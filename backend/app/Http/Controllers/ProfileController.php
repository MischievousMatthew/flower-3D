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

            // Debug info to allow root-cause identification from runtime.
            // (No tokens/PII included.)
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile data',
                'debug'   => [
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'auth_user_null' => $authUser === null,
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

            // ── Upload new profile picture to Cloudinary ───────────────────
            if ($request->hasFile('profile_picture')) {
                // Delete old picture from Cloudinary if it exists
                if ($user->profile_picture) {
                    try {
                        cloudinary()->destroy($user->profile_picture);
                    } catch (\Exception $e) {
                        Log::warning('Cloudinary delete failed for profile picture: ' . $user->profile_picture);
                    }
                }

                $result = cloudinary()->upload($request->file('profile_picture')->getRealPath(), [
                    'folder'        => 'profile_pictures',
                    'resource_type' => 'image',
                ]);

                $validated['profile_picture'] = $result->getPublicId();
            }

            if ($request->has('full_name')) {
                $nameParts         = explode(' ', $request->full_name, 2);
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
        } catch (\Exception $e) {
            Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update profile'], 500);
        }
    }

    public function updateProfilePicture(Request $request)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            $user = Auth::user();

            // ── Delete old picture from Cloudinary ────────────────────────
            if ($user->profile_picture) {
                try {
                    cloudinary()->destroy($user->profile_picture);
                } catch (\Exception $e) {
                    Log::warning('Cloudinary delete failed: ' . $user->profile_picture);
                }
            }

            // ── Upload new picture to Cloudinary ──────────────────────────
            $result = cloudinary()->upload($request->file('profile_picture')->getRealPath(), [
                'folder'        => 'profile_pictures',
                'resource_type' => 'image',
            ]);

            $user->profile_picture = $result->getPublicId();
            $user->save();

            return response()->json([
                'success'         => true,
                'message'         => 'Profile picture updated successfully',
                'profile_picture' => $result->getSecurePath(),
                'user'            => $this->formatUserResponse($user->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Profile picture upload error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to upload profile picture'], 500);
        }
    }

    private function formatUserResponse($user)
    {
        // ── Generate profile picture URL from Cloudinary public_id ────────
        $profilePicture = $user->profile_picture
            ? cloudinary()->getUrl($user->profile_picture)
            : 'https://ui-avatars.com/api/?name=' . urlencode($user->name . ' ' . $user->surname) . '&background=7F9CF5&color=ffffff&size=128';

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