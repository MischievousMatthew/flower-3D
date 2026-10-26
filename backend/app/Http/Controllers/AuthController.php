<?php

namespace App\Http\Controllers;

use App\Support\Phone;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TwoFactorLoginChallenge;
use App\Services\LoginAuditService;
use App\Services\AccountSuspensionService;
use App\Services\LoginGuard;
use App\Support\AccountLookup;
use App\Support\PlatformSettings;
use App\Support\SingleSession;
use App\Services\EmailOtpService;
use App\Services\OtpProblem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Exception;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly LoginAuditService $loginAuditService)
    {
    }

    public function sendOtp(Request $request)
    {
        // The code is only for signing up, so don't send one while sign-ups are closed
        if (!PlatformSettings::get('registrations_open')) {
            return response()->json(['success' => false, 'message' => 'New customer sign-ups are paused right now. Please try again later.'], 403);
        }

        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        if (User::whereRaw('lower(email) = ?', [strtolower(trim($request->email))])->exists()) {
            throw ValidationException::withMessages(['email' => 'This email already has an account. Log in instead, or reset your password if you forgot it.']);
        }

        return $this->sendCode($request->email, $request->ip(), EmailOtpService::PURPOSE_SIGNUP, 'We sent a 6-digit code to your email.');
    }

    /** Sends a code and answers with a message the page can show as-is */
    private function sendCode(string $email, string $ip, string $purpose, string $sent)
    {
        try {
            EmailOtpService::send($email, $ip, $purpose);
        } catch (OtpProblem $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'retry_after' => $e->retryAfter], $e->status);
        } catch (\Throwable $e) {
            Log::error('Sending a code failed', ['purpose' => $purpose, 'message' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => "We couldn't send the email right now. Please try again in a few minutes."], 503);
        }

        return response()->json(['success' => true, 'message' => $sent, 'retry_after' => 60, 'expires_in' => EmailOtpService::EXPIRES_IN_SECONDS]);
    }

    /**
     * POST /auth/check-availability {username?, email?}
     * Lets the sign-up form say "taken" on the step where it's typed, not at the end.
     */
    public function checkAvailability(Request $request)
    {
        $data = $request->validate([
            'username' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);
        $out = [];
        if (! empty($data['username'])) {
            $out['username'] = ! User::whereRaw('lower(username) = ?', [mb_strtolower(trim($data['username']))])->exists();
        }
        if (! empty($data['email'])) {
            $out['email'] = ! User::whereRaw('lower(email) = ?', [mb_strtolower(trim($data['email']))])->exists();
        }

        return response()->json(['success' => true, 'available' => $out]);
    }

    /**
     * POST /auth/password/forgot {identifier}
     * Emails a reset code to the account's address. The answer is the same
     * whether or not an account matched, so it can't be used to find accounts.
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['identifier' => 'required|string|max:255']);
        $user = $this->userFor($data['identifier']);
        $generic = response()->json([
            'success' => true,
            'message' => 'If an account matches, we emailed it a 6-digit code. It can take a minute to arrive — check your spam folder too.',
            'retry_after' => 60,
            'expires_in' => EmailOtpService::EXPIRES_IN_SECONDS,
        ]);

        if (! $user || ! $user->email) {
            return $generic;
        }
        try {
            EmailOtpService::send($user->email, $request->ip(), EmailOtpService::PURPOSE_RESET);
        } catch (OtpProblem $e) {
            // Waiting out a cooldown: the code already sent still works
            if ($e->status === 429) {
                return $generic;
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->status);
        }

        return $generic;
    }

    /** POST /auth/password/reset {identifier, otp, password, password_confirmation} */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:255',
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
        ], [
            'password.regex' => 'Use at least one uppercase letter, one lowercase letter and a number.',
            'otp.digits' => 'Enter the 6-digit code from the email.',
        ]);

        $user = $this->userFor($data['identifier']);
        if (! $user || ! $user->email) {
            throw ValidationException::withMessages(['otp' => "That code isn't right or has expired. Ask for a new one."]);
        }
        try {
            EmailOtpService::verify($user->email, $data['otp'], EmailOtpService::PURPOSE_RESET);
        } catch (OtpProblem $e) {
            throw ValidationException::withMessages(['otp' => $e->getMessage()]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])]);
        if (Schema::hasColumn('users', 'password_changed_at')) {
            $user->password_changed_at = now();
        }
        $user->save();
        // Signed out everywhere, and the wrong-password count starts over
        SingleSession::end($user);
        app(LoginGuard::class)->clear([$user->username, $user->email, $data['identifier']]);

        return response()->json([
            'success' => true,
            'message' => 'Your password was changed. Log in with your new password.',
            'username' => $user->username ?: $user->email,
        ]);
    }

    private function userFor(string $identifier): ?User
    {
        $id = mb_strtolower(trim($identifier));

        return User::whereRaw('lower(username) = ?', [$id])->orWhereRaw('lower(email) = ?', [$id])->first();
    }

    public function register(Request $request)
    {
        if (!PlatformSettings::get('registrations_open')) {
            return response()->json(['success' => false, 'message' => 'New customer sign-ups are paused right now. Please try again later.'], 403);
        }

        // "Ana" and "ana" are the same name, as the availability check already says
        $takenIgnoringCase = fn (string $column, string $message) => function ($attribute, $value, $fail) use ($column, $message) {
            if (User::whereRaw("lower({$column}) = ?", [mb_strtolower(trim((string) $value))])->exists()) {
                $fail($message);
            }
        };

        $request->validate([
            'name'                  => 'required|string|max:255',
            'surname'               => 'required|string|max:255',
            'username'              => ['required', 'string', 'max:255', 'alpha_dash', $takenIgnoringCase('username', 'This username is taken. Try another one.')],
            'email'                 => ['required', 'email', 'max:255', $takenIgnoringCase('email', 'This email already has an account. Log in instead, or reset your password if you forgot it.')],
            'contact_number'        => ['nullable', 'string', 'regex:' . Phone::PATTERN],
            'password'              => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'otp'                   => 'required|string|size:6',
        ], [
            'password.regex' => 'Password must contain uppercase, lowercase, and a number.',
        ]);

        try {
            EmailOtpService::verify($request->email, $request->otp, EmailOtpService::PURPOSE_SIGNUP);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['otp' => $e->getMessage()]);
        }

        $user = User::create([
            'name'               => $request->name,
            'surname'            => $request->surname,
            'username'           => trim($request->username),
            'email'              => mb_strtolower(trim($request->email)),
            'contact_number'     => $request->contact_number,
            'password'           => Hash::make($request->password),
            'is_verified'        => true,
            'email_verified_at'  => now(),
            'role'               => 'customer',
        ]);

        $token = Str::random(80);
        SingleSession::start($user, hash('sha256', $token), $request->input('device_id'));

        return response()->json([
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => $user->only(['id', 'name', 'surname', 'username', 'email', 'role']),
        ], 201);
    }

    /**
     * Refuses a sign-in for a reason about the account itself (suspended,
     * locked, unverified). The code lets the login page show this message
     * instead of a generic "wrong username or password".
     */
    private function refuseLogin(string $code, string $message, array $extra = []): never
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'errors' => ['username' => [$message]],
        ] + $extra, 422));
    }

    /** Wrong username or password, with how many tries are left before the lock */
    private function refuseCredentials(LoginGuard $guard, string $identifier): never
    {
        $left = $guard->attemptsLeft($identifier);
        if ($left === 0) {
            $seconds = $guard->lockedSeconds($identifier);
            $this->refuseLogin('account_locked', LoginGuard::lockedMessage(max(1, (int) ceil($seconds / 60))), ['retry_after' => $seconds]);
        }
        // How many tries are left is only shared when the admin turned it on (Security → Sign-in protection)
        $this->refuseLogin('invalid_credentials', "That username or password doesn't match our records.", PlatformSettings::get('login_show_attempts') ? ['attempts_left' => $left] : []);
    }

    public function login(Request $request)
    {
        // Nothing typed here is logged: people sometimes put their password in the
        // username box. Failed sign-ins are kept in login_attempts instead.
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'login_context' => 'nullable|array',
            ]);

            $guard = app(LoginGuard::class);
            $identifier = LoginGuard::identifier($request->username);
            if ($seconds = $guard->lockedSeconds($identifier)) {
                $guard->recordFailure($request, $identifier, 'locked');
                $this->refuseLogin('account_locked', LoginGuard::lockedMessage(max(1, (int) ceil($seconds / 60))), ['retry_after' => $seconds]);
            }

            // Username or email, capitals don't matter
            [$user, $passwordOk] = AccountLookup::signIn(User::query(), $request->username, $request->password);

            if (!$user) {
                $guard->recordFailure($request, $identifier, 'unknown_account');
                $this->refuseCredentials($guard, $identifier);
            }

            if (!$passwordOk) {
                $guard->recordFailure($request, $identifier, 'wrong_password', $user->id);
                $this->refuseCredentials($guard, $identifier);
            }

            // Suspension is checked before verification so a suspended account
            // always hears that it's suspended
            if (AccountSuspensionService::isBlocked($user)) {
                $guard->recordFailure($request, $identifier, 'suspended', $user->id);
                $this->refuseLogin('account_suspended', AccountSuspensionService::messageFor($user));
            }

            if (!$user->is_verified) {
                $guard->recordFailure($request, $identifier, 'unverified', $user->id);
                $this->refuseLogin('account_unverified', "Your account isn't verified yet. Open the verification link we emailed you, then sign in again.");
            }

            // Only after the password is right, so it never reveals anything to a stranger
            $this->askBeforeTakingOver($user, $request);

            if (in_array($user->role, [User::ROLE_CUSTOMER, User::ROLE_VENDOR], true) && $user->two_factor_enabled) {
                $challengeToken = Str::random(80);
                TwoFactorLoginChallenge::where('user_id', $user->id)->delete();
                TwoFactorLoginChallenge::create([
                    'user_id' => $user->id,
                    'challenge_token_hash' => hash('sha256', $challengeToken),
                    'expires_at' => now()->addMinutes(5),
                ]);

                return response()->json([
                    'requires_two_factor' => true,
                    'challenge_token' => $challengeToken,
                ]);
            }

            return $this->completeUserLogin($user, $request);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Login error', ['exception' => get_class($e), 'message' => $e->getMessage(), 'at' => basename($e->getFile()) . ':' . $e->getLine()]);
            throw ValidationException::withMessages([
                'username' => 'An error occurred during login. Please try again.',
            ]);
        }
    }

    public function verifyTwoFactorPasskey(Request $request)
    {
        $request->validate([
            'challenge_token' => ['required', 'string', 'size:80'],
            'passkey' => ['required', 'digits:6'],
            'login_context' => ['nullable', 'array'],
        ]);
        $challenge = $this->activeLoginChallenge($request->challenge_token);
        $user = $challenge->user;

        $challenge->increment('passkey_attempts');
        if (!Hash::check($request->passkey, $user->two_factor_passkey_hash)) {
            $challenge->refresh();
            if ($challenge->passkey_attempts >= 5) {
                $challenge->update(['is_locked' => true]);
            }
            $remaining = max(0, 5 - $challenge->passkey_attempts);
            throw ValidationException::withMessages([
                'passkey' => [$remaining > 0
                    ? "That passkey isn't right. {$remaining} " . ($remaining === 1 ? 'try' : 'tries') . ' left, or email yourself a code instead.'
                    : 'Too many wrong passkeys. Sign in again and choose "Email me a code".'],
            ]);
        }

        $challenge->delete();
        return $this->completeUserLogin($user, $request);
    }

    public function sendTwoFactorLoginOtp(Request $request)
    {
        $request->validate(['challenge_token' => ['required', 'string', 'size:80']]);
        $challenge = $this->activeLoginChallenge($request->challenge_token);

        try {
            EmailOtpService::send($challenge->user->email, $request->ip(), EmailOtpService::PURPOSE_SIGNIN);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['otp' => [$e->getMessage()]]);
        }

        return response()->json([
            'success' => true,
            'masked_email' => $this->maskEmail($challenge->user->email),
            'expires_in' => EmailOtpService::EXPIRES_IN_SECONDS,
        ]);
    }

    public function verifyTwoFactorLoginOtp(Request $request)
    {
        $request->validate([
            'challenge_token' => ['required', 'string', 'size:80'],
            'otp' => ['required', 'digits:6'],
            'login_context' => ['nullable', 'array'],
        ]);
        $challenge = $this->activeLoginChallenge($request->challenge_token);

        try {
            if (!EmailOtpService::verify($challenge->user->email, $request->otp, EmailOtpService::PURPOSE_SIGNIN)) {
                throw new Exception('Invalid OTP. Please request a new one.');
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['otp' => [$e->getMessage()]]);
        }

        $user = $challenge->user;
        $challenge->delete();
        return $this->completeUserLogin($user, $request);
    }

    private function activeLoginChallenge(string $token): TwoFactorLoginChallenge
    {
        $challenge = TwoFactorLoginChallenge::with('user')
            ->where('challenge_token_hash', hash('sha256', $token))
            ->first();

        if (!$challenge || $challenge->is_locked || now()->isAfter($challenge->expires_at)) {
            $challenge?->delete();
            throw ValidationException::withMessages([
                'two_factor' => ['This login verification has expired or is locked. Please sign in again.'],
            ]);
        }

        return $challenge;
    }

    /** "Already logged in elsewhere": the sign-in page asks, then retries with continue_here */
    private function askBeforeTakingOver(User $user, Request $request): void
    {
        if ($request->boolean('continue_here') || !SingleSession::isActiveElsewhere($user, $request->input('device_id'))) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'success' => false,
            'code' => 'session_active',
            'message' => SingleSession::ACTIVE_MESSAGE,
        ], 409));
    }

    private function completeUserLogin(User $user, Request $request)
    {
        $token = Str::random(80);
            SingleSession::start($user, hash('sha256', $token), $request->input('device_id'));
            $this->loginAuditService->logUserLogin($user, $request);

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'surname' => $user->surname,
                'username' => $user->username,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'role' => $user->role,
                'vendor_data' => $user->vendor_data,
            ];

        return response()->json([
                'message' => 'Login successful',
                'token'   => $token,
                'user'    => $userData,
                'redirect_url' => $this->getRedirectUrl((object)$userData),
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        return substr($local, 0, min(3, strlen($local))) . str_repeat('*', max(5, strlen($local) - 3)) . '@' . $domain;
    }

    private function getRedirectUrl($user)
    {
        $department = strtolower(trim($user->department ?? ''));
        $role = strtolower(trim($user->role ?? ''));

        if ($department === 'procurement') {
            if ($role === 'finance manager') {
                return '/erp/procurement/finance/funding-request';
            }
            if ($role === 'inventory manager') {
                return '/erp/procurement/inventory/dashboard';
            }
            if ($role === 'supply chain coordinator') {
                return '/erp/procurement/supply-chain/dashboard';
            }
        }

        if ($department === 'hr' || $department === 'human resources') {
            if ($role === 'hr manager') {
                return '/erp/hr';
            }
        }

        if ($department === 'accounting') {
            if ($role === 'accounting manager') {
                return '/erp/accountant/dashboard';
            }
        }

        if ($department === 'crm') {
            if ($role === 'crm specialist') {
                return '/erp/crm/dashboard';
            }
        }

        if ($role === 'vendor') {
            return '/vendor/products';
        }

        if ($role === 'admin') {
            return '/admin/vendor-requests';
        }

        if ($role === 'customer') {
            return '/shop';
        }

        return '/erp/dashboard';
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            // If the token belongs to an Employee (employee-token guard)
            if ($user instanceof \App\Models\Employee) {
                $assignments = $user->activeAssignments()
                    ->with(['department', 'role.permissions'])
                    ->get()
                    ->map(fn ($a) => [
                        'id'          => $a->id,
                        'label'       => $a->label,
                        'is_primary'  => $a->is_primary,
                        'department'  => [
                            'id'   => $a->department->id,
                            'name' => $a->department->name,
                            'slug' => $a->department->slug,
                        ],
                        'role'        => [
                            'id'      => $a->role->id,
                            'name'    => $a->role->name,
                            'slug'    => $a->role->slug,
                            'level'   => $a->role->hierarchy_level,
                            'modules' => $a->role->accessible_modules,
                        ],
                        'permissions' => $a->role->permissions->pluck('slug'),
                    ]);

                return response()->json([
                    'success' => true,
                    'data'    => array_merge($user->toArray(), [
                        'user_type'   => 'employee',
                        'assignments' => $assignments,
                    ]),
                ]);
            }

            // Regular User (vendor / customer / admin)
            return response()->json([
                'user' => [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'surname'        => $user->surname,
                    'username'       => $user->username,
                    'email'          => $user->email,
                    'contact_number' => $user->contact_number,
                    'role'           => $user->role,
                    'is_verified'    => $user->is_verified,
                    'vendor_data'    => $user->vendor_data,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Auth me error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /** GET /api/auth/session: 200 while this browser's session is current */
    public function session()
    {
        return response()->json(['success' => true]);
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user instanceof User) {
                SingleSession::end($user);
            }
            
            Log::info('User logged out successfully');
            
            return response()->json([
                'message' => 'Logged out successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json(['message' => 'Logout completed'], 200);
        }
    }
}
