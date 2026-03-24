<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class VendorProfileController extends Controller
{
    /**
     * Get vendor profile data
     */
    public function getProfile(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found or not approved'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $vendorApplication
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching vendor profile', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile'
            ], 500);
        }
    }

    /**
     * Update payment details
     */
    public function updatePaymentDetails(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'payout_method' => 'required|in:bank,gcash,maya',
                'account_holder_name' => 'required|string|max:255',
                'account_number' => 'required|string|max:255',
                'bank_name' => 'required|string|max:255',
                'billing_address' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendorApplication->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Payment details updated successfully',
                'data' => $vendorApplication->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating payment details', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment details'
            ], 500);
        }
    }

    /**
     * Update product details (UPDATED)
     */
    public function updateProductDetails(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'product_types' => 'required|array|min:1',
                'product_types.*' => 'required|in:bouquet,flower,bouquet_and_flower',

                'price_min' => 'required|numeric|min:0',
                'price_max' => 'required|numeric|gte:price_min',

                'same_day_delivery' => 'nullable|boolean',

                'cutoff_times' => 'nullable|array',
                'cutoff_times.*.day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'cutoff_times.*.time' => 'required|date_format:H:i',
            ], [
                'price_max.gte' => 'Maximum price must be greater than or equal to minimum price',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendorApplication->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product details updated successfully',
                'data' => $vendorApplication->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating product details', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update product details'
            ], 500);
        }
    }

    /**
     * Update delivery details (UPDATED - no delivery_fee, only self delivery)
     */
    public function updateDeliveryDetails(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'delivery_handled_by' => 'required|in:self',
                'max_orders_per_day' => 'required|integer|min:1',
                'lead_time' => 'required|string|max:255',
                'cancellation_policy' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendorApplication->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Delivery details updated successfully',
                'data' => $vendorApplication->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating delivery details', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery details'
            ], 500);
        }
    }

    /**
     * Update store logo (replaces profile photo functionality)
     */
    public function updateStoreLogo(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'store_logo' => 'required|image|mimes:jpeg,jpg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Delete old store logo if exists
            if ($vendorApplication->store_logo_path) {
                Storage::disk('public')->delete($vendorApplication->store_logo_path);
            }

            // Store new store logo
            $path = $request->file('store_logo')->store('store_logos', 'public');

            $vendorApplication->update([
                'store_logo_path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Store logo updated successfully',
                'data' => $vendorApplication->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating store logo', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update store logo'
            ], 500);
        }
    }

    /**
     * Update general profile information
     */
    public function updateGeneralInfo(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'store_name' => 'sometimes|required|string|max:255',
                'store_description' => 'nullable|string|max:1000',
                'store_address' => 'sometimes|required|string|max:500',
                'service_areas' => 'nullable|string|max:500',
                'operating_hours' => 'nullable|string|max:255',
                'owner_name' => 'sometimes|required|string|max:255',
                'position' => 'nullable|string|max:255',
                'contact_number' => 'sometimes|required|string|max:20',
                'facebook_page' => 'nullable|url|max:255',
                'instagram_page' => 'nullable|url|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $vendorApplication->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $vendorApplication->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating general info', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Delete store logo
     */
    public function deleteStoreLogo(Request $request)
    {
        try {
            $user = Auth::user();
            
            $vendorApplication = VendorApplication::where('email', $user->email)
                ->where('status', 'approved')
                ->first();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found'
                ], 404);
            }

            if ($vendorApplication->store_logo_path) {
                Storage::disk('public')->delete($vendorApplication->store_logo_path);
                
                $vendorApplication->update([
                    'store_logo_path' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Store logo deleted successfully',
                'data' => $vendorApplication->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting store logo', [
                'error' => $e->getMessage(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete store logo'
            ], 500);
        }
    }

    /**
     * Change vendor account password
     * PUT /vendor/profile/change-password
     */
    public function changePassword(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'current_password'      => 'required|string',
                'password'              => 'required|string|min:8|confirmed',
                'password_confirmation' => 'required|string',
            ], [
                'password.min'       => 'New password must be at least 8 characters.',
                'password.confirmed' => 'New passwords do not match.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            // Verify the current password is correct
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => [
                        'current_password' => ['The current password you entered is incorrect.'],
                    ],
                ], 422);
            }

            // Prevent reusing the same password
            if (Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => [
                        'password' => ['New password must be different from your current password.'],
                    ],
                ], 422);
            }

            $user->password = Hash::make($request->password);
            
            // Clear the needs_password_change flag if it exists
            if ($user->role === 'vendor' && isset($user->vendor_data['needs_password_change'])) {
                $vendorData = $user->vendor_data;
                $vendorData['needs_password_change'] = false;
                $user->vendor_data = $vendorData;
            }

            $user->save();

            Log::info('Vendor changed password', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Error changing password', [
                'error' => $e->getMessage(),
                'user'  => Auth::id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update password.',
            ], 500);
        }
    }
}