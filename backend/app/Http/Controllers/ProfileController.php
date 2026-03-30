<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function getProfile()
    {
        $authUser = null;
        try {
            $authUser = Auth::user();

            return response()->json([
                'success' => true,
                'user'    => $this->formatUserResponse($authUser),
            ]);
        } catch (\Throwable $e) {
            Log::error('Profile fetch error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile data',
                'debug'   => [
                    'exception_class'   => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'auth_user_null'    => $authUser === null,
                ],
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();

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
            ]);

            if ($request->hasFile('profile_picture')) {
                // Delete old picture from Cloudinary if it's a public_id (not a URL)
                if ($user->profile_picture && !str_starts_with($user->profile_picture, 'http')) {
                    try {
                        cloudinary()->destroy($user->profile_picture);
                    } catch (\Throwable $e) {
                        Log::warning('Cloudinary delete failed: ' . $user->profile_picture);
                    }
                }

                $result = cloudinary()->upload($request->file('profile_picture')->getRealPath(), [
                    'folder'        => 'profile_pictures',
                    'resource_type' => 'image',
                ]);

                $validated['profile_picture'] = $result->getPublicId();
            }

            if ($request->has('full_name')) {
                $nameParts            = explode(' ', $request->full_name, 2);
                $validated['name']    = $nameParts[0];
                $validated['surname'] = $nameParts[1] ?? '';
                unset($validated['full_name']);
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user'    => $this->formatUserResponse($user->fresh()),
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update profile'], 500);
        }
    }

    public function updateProfilePicture(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if (!$request->hasFile('profile_picture')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No file uploaded'
                ], 400);
            }

            $result = cloudinary()->upload(
                $request->file('profile_picture')->getRealPath(),
                [
                    'folder' => 'profile_pictures',
                    'resource_type' => 'image',
                ]
            );

            $user->profile_picture = $result->getPublicId();
            $user->save();

            return response()->json([
                'success' => true,
                'profile_picture' => $result->getSecurePath(),
            ]);

        } catch (\Throwable $e) {
            Log::error('UPLOAD ERROR: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(), // 🔥 shows real issue
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

        // Already a full URL — return as-is
        // This covers: old local storage URLs that got migrated,
        // or any Cloudinary secure URLs stored directly
        if (str_starts_with($profilePicture, 'http')) {
            return $profilePicture;
        }

        // Cloudinary public_id — generate the URL
        try {
            return cloudinary()->getUrl($profilePicture);
        } catch (\Throwable $e) {
            Log::warning('Could not resolve Cloudinary URL for: ' . $profilePicture);
            return $fallback;
        }
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