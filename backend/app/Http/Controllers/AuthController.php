<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TwoFactorLoginChallenge;
use App\Services\LoginAuditService;
use App\Services\AccountSuspensionService;
use App\Services\LoginGuard;
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
use Illuminate\Support\Facades\DB;
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

        return response()->json(['success' => true, 'message' => $sent, 'retry_after' => 60]);
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
            EmailOtpService::verify($user->email, $data['otp']);
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

        $request->validate([
            'name'                  => 'required|string|max:255',
            'surname'               => 'required|string|max:255',
            'username'              => 'required|string|max:255|unique:users|alpha_dash',
            'email'                 => 'required|email|max:255|unique:users',
            'contact_number'        => 'nullable|string|regex:/^[0-9]{10,15}$/',
            'password'              => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'otp'                   => 'required|string|size:6',
        ], [
            'password.regex' => 'Password must contain uppercase, lowercase, and a number.',
        ]);

        try {
            EmailOtpService::verify($request->email, $request->otp);
        } catch (\Exception $e) {
            throw ValidationException::withMessages(['otp' => $e->getMessage()]);
        }

        $user = User::create([
            'name'               => $request->name,
            'surname'            => $request->surname,
            'username'           => $request->username,
            'email'              => $request->email,
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
        $this->refuseLogin('invalid_credentials', "That username or password doesn't match our records.", ['attempts_left' => $left]);
    }

    public function login(Request $request)
    {
        DB::enableQueryLog();
        
        try {
            Log::info('=== LOGIN REQUEST ===', [
                'username' => $request->username,
            ]);
            
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

            // Find user by username or email
            $user = User::where('username', $request->username)
                        ->orWhere('email', $request->username)
                        ->first();

            Log::info('User lookup result:', [
                'found' => !is_null($user),
                'username' => $request->username,
                'user_id' => $user->id ?? null,
                'queries' => DB::getQueryLog()
            ]);

            if (!$user) {
                Log::warning('User not found: ' . $request->username);
                $guard->recordFailure($request, $identifier, 'unknown_account');
                $this->refuseCredentials($guard, $identifier);
            }

            // Check password
            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Password mismatch for user: ' . $user->username);
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
                Log::warning('Unverified user attempted login: ' . $user->username);
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
            Log::warning('Login validation failed', $e->errors());
            throw $e;
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Login Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'queries' => DB::getQueryLog()
            ]);
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
            if (!EmailOtpService::verify($challenge->user->email, $request->otp)) {
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

            Log::info('Login successful', [
                'user_id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'token_created' => true
            ]);

            $employee = \DB::table('employees')
                ->where('owner_id', $user->id)
                ->first();

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

            Log::info('Login user data:', $userData);

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

    public function employeeLogin(Request $request)
    {
        DB::enableQueryLog();
        
        try {
            Log::info('=== EMPLOYEE LOGIN REQUEST ===', $request->all());
            
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
                'login_context' => 'nullable|array',
            ]);

            // Find employee by username or email
            $employee = \DB::table('employees')
                ->where('username', $request->username)
                ->orWhere('email', $request->username)
                ->first();

            Log::info('Employee lookup result:', [
                'found' => !is_null($employee),
                'username' => $request->username,
            ]);

            if (!$employee) {
                Log::warning('Employee not found: ' . $request->username);
                throw ValidationException::withMessages([
                    'username' => 'Invalid employee credentials.',
                ]);
            }

            // Check password
            if (!Hash::check($request->password, $employee->password)) {
                Log::warning('Password mismatch for employee: ' . $employee->username);
                throw ValidationException::withMessages([
                    'username' => 'Invalid employee credentials.',
                ]);
            }

            // Check status
            if ($employee->status !== 'Active') {
                Log::warning('Inactive employee attempted login: ' . $employee->username);
                throw ValidationException::withMessages([
                    'username' => 'Your account is not active.',
                ]);
            }

            // Find the owner user to create token
            $user = User::find($employee->owner_id);
            
            if (!$user) {
                throw ValidationException::withMessages([
                    'username' => 'Account configuration error.',
                ]);
            }

            $token = Str::random(80);
            \DB::table('users')
                ->where('id', $user->id)
                ->update(['api_token' => hash('sha256', $token)]);

            $employeeData = [
                'id' => $employee->id,
                'owner_id' => $employee->owner_id,
                'name' => $employee->name,
                'email' => $employee->email,
                'username' => $employee->username,
                'role' => $employee->role,
                'department' => $employee->department,
                'status' => $employee->status,
            ];

            $redirectUrl = $this->getRedirectUrl((object)$employeeData);

            Log::info('Employee login successful', [
                'employee_id' => $employee->id,
                'username' => $employee->username,
                'role' => $employee->role,
                'department' => $employee->department,
                'redirect_url' => $redirectUrl,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee login successful',
                'token' => $token,
                'employee' => $employeeData,
                'redirect_url' => $redirectUrl,
            ]);
        } catch (ValidationException $e) {
            Log::warning('Employee login validation failed', $e->errors());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Employee login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'queries' => DB::getQueryLog()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login.',
            ], 500);
        }
    }

    public function employeeMe(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            
            // Get employee record
            $employee = \DB::table('employees')
                ->where('owner_id', $user->id)
                ->first();

            if (!$employee) {
                return response()->json(['error' => 'Employee record not found'], 404);
            }
            
            return response()->json([
                'employee' => [
                    'id' => $employee->id,
                    'owner_id' => $employee->owner_id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'username' => $employee->username,
                    'role' => $employee->role,
                    'department' => $employee->department,
                    'status' => $employee->status,
                    'phone' => $employee->phone,
                    'address' => $employee->address,
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Employee me error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function vendorLogin(Request $request)
    {
        try {
            Log::info('=== VENDOR LOGIN REQUEST ===', ['username' => $request->username]);
            
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $guard = app(LoginGuard::class);
            $identifier = LoginGuard::identifier($request->username);
            if ($minutes = $guard->lockedFor($identifier)) {
                $guard->recordFailure($request, $identifier, 'locked');
                throw ValidationException::withMessages(['username' => LoginGuard::lockedMessage($minutes)]);
            }

            $user = User::where('username', $request->username)
                        ->orWhere('email', $request->username)
                        ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                $guard->recordFailure($request, $identifier, $user ? 'wrong_password' : 'unknown_account', $user?->id);
                throw ValidationException::withMessages([
                    'username' => 'Invalid vendor credentials.',
                ]);
            }

            if ($user->role !== 'vendor') {
                throw ValidationException::withMessages([
                    'username' => 'Vendor access only.',
                ]);
            }

            if (!$user->is_verified) {
                throw ValidationException::withMessages([
                    'username' => 'Vendor account not active.',
                ]);
            }

            if (AccountSuspensionService::isBlocked($user)) {
                throw ValidationException::withMessages([
                    'username' => AccountSuspensionService::messageFor($user),
                ]);
            }
            
            $this->askBeforeTakingOver($user, $request);

            $token = Str::random(80);
            SingleSession::start($user, hash('sha256', $token), $request->input('device_id'));
            $this->loginAuditService->logUserLogin($user, $request);

            return response()->json([
                'message' => 'Vendor login successful',
                'token'   => $token,
                'user'    => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'username'   => $user->username,
                    'email'      => $user->email,
                    'role'       => $user->role,
                    'vendor_data'=> $user->vendor_data,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Vendor login error: ' . $e->getMessage());
            throw $e;
        }
    }
}
