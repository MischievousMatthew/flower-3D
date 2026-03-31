<?php

namespace App\Http\Controllers;

use App\Models\VendorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Helpers\CloudinaryHelper;

class VendorController extends Controller
{
    public function register(Request $request)
    {
        \Log::info('Vendor Registration - Request Data:', [
            'all_data'       => $request->except(['government_id', 'selfie_with_id', 'proof_of_address', 'portfolio_photos']),
            'files_received' => array_keys($request->allFiles()),
        ]);

        $validator = Validator::make($request->all(), [
            'store_name'                => 'required|string|max:255',
            'store_description'         => 'required|string',
            'business_type'             => 'required|in:individual,partnership,corporation',
            'store_address'             => 'required|string',
            'service_areas'             => 'required|string',
            'operating_hours'           => 'required|string',
            'owner_name'                => 'required|string|max:255',
            'position'                  => 'required|string|max:255',
            'contact_number'            => 'required|string|max:20',
            'email'                     => 'required|email|unique:vendor_applications,email',
            'government_id_number'      => 'required|string|max:255',
            'government_id'             => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'selfie_with_id'            => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'proof_of_address'          => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'dti_number'                => 'required|string|max:255',
            'sec_number'                => 'required|string|max:255',
            'barangay_clearance_number' => 'required|string|max:255',
            'barangay_clearance'        => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'mayor_permit_number'       => 'required|string|max:255',
            'mayor_permit'              => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'bir_tin'                   => 'required|string|max:255',
            'accept_terms'              => 'required|in:1,true,on,accepted',
            'accept_vendor_agreement'   => 'required|in:1,true,on,accepted',
            'accept_data_privacy'       => 'required|in:1,true,on,accepted',
            'confirm_accuracy'          => 'required|in:1,true,on,accepted',
            'store_logo'                => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'portfolio_photos'          => 'nullable|array',
            'portfolio_photos.*'        => 'file|mimes:jpg,jpeg,png|max:5120',
            'facebook_page'             => 'nullable|url',
            'instagram_page'            => 'nullable|url',
            'application_date'          => 'required|date',
        ], [
            'email.unique'                    => 'This email is already registered. Please use a different email.',
            'government_id_number.required'   => 'Government ID number is required.',
            'accept_terms.required'           => 'You must accept the Terms & Conditions.',
            'accept_vendor_agreement.required'=> 'You must accept the Vendor Agreement.',
            'accept_data_privacy.required'    => 'You must accept the Data Privacy Policy.',
            'confirm_accuracy.required'       => 'You must confirm the accuracy of your application.',
            'government_id.required'          => 'Government ID is required.',
            'selfie_with_id.required'         => 'Selfie with ID is required.',
            'proof_of_address.required'       => 'Proof of address is required.',
            'dti_number.required'             => 'DTI number is required.',
            'sec_number.required'             => 'SEC number is required.',
            'barangay_clearance_number.required' => 'Barangay Clearance Number is required.',
            'barangay_clearance.required'     => 'Barangay Clearance document is required.',
            'mayor_permit_number.required'    => 'Mayor\'s Permit Number is required.',
            'mayor_permit.required'           => 'Mayor\'s Permit document is required.',
            'bir_tin.required'                => 'BIR Registration / TIN is required.',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check your input.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $applicationId = 'VEN-' . strtoupper(Str::random(3)) . '-' . date('Ymd')
                           . str_pad(VendorApplication::count() + 1, 4, '0', STR_PAD_LEFT);

            $app = new VendorApplication();
            $app->application_id  = $applicationId;
            $app->store_name      = $request->store_name;
            $app->store_description = $request->store_description;
            $app->business_type   = $request->business_type;
            $app->store_address   = $request->store_address;
            $app->service_areas   = $request->service_areas;
            $app->operating_hours = $request->operating_hours;
            $app->owner_name      = $request->owner_name;
            $app->position        = $request->position;
            $app->contact_number  = $request->contact_number;
            $app->email           = $request->email;
            $app->government_id_number = $request->government_id_number;

            // ── Upload documents to Cloudinary (store full secure URL) ─────
            // These are permanent documents — we store the URL directly.

            if ($request->hasFile('government_id')) {
                $result = CloudinaryHelper::upload($request->file('government_id')->getRealPath(), [
                    'folder'        => 'vendor-applications/ids',
                    'resource_type' => 'auto',
                    'public_id'     => 'vendor-applications/ids/gov_id_' . $applicationId,
                ]);
                $app->government_id_path = $result['secure_url'];
            }

            if ($request->hasFile('selfie_with_id')) {
                $result = CloudinaryHelper::upload($request->file('selfie_with_id')->getRealPath(), [
                    'folder'        => 'vendor-applications/selfies',
                    'resource_type' => 'image',
                    'public_id'     => 'vendor-applications/selfies/selfie_' . $applicationId,
                ]);
                $app->selfie_with_id_path = $result['secure_url'];
            }

            if ($request->hasFile('proof_of_address')) {
                $result = CloudinaryHelper::upload($request->file('proof_of_address')->getRealPath(), [
                    'folder'        => 'vendor-applications/address-proof',
                    'resource_type' => 'auto',
                    'public_id'     => 'vendor-applications/address-proof/address_' . $applicationId,
                ]);
                $app->proof_of_address_path = $result['secure_url'];
            }

            $app->dti_number                = $request->dti_number;
            $app->sec_number                = $request->sec_number;
            $app->barangay_clearance_number = $request->barangay_clearance_number;
            $app->mayor_permit_number       = $request->mayor_permit_number;
            $app->bir_tin                   = $request->bir_tin;

            if ($request->hasFile('barangay_clearance')) {
                $result = CloudinaryHelper::upload($request->file('barangay_clearance')->getRealPath(), [
                    'folder'        => 'vendor-applications/barangay',
                    'resource_type' => 'auto',
                    'public_id'     => 'vendor-applications/barangay/barangay_' . $applicationId,
                ]);
                $app->barangay_clearance_path = $result['secure_url'];
            }

            if ($request->hasFile('mayor_permit')) {
                $result = CloudinaryHelper::upload($request->file('mayor_permit')->getRealPath(), [
                    'folder'        => 'vendor-applications/permits',
                    'resource_type' => 'auto',
                    'public_id'     => 'vendor-applications/permits/mayor_' . $applicationId,
                ]);
                $app->mayor_permit_path = $result['secure_url'];
            }

            // ── Store logo: store public_id so it can be replaced later ───
            if ($request->hasFile('store_logo')) {
                $result = CloudinaryHelper::upload($request->file('store_logo')->getRealPath(), [
                    'folder'        => 'store_logos',
                    'resource_type' => 'image',
                ]);
                $app->store_logo_path = $result['public_id'];
            }

            // ── Portfolio photos: store full secure URLs ───────────────────
            if ($request->hasFile('portfolio_photos')) {
                $portfolioUrls = [];
                foreach ($request->file('portfolio_photos') as $index => $photo) {
                    $result = CloudinaryHelper::upload($photo->getRealPath(), [
                        'folder'        => 'vendor-applications/portfolio',
                        'resource_type' => 'image',
                        'public_id'     => 'vendor-applications/portfolio/portfolio_' . $applicationId . '_' . $index,
                    ]);
                    $portfolioUrls[] = $result['secure_url'];
                }
                $app->portfolio_photos_paths = $portfolioUrls;
            }

            $app->facebook_page  = $request->facebook_page;
            $app->instagram_page = $request->instagram_page;

            $verificationLevel = 'basic';
            if ($request->filled('dti_number') || $request->filled('sec_number')
                || $request->filled('bir_tin') || $request->hasFile('barangay_clearance')
                || $request->hasFile('mayor_permit')
            ) {
                $verificationLevel = 'verified';
            }
            $app->verification_level = $verificationLevel;

            $app->status        = 'pending';
            $app->submitted_at  = now();
            $app->payment_details_completed  = false;
            $app->product_details_completed  = false;
            $app->delivery_details_completed = false;
            $app->profile_fully_completed    = false;
            $app->status_token  = Str::random(32);

            $app->save();

            \Log::info('Vendor application saved successfully:', [
                'application_id' => $applicationId,
                'email'          => $app->email,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Vendor application submitted successfully!',
                'data'    => [
                    'application_id'   => $app->application_id,
                    'email'            => $app->email,
                    'store_name'       => $app->store_name,
                    'status'           => $app->status,
                    'submitted_at'     => $app->submitted_at->format('Y-m-d H:i:s'),
                    'verification_level' => $app->verification_level,
                    'status_check_url' => url("/api/vendor/status/{$app->application_id}?token={$app->status_token}"),
                    'profile_completion' => [
                        'payment_details'  => false,
                        'product_details'  => false,
                        'delivery_details' => false,
                        'fully_completed'  => false,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Vendor registration error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your application.',
                'error'   => config('app.debug') ? $e->getMessage() : 'Please try again later or contact support.',
            ], 500);
        }
    }

    public function checkStatus(Request $request)
    {
        $email = $request->query('email');

        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email parameter is required.'], 400);
        }

        $application = VendorApplication::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$application) {
            return response()->json(['success' => false, 'message' => 'No application found with this email.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'application_id'     => $application->application_id,
                'status'             => $application->status,
                'submitted_at'       => $application->submitted_at->format('Y-m-d H:i:s'),
                'store_name'         => $application->store_name,
                'verification_level' => $application->verification_level,
                'profile_completion' => [
                    'payment_details'  => $application->payment_details_completed,
                    'product_details'  => $application->product_details_completed,
                    'delivery_details' => $application->delivery_details_completed,
                    'fully_completed'  => $application->profile_fully_completed,
                ],
            ],
        ]);
    }
}