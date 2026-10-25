<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\SubscriptionAccessService;
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
                'data'    => array_merge($this->serializeVendorApplication($vendorApplication), [
                    'subscription' => app(SubscriptionAccessService::class)->accessSummary($request->user()),
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching vendor profile', [
                'error' => $e->getMessage(),
                'user'  => Auth::id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch profile'], 500);
        }
    }

    /** GET /vendor/shop — what customers see about the shop, for the My Shop page. */
    public function getShop(Request $request)
    {
        $vendorApplication = $this->findApprovedVendorApplication();
        if (!$vendorApplication) {
            return response()->json(['success' => false, 'message' => 'Shop not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->serializeShop($vendorApplication)]);
    }

    /** PUT /vendor/shop — shop profile, location and weekly business hours. */
    public function updateShop(Request $request)
    {
        $vendorApplication = $this->findApprovedVendorApplication();
        if (!$vendorApplication) {
            return response()->json(['success' => false, 'message' => 'Shop not found'], 404);
        }

        $data = $request->validate([
            'store_name'          => 'required|string|max:255',
            'store_description'   => 'nullable|string|max:1000',
            'store_address'       => 'required|string|max:500',
            'store_street'        => 'nullable|string|max:255',
            'store_latitude'      => 'nullable|numeric|between:-90,90',
            'store_longitude'     => 'nullable|numeric|between:-180,180',
            'service_areas'       => ['nullable', 'string', 'max:500', VendorApplication::serviceAreaRule()],
            'contact_number'      => 'required|string|max:20',
            'facebook_page'       => 'nullable|url|max:255',
            'instagram_page'      => 'nullable|url|max:255',
            // Business hours now live in Shop settings; still accepted here when sent
            ...self::scheduleRules('sometimes'),
        ], self::SCHEDULE_MESSAGES);

        if (array_key_exists('operating_schedules', $data)) {
            $schedules = self::normalizeSchedules($data['operating_schedules']);
            $vendorApplication->fill([
                'operating_schedules' => $schedules,
                'operating_hours'     => self::describeSchedules($schedules),
            ]);
        }

        $vendorApplication->fill([
            'store_name'          => trim($data['store_name']),
            'store_description'   => isset($data['store_description']) ? trim($data['store_description']) : null,
            'store_address'       => trim($data['store_address']),
            'store_street'        => isset($data['store_street']) ? trim($data['store_street']) : null,
            'store_latitude'      => $data['store_latitude'] ?? $vendorApplication->store_latitude,
            'store_longitude'     => $data['store_longitude'] ?? $vendorApplication->store_longitude,
            'service_areas'       => isset($data['service_areas']) ? trim($data['service_areas']) : null,
            'contact_number'      => trim($data['contact_number']),
            'facebook_page'       => $data['facebook_page'] ?? null,
            'instagram_page'      => $data['instagram_page'] ?? null,
        ])->save();

        // The sidebar and storefront read the name from the user record too
        $user = $this->authenticatedVendorUser();
        if ($user) {
            $vendorData = is_array($user->vendor_data) ? $user->vendor_data : (json_decode((string) $user->vendor_data, true) ?: []);
            if (($vendorData['store_name'] ?? null) !== $vendorApplication->store_name) {
                $vendorData['store_name'] = $vendorApplication->store_name;
                $user->forceFill(['vendor_data' => $vendorData])->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Shop details saved.',
            'data'    => $this->serializeShop($vendorApplication->fresh()),
        ]);
    }

    public const WEEK_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public const SCHEDULE_MESSAGES = [
        'operating_schedules.required' => 'Open the shop on at least one day.',
        'operating_schedules.*.opening_time.regex' => 'Use a valid opening time.',
        'operating_schedules.*.closing_time.regex' => 'Use a valid closing time.',
    ];

    /** Validation for the weekly business hours ([{days, opening_time, closing_time}]). */
    public static function scheduleRules(string $presence = 'required'): array
    {
        return [
            'operating_schedules' => "{$presence}|array|min:1|max:7",
            'operating_schedules.*.days'         => 'required|array|min:1',
            'operating_schedules.*.days.*'       => ['string', Rule::in(self::WEEK_DAYS)],
            'operating_schedules.*.opening_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'operating_schedules.*.closing_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    /** Orders days Monday-first; one set of hours per day, closing after opening. */
    public static function normalizeSchedules(array $input): array
    {
        $schedules = [];
        $seen = [];
        foreach ($input as $schedule) {
            if ($schedule['opening_time'] >= $schedule['closing_time']) {
                throw ValidationException::withMessages(['operating_schedules' => ['Closing time must be after opening time.']]);
            }
            $days = array_values(array_filter(self::WEEK_DAYS, fn ($d) => in_array($d, $schedule['days'], true)));
            if (array_intersect($days, $seen)) {
                throw ValidationException::withMessages(['operating_schedules' => ['Each day can only have one set of hours.']]);
            }
            $seen = [...$seen, ...$days];
            $schedules[] = ['days' => $days, 'opening_time' => $schedule['opening_time'], 'closing_time' => $schedule['closing_time']];
        }

        return $schedules;
    }

    /** "Mon-Fri: 8:00 AM – 5:00 PM; Sat, Sun: 8:00 AM – 3:00 PM", as the registration form writes it. */
    public static function describeSchedules(array $schedules): string
    {
        $time = fn (string $t) => \Carbon\Carbon::createFromFormat('H:i', $t)->format('g:i A');
        $days = function (array $days) {
            if (count($days) === 7) {
                return 'Everyday';
            }
            $idx = array_map(fn ($d) => array_search($d, self::WEEK_DAYS, true), $days);
            $parts = [];
            $start = 0;
            for ($i = 1; $i <= count($idx); $i++) {
                if ($i === count($idx) || $idx[$i] !== $idx[$i - 1] + 1) {
                    $run = array_slice($idx, $start, $i - $start);
                    if (count($run) >= 3) {
                        $parts[] = substr(self::WEEK_DAYS[$run[0]], 0, 3) . '-' . substr(self::WEEK_DAYS[end($run)], 0, 3);
                    } else {
                        foreach ($run as $r) {
                            $parts[] = substr(self::WEEK_DAYS[$r], 0, 3);
                        }
                    }
                    $start = $i;
                }
            }

            return implode(', ', $parts);
        };

        return implode('; ', array_map(
            fn ($s) => $days($s['days']) . ': ' . $time($s['opening_time']) . ' – ' . $time($s['closing_time']),
            $schedules
        ));
    }

    private function serializeShop(VendorApplication $app): array
    {
        return [
            'store_name'          => $app->store_name ?? '',
            'store_description'   => $app->store_description ?? '',
            'store_address'       => $app->store_address ?? '',
            'store_street'        => $app->store_street ?? '',
            'store_latitude'      => $app->store_latitude !== null ? (float) $app->store_latitude : null,
            'store_longitude'     => $app->store_longitude !== null ? (float) $app->store_longitude : null,
            'service_areas'       => $app->service_areas ?? '',
            'contact_number'      => $app->contact_number ?? '',
            'email'               => $app->email ?? '',
            'facebook_page'       => $app->facebook_page ?? '',
            'instagram_page'      => $app->instagram_page ?? '',
            'operating_hours'     => $app->operating_hours ?? '',
            'operating_schedules' => $this->safeVendorArray($app, 'operating_schedules'),
            'store_logo_url'      => $this->safeVendorValue($app, 'store_logo_url'),
            'store_cover_url'     => $this->safeVendorValue($app, 'store_cover_url'),
            'business_type'       => $this->safeVendorValue($app, 'formatted_business_type'),
            'member_since'        => ($since = $app->reviewed_at ?? $app->created_at) ? \Carbon\Carbon::parse($since)->toIso8601String() : null,
        ];
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

    public function updateStoreCover(Request $request)
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
                'store_cover' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors'  => $validator->errors(),
                ], 422);
            }

            if ($vendorApplication->store_cover_path) {
                CloudinaryHelper::destroy($vendorApplication->store_cover_path);
            }

            $result = CloudinaryHelper::upload($request->file('store_cover')->getRealPath(), [
                'folder'        => 'store_covers',
                'resource_type' => 'image',
            ]);

            $vendorApplication->update(['store_cover_path' => $result['public_id']]);

            return response()->json([
                'success' => true,
                'message' => 'Cover photo updated successfully',
                'data'    => $this->serializeVendorApplication($vendorApplication->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating store cover', ['error' => $e->getMessage(), 'user' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to update cover photo'], 500);
        }
    }

    public function deleteStoreCover(Request $request)
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

            if ($vendorApplication->store_cover_path) {
                CloudinaryHelper::destroy($vendorApplication->store_cover_path);
                $vendorApplication->update(['store_cover_path' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Cover photo removed',
                'data'    => $this->serializeVendorApplication($vendorApplication->fresh()),
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting store cover', ['error' => $e->getMessage(), 'user' => Auth::id()]);
            return response()->json(['success' => false, 'message' => 'Failed to remove cover photo'], 500);
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
                // The same rule as signing up and resetting a password
                'password'              => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                'password_confirmation' => 'required|string',
            ], [
                'password.min'       => 'New password must be at least 8 characters.',
                'password.regex'     => 'Use at least one uppercase letter, one lowercase letter and a number.',
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
            $user->password_changed_at = now();

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
            'store_cover_url'            => $this->safeVendorValue($vendorApplication, 'store_cover_url'),
            'store_latitude'             => $this->safeVendorValue($vendorApplication, 'store_latitude'),
            'store_longitude'            => $this->safeVendorValue($vendorApplication, 'store_longitude'),
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
