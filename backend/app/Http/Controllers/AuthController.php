<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TwoFactorLoginChallenge;
use App\Services\LoginAuditService;
use App\Services\EmailOtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            EmailOtpService::send(
                email: $request->email,
                ip:    $request->ip(),
            );

            return response()->json([
                'message' => 'OTP sent to your email address.'
            ]);

        } catch (\Throwable $e) {

            Log::error('SEND OTP ERROR', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to send OTP',
                'error'   => $e->getMessage(), // 👈 TEMP DEBUG
            ], 500);
        }
    }

    public function register(Request $request)
    {
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
        $user->forceFill([
            'api_token' => hash('sha256', $token),
        ])->save();

        return response()->json([
            'message' => 'Registration successful',
            'token'   => $token,
            'user'    => $user->only(['id', 'name', 'surname', 'username', 'email', 'role']),
        ], 201);
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
                throw ValidationException::withMessages([
                    'username' => 'The provided credentials are incorrect.',
                ]);
            }

            // Check password
            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Password mismatch for user: ' . $user->username);
                throw ValidationException::withMessages([
                    'username' => 'The provided credentials are incorrect.',
                ]);
            }

            if (!$user->is_verified) {
                Log::warning('Unverified user attempted login: ' . $user->username);
                throw ValidationException::withMessages([
                    'username' => 'Your account is not verified.',
                ]);
            }

            if ($user->role === User::ROLE_CUSTOMER && $user->two_factor_enabled) {
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
                'passkey' => ["Invalid passkey. {$remaining} attempt(s) remaining."],
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
            EmailOtpService::send($challenge->user->email, $request->ip());
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

    private function completeUserLogin(User $user, Request $request)
    {
        $token = Str::random(80);
            $user->forceFill([
                'api_token' => hash('sha256', $token),
            ])->save();
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

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if ($user) {
                $user->forceFill([
                    'api_token' => null,
                ])->save();
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
            Log::info('=== VENDOR LOGIN REQUEST ===', $request->all());
            
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $user = User::where('username', $request->username)
                        ->orWhere('email', $request->username)
                        ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
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
            
            $token = Str::random(80);
            $user->forceFill([
                'api_token' => hash('sha256', $token),
            ])->save();
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
