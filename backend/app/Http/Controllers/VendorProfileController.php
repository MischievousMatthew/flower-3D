<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class VendorProfileController extends Controller
{
    public function getProfile(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found or not approved',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $this->serializeVendorApplication($vendorApplication),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching vendor profile', [
                'error' => $e->getMessage(),
                'user'  => Auth::id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch profile'], 500);
        }
    }

    public function updatePaymentDetails(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor application not found',
                ], 404);
            }

            $payload = array_map(
                static fn ($value) => is_string($value) ? trim($value) : $value,
                $request->only([
                    'payout_method',
                    'account_holder_name',
                    'account_number',
                    'bank_name',
                    'billing_address',
                ])
            );

            $validator = Validator::make($payload, [
                'payout_method'       => ['required', Rule::in(['bank', 'gcash', 'maya'])],
                'account_holder_name' => ['required', 'string', 'max:255'],
                'account_number'      => ['required', 'string', 'max:50'],
                'bank_name'           => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::requiredIf(static fn () => ($payload['payout_method'] ?? null) === 'bank'),
                ],
                'billing_address'     => ['required', 'string', 'max:500'],
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            if (in_array($validated['payout_method'], ['gcash', 'maya'], true) && empty($validated['bank_name'])) {
                $validated['bank_name'] = ucfirst($validated['payout_method']);
            }

            $vendorApplication->fill($validated);
            $vendorApplication->save();

            return response()->json([
                'success' => true,
                'message' => 'Payment details updated successfully',
                'data'    => [
                    'id'                        => $vendorApplication->id,
                    'application_id'            => $vendorApplication->application_id,
                    'payout_method'             => $validated['payout_method'],
                    'account_holder_name'       => $validated['account_holder_name'],
                    'decrypted_account_number'  => $validated['account_number'],
                    'bank_name'                 => $validated['bank_name'] ?? '',
                    'billing_address'           => $validated['billing_address'],
                    'payment_details_completed' => (bool) $vendorApplication->payment_details_completed,
                    'profile_fully_completed'   => (bool) $vendorApplication->profile_fully_completed,
                    'profile_completion_percentage' => (int) ($vendorApplication->profile_completion_percentage ?? 0),
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Error updating payment details', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user'    => Auth::id(),
                'payload' => $request->except(['account_number']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment details',
            ], 500);
        }
    }

    public function updateProductDetails(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json(['success' => false, 'message' => 'Vendor application not found'], 404);
            }

            $payload = [
                'product_types'     => array_values(array_filter((array) $request->input('product_types', []))),
                'price_min'         => $request->input('price_min'),
                'price_max'         => $request->input('price_max'),
                'same_day_delivery' => $request->has('same_day_delivery')
                    ? filter_var($request->input('same_day_delivery'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    : null,
                'cutoff_times'      => array_values(array_filter(
                    (array) $request->input('cutoff_times', []),
                    fn ($c) => is_array($c) && filled($c['day'] ?? null) && filled($c['time'] ?? null)
                )),
            ];

            $validator = Validator::make($payload, [
                'product_types'       => 'required|array|min:1',
                'product_types.*'     => 'required|in:bouquet,flower,bouquet_and_flower',
                'price_min'           => 'required|numeric|min:0',
                'price_max'           => 'required|numeric|gte:price_min',
                'same_day_delivery'   => 'nullable|boolean',
                'cutoff_times'        => 'nullable|array',
                'cutoff_times.*.day'  => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'cutoff_times.*.time' => 'required|date_format:H:i',
            ], [
                'price_max.gte' => 'Maximum price must be greater than or equal to minimum price',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $vendorApplication->product_types     = $validated['product_types'];
            $vendorApplication->price_min         = $validated['price_min'];
            $vendorApplication->price_max         = $validated['price_max'];
            $vendorApplication->same_day_delivery = $validated['same_day_delivery'];
            $vendorApplication->cutoff_times      = $validated['cutoff_times'] ?? [];
            $vendorApplication->save();
            $vendorApplication->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Product details updated successfully',
                'data'    => $this->serializeVendorApplication($vendorApplication),
            ]);

        } catch (\Throwable $e) {
            Log::error('Error updating product details', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user'    => Auth::id(),
                'payload' => $request->all(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to update product details'], 500);
        }
    }

    public function updateDeliveryDetails(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json(['success' => false, 'message' => 'Vendor application not found'], 404);
            }

            $payload = [
                'delivery_handled_by' => trim((string) $request->input('delivery_handled_by', 'self')),
                'max_orders_per_day'  => $request->input('max_orders_per_day'),
                'lead_time'           => trim((string) $request->input('lead_time', '')),
                'cancellation_policy' => trim((string) $request->input('cancellation_policy', '')),
            ];

            $validator = Validator::make($payload, [
                'delivery_handled_by' => 'required|in:self',
                'max_orders_per_day'  => 'required|integer|min:1',
                'lead_time'           => 'required|string|max:255',
                'cancellation_policy' => 'required|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            $vendorApplication->delivery_handled_by = $this->normalizeDeliveryHandledByForStorage($validated['delivery_handled_by']);
            $vendorApplication->max_orders_per_day  = $validated['max_orders_per_day'];
            $vendorApplication->lead_time           = $validated['lead_time'];
            $vendorApplication->cancellation_policy = $validated['cancellation_policy'];
            $vendorApplication->save();
            $vendorApplication->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Delivery details updated successfully',
                'data'    => $this->serializeVendorApplication($vendorApplication),
            ]);

        } catch (\Throwable $e) {
            Log::error('Error updating delivery details', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user'    => Auth::id(),
                'payload' => $request->all(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to update delivery details'], 500);
        }
    }

    public function updateStoreLogo(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json(['success' => false, 'message' => 'Vendor application not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'store_logo' => 'required|image|mimes:jpeg,jpg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            if ($vendorApplication->store_logo_path) {
                CloudinaryHelper::destroy($vendorApplication->store_logo_path);
            }

            $result = CloudinaryHelper::upload($request->file('store_logo')->getRealPath(), [
                'folder'        => 'store_logos',
                'resource_type' => 'image',
            ]);

            $vendorApplication->update(['store_logo_path' => $result['public_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Store logo updated successfully',
                'data'    => $this->serializeVendorApplication($vendorApplication->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating store logo', ['error' => $e->getMessage(), 'user' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to update store logo'], 500);
        }
    }

    public function updateGeneralInfo(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json(['success' => false, 'message' => 'Vendor application not found'], 404);
            }

            $payload = [
                'store_name'        => trim((string) $request->input('store_name', '')),
                'store_description' => $request->filled('store_description') ? trim((string) $request->input('store_description')) : null,
                'store_address'     => trim((string) $request->input('store_address', '')),
                'service_areas'     => $request->filled('service_areas') ? trim((string) $request->input('service_areas')) : null,
                'operating_hours'   => $request->filled('operating_hours') ? trim((string) $request->input('operating_hours')) : null,
                'owner_name'        => trim((string) $request->input('owner_name', '')),
                'position'          => $request->filled('position') ? trim((string) $request->input('position')) : null,
                'contact_number'    => trim((string) $request->input('contact_number', '')),
                'facebook_page'     => $request->filled('facebook_page') ? trim((string) $request->input('facebook_page')) : null,
                'instagram_page'    => $request->filled('instagram_page') ? trim((string) $request->input('instagram_page')) : null,
            ];

            $validator = Validator::make($payload, [
                'store_name'        => 'sometimes|required|string|max:255',
                'store_description' => 'nullable|string|max:1000',
                'store_address'     => 'sometimes|required|string|max:500',
                'service_areas'     => 'nullable|string|max:500',
                'operating_hours'   => 'nullable|string|max:255',
                'owner_name'        => 'sometimes|required|string|max:255',
                'position'          => 'nullable|string|max:255',
                'contact_number'    => 'sometimes|required|string|max:20',
                'facebook_page'     => 'nullable|url|max:255',
                'instagram_page'    => 'nullable|url|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            foreach ($validator->validated() as $field => $value) {
                $vendorApplication->{$field} = $value;
            }

            $vendorApplication->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data'    => $this->serializeVendorApplication($vendorApplication->fresh()),
            ]);

        } catch (\Throwable $e) {
            Log::error('Error updating general info', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'user'    => Auth::id(),
                'payload' => $request->all(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to update profile'], 500);
        }
    }

    public function deleteStoreLogo(Request $request)
    {
        try {
            if (!$this->authenticatedVendorUser()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

            $vendorApplication = $this->findApprovedVendorApplication();

            if (!$vendorApplication) {
                return response()->json(['success' => false, 'message' => 'Vendor application not found'], 404);
            }

            if ($vendorApplication->store_logo_path) {
                CloudinaryHelper::destroy($vendorApplication->store_logo_path);
                $vendorApplication->update(['store_logo_path' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Store logo deleted successfully',
                'data'    => $this->serializeVendorApplication($vendorApplication->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting store logo', ['error' => $e->getMessage(), 'user' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete store logo'], 500);
        }
    }

    public function changePassword(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user instanceof User || !$user->isVendor()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Vendor only.',
                ], 403);
            }

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

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => ['current_password' => ['The current password you entered is incorrect.']],
                ], 422);
            }

            if (Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => ['password' => ['New password must be different from your current password.']],
                ], 422);
            }

            $user->password = Hash::make($request->password);

            if ($user->role === 'vendor' && isset($user->vendor_data['needs_password_change'])) {
                $vendorData                          = $user->vendor_data;
                $vendorData['needs_password_change'] = false;
                $user->vendor_data                   = $vendorData;
            }

            $user->save();

            Log::info('Vendor changed password', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Password updated successfully.']);

        } catch (\Exception $e) {
            Log::error('Error changing password', ['error' => $e->getMessage(), 'user' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to update password.'], 500);
        }
    }

    private function authenticatedVendorUser(): ?User
    {
        $user = Auth::user();

        if (!$user instanceof User || !$user->isVendor()) {
            return null;
        }

        return $user;
    }

    private function findApprovedVendorApplication(): ?VendorApplication
    {
        $user = $this->authenticatedVendorUser();

        if (!$user) {
            return null;
        }

        return $user->vendor()
            ->where('status', 'approved')
            ->first();
    }

    private function normalizeDeliveryHandledByForStorage(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === 'self') {
            return 'vendor';
        }

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeDeliveryHandledByForDisplay(?string $value): string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['self', 'vendor'], true)) {
            return 'self';
        }

        return $normalized !== '' ? $normalized : 'self';
    }

    private function serializeVendorApplication(VendorApplication $vendorApplication): array
    {
        return [
            'id'                         => $vendorApplication->id,
            'application_id'             => $vendorApplication->application_id,
            'store_name'                 => $this->safeVendorValue($vendorApplication, 'store_name', ''),
            'store_description'          => $this->safeVendorValue($vendorApplication, 'store_description', ''),
            'store_address'              => $this->safeVendorValue($vendorApplication, 'store_address', ''),
            'service_areas'              => $this->safeVendorValue($vendorApplication, 'service_areas', ''),
            'operating_hours'            => $this->safeVendorValue($vendorApplication, 'operating_hours', ''),
            'owner_name'                 => $this->safeVendorValue($vendorApplication, 'owner_name', ''),
            'position'                   => $this->safeVendorValue($vendorApplication, 'position', ''),
            'contact_number'             => $this->safeVendorValue($vendorApplication, 'contact_number', ''),
            'email'                      => $this->safeVendorValue($vendorApplication, 'email', ''),
            'facebook_page'              => $this->safeVendorValue($vendorApplication, 'facebook_page', ''),
            'instagram_page'             => $this->safeVendorValue($vendorApplication, 'instagram_page', ''),
            'payout_method'              => $this->safeVendorValue($vendorApplication, 'payout_method', ''),
            'account_holder_name'        => $this->safeVendorValue($vendorApplication, 'account_holder_name', ''),
            'decrypted_account_number'   => $this->safeVendorValue($vendorApplication, 'decrypted_account_number'),
            'bank_name'                  => $this->safeVendorValue($vendorApplication, 'bank_name', ''),
            'billing_address'            => $this->safeVendorValue($vendorApplication, 'billing_address', ''),
            'product_types'              => $this->safeVendorArray($vendorApplication, 'product_types'),
            'price_min'                  => $this->safeVendorValue($vendorApplication, 'price_min'),
            'price_max'                  => $this->safeVendorValue($vendorApplication, 'price_max'),
            'formatted_price_range'      => $this->safeVendorValue($vendorApplication, 'formatted_price_range'),
            'same_day_delivery'          => $this->safeVendorValue($vendorApplication, 'same_day_delivery'),
            'cutoff_times'               => $this->safeVendorArray($vendorApplication, 'cutoff_times'),
            'delivery_handled_by'        => $this->normalizeDeliveryHandledByForDisplay(
                (string) $this->safeVendorValue($vendorApplication, 'delivery_handled_by', 'self')
            ),
            'max_orders_per_day'         => $this->safeVendorValue($vendorApplication, 'max_orders_per_day'),
            'lead_time'                  => $this->safeVendorValue($vendorApplication, 'lead_time', ''),
            'cancellation_policy'        => $this->safeVendorValue($vendorApplication, 'cancellation_policy', ''),
            'store_logo_url'             => $this->safeVendorValue($vendorApplication, 'store_logo_url'),
            'profile_photo_url'          => $this->safeVendorValue($vendorApplication, 'profile_photo_url'),
            'portfolio_photos_urls'      => $this->safeVendorArray($vendorApplication, 'portfolio_photos_urls'),
            'payment_details_completed'  => (bool) $this->safeVendorValue($vendorApplication, 'payment_details_completed', false),
            'product_details_completed'  => (bool) $this->safeVendorValue($vendorApplication, 'product_details_completed', false),
            'delivery_details_completed' => (bool) $this->safeVendorValue($vendorApplication, 'delivery_details_completed', false),
            'profile_fully_completed'    => (bool) $this->safeVendorValue($vendorApplication, 'profile_fully_completed', false),
            'profile_completion_percentage' => (int) $this->safeVendorValue($vendorApplication, 'profile_completion_percentage', 0),
            'formatted_business_type'    => $this->safeVendorValue($vendorApplication, 'formatted_business_type'),
            'formatted_status'           => $this->safeVendorValue($vendorApplication, 'formatted_status'),
            'formatted_date'             => $this->safeVendorValue($vendorApplication, 'formatted_date'),
        ];
    }

    private function safeVendorValue(VendorApplication $vendorApplication, string $field, mixed $default = null): mixed
    {
        try {
            return $vendorApplication->{$field};
        } catch (\Throwable $e) {
            Log::warning('Vendor profile serialization failed for field', [
                'application_id' => $vendorApplication->application_id,
                'field'          => $field,
                'error'          => $e->getMessage(),
            ]);

            return $default;
        }
    }

    private function safeVendorArray(VendorApplication $vendorApplication, string $field): array
    {
        $value = $this->safeVendorValue($vendorApplication, $field, []);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
