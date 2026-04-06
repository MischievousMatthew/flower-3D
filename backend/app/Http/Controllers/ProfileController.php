<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
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
                'contact_number' => 'nullable|digits:11',
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
        ];
    }
}
