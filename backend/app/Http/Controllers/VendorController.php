<?php

namespace App\Http\Controllers;

use App\Models\VendorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorController extends Controller
{
    public function register(Request $request)
    {
        // Log incoming request
        \Log::info('Vendor Registration - Request Data:', [
            'all_data' => $request->except(['government_id', 'selfie_with_id', 'proof_of_address', 'portfolio_photos']),
            'files_received' => array_keys($request->allFiles()),
        ]);
        
        // Validate the request - Updated validation rules
        $validator = Validator::make($request->all(), [
            // Step 1: Store Information
            'store_name' => 'required|string|max:255',
            'store_description' => 'required|string',
            'business_type' => 'required|in:individual,partnership,corporation',
            'store_address' => 'required|string',
            'service_areas' => 'required|string',
            'operating_hours' => 'required|string',
            
            // Step 2: Owner Information & Verification
            'owner_name' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'contact_number' => 'required|string|max:20',
            'email' => 'required|email|unique:vendor_applications,email',
            'government_id_number' => 'required|string|max:255',
            'government_id' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'selfie_with_id' => 'required|file|mimes:jpg,jpeg,png|max:5120',
            'proof_of_address' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            
            // Step 3: Business Registration (Optional with ID numbers)
            'dti_number' => 'nullable|string|max:255',
            'sec_number' => 'nullable|string|max:255',
            'barangay_clearance_number' => 'nullable|string|max:255',
            'barangay_clearance' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'mayor_permit_number' => 'nullable|string|max:255',
            'mayor_permit' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'bir_tin' => 'nullable|string|max:255',
            
            // Step 4: Legal - NO PAYMENT, PRODUCT, OR DELIVERY INFO
            'accept_terms' => 'required|in:1,true,on,accepted',
            'accept_vendor_agreement' => 'required|in:1,true,on,accepted',
            'accept_data_privacy' => 'required|in:1,true,on,accepted',
            'confirm_accuracy' => 'required|in:1,true,on,accepted',
            
            // Optional
            'store_logo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'portfolio_photos' => 'nullable|array',
            'portfolio_photos.*' => 'file|mimes:jpg,jpeg,png|max:5120',
            'facebook_page' => 'nullable|url',
            'instagram_page' => 'nullable|url',
            
            // Metadata
            'application_date' => 'required|date',
        ], [
            'email.unique' => 'This email is already registered. Please use a different email.',
            'government_id_number.required' => 'Government ID number is required.',
            'accept_terms.required' => 'You must accept the Terms & Conditions.',
            'accept_vendor_agreement.required' => 'You must accept the Vendor Agreement.',
            'accept_data_privacy.required' => 'You must accept the Data Privacy Policy.',
            'confirm_accuracy.required' => 'You must confirm the accuracy of your application.',
            'government_id.required' => 'Government ID is required.',
            'selfie_with_id.required' => 'Selfie with ID is required.',
            'proof_of_address.required' => 'Proof of address is required.',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please check your input.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            // Generate application ID
            $applicationId = 'VEN-' . strtoupper(Str::random(3)) . '-' . date('Ymd') . str_pad(VendorApplication::count() + 1, 4, '0', STR_PAD_LEFT);
            
            // Create vendor application
            $vendorApplication = new VendorApplication();
            $vendorApplication->application_id = $applicationId;
            
            // Store Information
            $vendorApplication->store_name = $request->store_name;
            $vendorApplication->store_description = $request->store_description;
            $vendorApplication->business_type = $request->business_type;
            $vendorApplication->store_address = $request->store_address;
            $vendorApplication->service_areas = $request->service_areas;
            $vendorApplication->operating_hours = $request->operating_hours;
            
            // Owner Information
            $vendorApplication->owner_name = $request->owner_name;
            $vendorApplication->position = $request->position;
            $vendorApplication->contact_number = $request->contact_number;
            $vendorApplication->email = $request->email;
            $vendorApplication->government_id_number = $request->government_id_number;
            
            // Handle file uploads
            if ($request->hasFile('government_id')) {
                $file = $request->file('government_id');
                $filename = 'gov_id_' . $applicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('vendor-applications/ids', $filename, 'public');
                $vendorApplication->government_id_path = $path;
            }
            
            if ($request->hasFile('selfie_with_id')) {
                $file = $request->file('selfie_with_id');
                $filename = 'selfie_' . $applicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('vendor-applications/selfies', $filename, 'public');
                $vendorApplication->selfie_with_id_path = $path;
            }
            
            if ($request->hasFile('proof_of_address')) {
                $file = $request->file('proof_of_address');
                $filename = 'address_' . $applicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('vendor-applications/address-proof', $filename, 'public');
                $vendorApplication->proof_of_address_path = $path;
            }
            
            // Business Registration (Optional)
            $vendorApplication->dti_number = $request->dti_number;
            $vendorApplication->sec_number = $request->sec_number;
            $vendorApplication->barangay_clearance_number = $request->barangay_clearance_number;
            $vendorApplication->mayor_permit_number = $request->mayor_permit_number;
            $vendorApplication->bir_tin = $request->bir_tin;
            
            if ($request->hasFile('barangay_clearance')) {
                $file = $request->file('barangay_clearance');
                $filename = 'barangay_' . $applicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $vendorApplication->barangay_clearance_path = $file->storeAs('vendor-applications/barangay', $filename, 'public');
            }
            
            if ($request->hasFile('mayor_permit')) {
                $file = $request->file('mayor_permit');
                $filename = 'mayor_' . $applicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $vendorApplication->mayor_permit_path = $file->storeAs('vendor-applications/permits', $filename, 'public');
            }
            
            // Payment, Product, and Delivery details are NULL - to be filled after login
            // No need to set these fields as they are nullable in the database
            
            // Optional Information
            if ($request->hasFile('store_logo')) {
                $file = $request->file('store_logo');
                $filename = 'logo_' . $applicationId . '_' . time() . '.' . $file->getClientOriginalExtension();
                $vendorApplication->store_logo_path = $file->storeAs('vendor-applications/logos', $filename, 'public');
            }
            
            // Handle multiple portfolio photos
            if ($request->hasFile('portfolio_photos')) {
                $portfolioPaths = [];
                foreach ($request->file('portfolio_photos') as $index => $photo) {
                    $filename = 'portfolio_' . $applicationId . '_' . $index . '_' . time() . '.' . $photo->getClientOriginalExtension();
                    $portfolioPaths[] = $photo->storeAs('vendor-applications/portfolio', $filename, 'public');
                }
                $vendorApplication->portfolio_photos_paths = json_encode($portfolioPaths);
            }
            
            $vendorApplication->facebook_page = $request->facebook_page;
            $vendorApplication->instagram_page = $request->instagram_page;
            
            // Calculate verification level
            $verificationLevel = 'basic';
            if ($request->filled('dti_number') || $request->filled('sec_number') || $request->filled('bir_tin') || 
                $request->hasFile('barangay_clearance') || $request->hasFile('mayor_permit')) {
                $verificationLevel = 'verified';
            }
            $vendorApplication->verification_level = $verificationLevel;
            
            // Set application status
            $vendorApplication->status = 'pending';
            $vendorApplication->submitted_at = now();
            
            // Profile completion tracking
            $vendorApplication->payment_details_completed = false;
            $vendorApplication->product_details_completed = false;
            $vendorApplication->delivery_details_completed = false;
            $vendorApplication->profile_fully_completed = false;
            
            // Generate status token
            $vendorApplication->status_token = Str::random(32);
            
            // Save
            $vendorApplication->save();
            
            \Log::info('Vendor application saved successfully:', [
                'application_id' => $applicationId,
                'email' => $vendorApplication->email
            ]);
            
            DB::commit();
            
            // TODO: Send notification emails
            // Mail::to(config('mail.admin_email'))->send(new NewVendorApplication($vendorApplication));
            // Mail::to($vendorApplication->email)->send(new VendorApplicationReceived($vendorApplication));
            
            return response()->json([
                'success' => true,
                'message' => 'Vendor application submitted successfully! You will need to complete your payment, product, and delivery information after your account is approved.',
                'data' => [
                    'application_id' => $vendorApplication->application_id,
                    'email' => $vendorApplication->email,
                    'store_name' => $vendorApplication->store_name,
                    'status' => $vendorApplication->status,
                    'submitted_at' => $vendorApplication->submitted_at->format('Y-m-d H:i:s'),
                    'verification_level' => $vendorApplication->verification_level,
                    'status_check_url' => url("/api/vendor/status/{$vendorApplication->application_id}?token={$vendorApplication->status_token}"),
                    'profile_completion' => [
                        'payment_details' => false,
                        'product_details' => false,
                        'delivery_details' => false,
                        'fully_completed' => false
                    ]
                ]
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Vendor registration error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing your application.',
                'error' => config('app.debug') ? $e->getMessage() : 'Please try again later or contact support.'
            ], 500);
        }
    }
    
    public function checkStatus(Request $request)
    {
        $email = $request->query('email');
        
        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'Email parameter is required.'
            ], 400);
        }
        
        $application = VendorApplication::where('email', $email)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'No application found with this email.'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'application_id' => $application->application_id,
                'status' => $application->status,
                'submitted_at' => $application->submitted_at->format('Y-m-d H:i:s'),
                'store_name' => $application->store_name,
                'verification_level' => $application->verification_level,
                'profile_completion' => [
                    'payment_details' => $application->payment_details_completed,
                    'product_details' => $application->product_details_completed,
                    'delivery_details' => $application->delivery_details_completed,
                    'fully_completed' => $application->profile_fully_completed
                ]
            ]
        ]);
    }
}