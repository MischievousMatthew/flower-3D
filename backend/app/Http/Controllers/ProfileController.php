<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\TwoFactorManagementChallenge;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    private const TWO_FACTOR_CHALLENGE_MINUTES = 10;
    private const MAX_PASSKEY_ATTEMPTS = 5;

    public function __construct()
    {
        $this->middleware('token.auth:user');
    }

    public function getProfile()
    {
        try {
            return response()->json([
                'success' => true,
                'user'    => $this->formatUserResponse(Auth::user()),
            ]);
        } catch (\Throwable $e) {
            Log::error('Profile fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile data',
                'debug'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();
            $previousProfilePicture = $user->profile_picture;
            $uploadedProfilePicture = null;

            $validated = $request->validate([
                'name'           => 'sometimes|string|max:255',
                'surname'        => 'sometimes|string|max:255',
                'username'       => 'sometimes|string|max:255|alpha_dash|unique:users,username,' . $user->id,
                'date_of_birth'  => 'nullable|date|before:today',
                'gender'         => 'nullable|in:Male,Female,Other',
                'nationality'    => 'nullable|string|max:100',
                'address'        => 'nullable|string|max:500',
                'city'           => 'nullable|string|max:100',
                'postal_code'    => 'nullable|string|max:20',
                'contact_number' => [
                    'nullable',
                    'digits:11',
                    Rule::unique('users', 'contact_number')->ignore($user->id),
                ],
                'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            if ($request->hasFile('profile_picture')) {
                $result = CloudinaryHelper::uploadImage(
                    $request->file('profile_picture'),
                    'profile_pictures'
                );

                $uploadedProfilePicture = $result['public_id'];
                $validated['profile_picture'] = $result['public_id'];
            }

            if ($request->has('full_name')) {
                $nameParts            = explode(' ', $request->full_name, 2);
                $validated['name']    = $nameParts[0];
                $validated['surname'] = $nameParts[1] ?? '';
                unset($validated['full_name']);
            }

            $user->update($validated);

            if (
                isset($validated['profile_picture']) &&
                $previousProfilePicture &&
                !str_starts_with($previousProfilePicture, 'http') &&
                $previousProfilePicture !== $validated['profile_picture']
            ) {
                CloudinaryHelper::destroy($previousProfilePicture);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user'    => $this->formatUserResponse($user->fresh()),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            if (!empty($uploadedProfilePicture)) {
                CloudinaryHelper::destroy($uploadedProfilePicture);
            }
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateProfilePicture(Request $request)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            ]);

            $user = Auth::user();
            $previousProfilePicture = $user->profile_picture;
            $uploadedProfilePicture = null;

            $result = CloudinaryHelper::uploadImage(
                $request->file('profile_picture'),
                'profile_pictures'
            );

            $uploadedProfilePicture = $result['public_id'];
            $user->profile_picture = $result['public_id'];
            $user->save();

            if (
                $previousProfilePicture &&
                !str_starts_with($previousProfilePicture, 'http') &&
                $previousProfilePicture !== $user->profile_picture
            ) {
                CloudinaryHelper::destroy($previousProfilePicture);
            }

            return response()->json([
                'success'         => true,
                'message'         => 'Profile picture updated successfully',
                'profile_picture' => $result['secure_url'],
                'user'            => $this->formatUserResponse($user->fresh()),
            ]);

        } catch (\Throwable $e) {
            if (!empty($uploadedProfilePicture)) {
                CloudinaryHelper::destroy($uploadedProfilePicture);
            }
            Log::error('Profile picture upload error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'debug'   => $e->getMessage(),
            ], 500);
        }
    }

    public function startTwoFactorManagement(Request $request)
    {
        $user = Auth::user();
        $challenge = TwoFactorManagementChallenge::updateOrCreate(
            [
                'user_id' => $user->id,
                'session_token_hash' => $this->sessionTokenHash($request),
            ],
            [
                'email_otp_verified_at' => null,
                'authorized_at' => null,
                'passkey_attempts' => 0,
                'is_locked' => false,
                'expires_at' => now()->addMinutes(self::TWO_FACTOR_CHALLENGE_MINUTES),
            ],
        );

        try {
            EmailOtpService::send($user->email, $request->ip());
        } catch (\Throwable $e) {
            $challenge->delete();
            throw ValidationException::withMessages([
                'otp' => [$e->getMessage()],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'A verification code was sent to your registered email address.',
        ]);
    }

    public function verifyTwoFactorManagementEmailOtp(Request $request)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $user = Auth::user();
        $challenge = $this->activeChallenge($request);

        try {
            if (!EmailOtpService::verify($user->email, $request->otp)) {
                throw new \Exception('Invalid OTP. Please request a new one.');
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['otp' => [$e->getMessage()]]);
        }

        $challenge->forceFill([
            'email_otp_verified_at' => now(),
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_CHALLENGE_MINUTES),
        ])->save();

        return response()->json([
            'success' => true,
            'passkey_configured' => !empty($user->two_factor_passkey_hash),
        ]);
    }

    public function sendTwoFactorManagementAlternativeOtp(Request $request)
    {
        $challenge = $this->activeChallenge($request, true);
        $user = Auth::user();

        try {
            EmailOtpService::send($user->email, $request->ip());
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['otp' => [$e->getMessage()]]);
        }

        $challenge->forceFill([
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_CHALLENGE_MINUTES),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'A new verification code was sent to your registered email address.',
        ]);
    }

    public function verifyTwoFactorManagementPasskey(Request $request)
    {
        $request->validate(['passkey' => ['required', 'digits:6']]);
        $challenge = $this->activeChallenge($request, true);
        $user = Auth::user();

        if (!$user->two_factor_passkey_hash) {
            throw ValidationException::withMessages([
                'passkey' => ['No security passkey is configured. Try another way instead.'],
            ]);
        }

        if ($challenge->is_locked) {
            throw ValidationException::withMessages([
                'passkey' => ['Too many failed passkey attempts. Start again.'],
            ]);
        }

        $challenge->increment('passkey_attempts');
        if (!Hash::check($request->passkey, $user->two_factor_passkey_hash)) {
            $challenge->refresh();
            if ($challenge->passkey_attempts >= self::MAX_PASSKEY_ATTEMPTS) {
                $challenge->update(['is_locked' => true]);
            }

            $remaining = max(0, self::MAX_PASSKEY_ATTEMPTS - $challenge->passkey_attempts);
            throw ValidationException::withMessages([
                'passkey' => ["Invalid passkey. {$remaining} attempt(s) remaining."],
            ]);
        }

        $this->authorizeChallenge($challenge);

        return response()->json(['success' => true]);
    }

    public function verifyTwoFactorManagementAlternativeOtp(Request $request)
    {
        $request->validate(['otp' => ['required', 'digits:6']]);
        $challenge = $this->activeChallenge($request, true);
        $user = Auth::user();

        try {
            if (!EmailOtpService::verify($user->email, $request->otp)) {
                throw new \Exception('Invalid OTP. Please request a new one.');
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['otp' => [$e->getMessage()]]);
        }

        $this->authorizeChallenge($challenge);

        return response()->json(['success' => true]);
    }

    public function setTwoFactorPasskey(Request $request)
    {
        $request->validate(['passkey' => ['required', 'digits:6']]);
        $this->activeChallenge($request, true, true);

        $user = Auth::user();
        $user->forceFill([
            'two_factor_passkey_hash' => Hash::make($request->passkey),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Security passkey saved.',
        ]);
    }

    private function activeChallenge(Request $request, bool $requireEmailVerification = false, bool $requireAuthorization = false): TwoFactorManagementChallenge
    {
        $challenge = TwoFactorManagementChallenge::query()
            ->where('user_id', Auth::id())
            ->where('session_token_hash', $this->sessionTokenHash($request))
            ->first();

        if (!$challenge || now()->isAfter($challenge->expires_at)) {
            $challenge?->delete();
            throw ValidationException::withMessages([
                'two_factor' => ['Your security verification session has expired. Start again.'],
            ]);
        }

        if ($challenge->is_locked) {
            throw ValidationException::withMessages([
                'two_factor' => ['This security verification is locked. Start again.'],
            ]);
        }

        if ($requireEmailVerification && !$challenge->email_otp_verified_at) {
            throw ValidationException::withMessages([
                'two_factor' => ['Verify the email code before choosing another verification method.'],
            ]);
        }

        if ($requireAuthorization && !$challenge->authorized_at) {
            throw ValidationException::withMessages([
                'two_factor' => ['Complete the second security verification first.'],
            ]);
        }

        return $challenge;
    }

    private function authorizeChallenge(TwoFactorManagementChallenge $challenge): void
    {
        $challenge->forceFill([
            'authorized_at' => now(),
            'expires_at' => now()->addMinutes(self::TWO_FACTOR_CHALLENGE_MINUTES),
        ])->save();
    }

    private function sessionTokenHash(Request $request): string
    {
        return hash('sha256', (string) $request->bearerToken());
    }

    private function resolveProfilePictureUrl(?string $profilePicture, string $fullName): string
    {
        $fallback = 'https://ui-avatars.com/api/?name=' . urlencode($fullName)
                  . '&background=7F9CF5&color=ffffff&size=128';

        if (!$profilePicture) {
            return $fallback;
        }

        if (str_starts_with($profilePicture, 'http')) {
            return $profilePicture;
        }

        // Build URL directly — no package needed
        return CloudinaryHelper::getUrl($profilePicture);
    }

    private function formatUserResponse($user): array
    {
        $fullName       = trim(($user->name ?? '') . ' ' . ($user->surname ?? ''));
        $profilePicture = $this->resolveProfilePictureUrl($user->profile_picture, $fullName);

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'surname'           => $user->surname,
            'username'          => $user->username,
            'email'             => $user->email,
            'contact_number'    => $user->contact_number,
            'is_verified'       => $user->is_verified,
            'role'              => $user->role,
            'vendor_data'       => $user->vendor_data,
            'email_verified_at' => $user->email_verified_at,
            'created_at'        => $user->created_at,
            'updated_at'        => $user->updated_at,
            'date_of_birth'     => $user->date_of_birth,
            'gender'            => $user->gender,
            'nationality'       => $user->nationality,
            'address'           => $user->address,
            'city'              => $user->city,
            'postal_code'       => $user->postal_code,
            'profile_picture'   => $profilePicture,
            'plan'              => $user->plan,
            'two_factor_passkey_configured' => !empty($user->two_factor_passkey_hash),
        ];
    }
}
