<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VendorApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use App\Mail\VendorAccountCreated;
use App\Mail\VendorApplicationRejected;
use Illuminate\Support\Str;
use App\Helpers\CloudinaryHelper;
use Illuminate\Database\QueryException;

class VendorApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            Log::info('Fetching vendor applications', ['params' => $request->all()]);
            
            $perPage = $request->get('per_page', 10);
            
            // Start query
            $query = VendorApplication::query();
            
            // Filter by status
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            // Search
            if ($request->has('search') && $request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('store_name', 'like', '%' . $request->search . '%')
                      ->orWhere('owner_name', 'like', '%' . $request->search . '%')
                      ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }
            
            // Filter by business type
            if ($request->has('business_type') && $request->business_type) {
                $query->where('business_type', $request->business_type);
            }
            
            // Filter by date range
            if ($request->has('start_date') && $request->start_date) {
                $query->whereDate('submitted_at', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && $request->end_date) {
                $query->whereDate('submitted_at', '<=', $request->end_date);
            }
            
            // Order by submitted date (newest first)
            $query->orderBy('submitted_at', 'desc');
            
            // Paginate results - will automatically use the model's appends
            $applications = $query->paginate($perPage);
            
            Log::info('Applications fetched successfully', ['count' => $applications->count()]);
            
            return response()->json([
                'data' => $applications->items(),
                'meta' => [
                    'current_page' => $applications->currentPage(),
                    'last_page' => $applications->lastPage(),
                    'from' => $applications->firstItem(),
                    'to' => $applications->lastItem(),
                    'total' => $applications->total(),
                    'per_page' => $applications->perPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching vendor applications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to fetch applications',
                'message' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Get statistics
     */
    public function statistics()
    {
        try {
            Log::info('Fetching vendor application statistics');
            
            $total = VendorApplication::count();
            $pending = VendorApplication::where('status', 'pending')->count();
            $approved = VendorApplication::where('status', 'approved')->count();
            $rejected = VendorApplication::where('status', 'rejected')->count();
            $underReview = VendorApplication::where('status', 'under_review')->count();

            return response()->json([
                'total' => $total,
                'pending' => $pending,
                'approved' => $approved,
                'rejected' => $rejected,
                'under_review' => $underReview,
                'recent_7_days' => VendorApplication::where('created_at', '>=', now()->subDays(7))->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching statistics', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'total' => 0,
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'under_review' => 0,
                'recent_7_days' => 0
            ]);
        }
    }

    /**
     * Update application status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            Log::info('Updating application status', ['id' => $id, 'status' => $request->status]);
            
            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected,under_review',
                'rejection_reason' => 'nullable|string|max:1000',
            ]);

            $application = VendorApplication::findOrFail($id);
            
            $updateData = [
                'status' => $validated['status'],
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id() ?? 1,
            ];

            if (isset($validated['rejection_reason'])) {
                $updateData['rejection_reason'] = $validated['rejection_reason'];
            }

            if ($validated['status'] === 'approved') {
                $updateData['verification_level'] = 'verified';
                
                // Create user account for the vendor
                $this->createVendorAccount($application);
            }

            $application->update($updateData);

            if ($validated['status'] === 'rejected' && !empty($validated['rejection_reason'])) {
                $this->sendVendorRejectionEmail($application->fresh(), $validated['rejection_reason']);
            }

            return response()->json([
                'message' => 'Application status updated successfully',
                'application' => $application
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating application status', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to update status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a user account for the approved vendor
     */
    private function createVendorAccount(VendorApplication $application): User
    {
        try {
            $result = DB::transaction(function () use ($application) {
                $existingUser = User::where('email', $application->email)->lockForUpdate()->first();

                if ($existingUser) {
                    $this->syncVendorUser($existingUser, $application);

                    return ['user' => $existingUser->fresh(), 'password' => null, 'existing' => true];
                }

                $username = $this->generateUniqueUsername($application->email);
                [$name, $surname] = $this->splitOwnerName($application->owner_name);
                $temporaryPassword = Str::random(12);

                $user = User::create([
                    'name'              => $name,
                    'surname'           => $surname,
                    'username'          => $username,
                    'email'             => $application->email,
                    'contact_number'    => $application->contact_number,
                    'password'          => Hash::make($temporaryPassword),
                    'is_verified'       => true,
                    'email_verified_at' => now(),
                    'role'              => 'vendor',
                    'vendor_data'       => [
                        'store_name'            => $application->store_name,
                        'application_id'        => $application->id,
                        'approved_at'           => now()->toDateTimeString(),
                        'needs_password_change' => true,
                    ],
                ]);

                return ['user' => $user, 'password' => $temporaryPassword, 'existing' => false];
            });

            $this->sendVendorWelcomeEmail($application, $result['password'], $result['existing']);

            return $result['user'];
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                Log::error('Error creating vendor account', [
                    'application_id' => $application->id,
                    'error'          => $e->getMessage(),
                ]);
                throw $e;
            }

            $existingUser = User::where('email', $application->email)->first();

            if (! $existingUser) {
                Log::error('Duplicate vendor account conflict could not be recovered', [
                    'application_id' => $application->id,
                    'email'          => $application->email,
                    'error'          => $e->getMessage(),
                ]);
                throw $e;
            }

            $this->syncVendorUser($existingUser, $application);
            $this->sendVendorWelcomeEmail($application, null, true);

            return $existingUser->fresh();
        } catch (\Exception $e) {
            Log::error('Error creating vendor account', [
                'application_id' => $application->id,
                'error'          => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function syncVendorUser(User $user, VendorApplication $application): void
    {
        $user->forceFill([
            'role'              => 'vendor',
            'contact_number'    => $user->contact_number ?: $application->contact_number,
            'is_verified'       => true,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'vendor_data'       => array_merge($user->vendor_data ?? [], [
                'store_name'            => $application->store_name,
                'application_id'        => $application->id,
                'approved_at'           => now()->toDateTimeString(),
                'needs_password_change' => true,
            ]),
        ])->save();
    }

    private function generateUniqueUsername(string $email): string
    {
        $baseUsername = explode('@', $email)[0];
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter++;
        }

        return $username;
    }

    private function splitOwnerName(string $ownerName): array
    {
        $nameParts = explode(' ', trim($ownerName));

        if (count($nameParts) >= 2) {
            $surname = array_pop($nameParts);
            $name = implode(' ', $nameParts);
            return [$name, $surname];
        }

        return [$ownerName, ''];
    }

    /**
     * Send welcome email to vendor (in background, don't block approval)
     */
        private function sendVendorWelcomeEmail(
            VendorApplication $application,
            ?string $password = null,
            bool $isExistingUser = false
        ): void {
            try {
                (new VendorAccountCreated($application, $password, $isExistingUser))->send();

                Log::info('Vendor welcome email sent', [
                    'application_id' => $application->id,
                    'email'          => $application->email,
                ]);
            } catch (\Exception $e) {
                Log::warning('Vendor welcome email failed (account still created)', [
                    'application_id' => $application->id,
                    'email'          => $application->email,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

    /**
     * Show individual application
     */
    public function show($id)
    {
        try {
            $application = VendorApplication::findOrFail($id);
            
            return response()->json($application);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Application not found'
            ], 404);
        }
    }

    /**
     * Approve a vendor application (dedicated endpoint)
     */
    public function approve(Request $request, $id)
    {
        try {
            $application = VendorApplication::findOrFail($id);

            if ($application->status === 'approved') {
                return response()->json([
                    'message' => 'Application is already approved.',
                    'application' => $application,
                ]);
            }

            if ($application->status !== 'pending') {
                return response()->json([
                    'message' => 'Application is not in pending status.'
                ], 422);
            }

            $this->createVendorAccount($application);

            $application->update([
                'status'             => 'approved',
                'verification_level' => 'verified',
                'reviewed_at'        => now(),
                'reviewed_by'        => auth()->id(),
            ]);

            return response()->json([
                'message'     => 'Vendor application approved successfully!',
                'application' => $application->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error approving application', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to approve application',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a vendor application (dedicated endpoint)
     */
    public function reject(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'rejection_reason' => 'required|string|min:10|max:1000',
            ]);

            $application = VendorApplication::findOrFail($id);

            if ($application->status !== 'pending') {
                return response()->json([
                    'message' => 'Application is not in pending status.'
                ], 422);
            }

            $application->update([
                'status'           => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
                'reviewed_at'      => now(),
                'reviewed_by'      => auth()->id(),
            ]);

            $this->sendVendorRejectionEmail($application->fresh(), $validated['rejection_reason']);

            return response()->json([
                'message'     => 'Vendor application rejected successfully!',
                'application' => $application->fresh(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error rejecting application', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error'   => 'Failed to reject application',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send rejection email to vendor applicant (in background, don't block rejection)
     */
    private function sendVendorRejectionEmail(VendorApplication $application, string $rejectionReason): void
    {
        try {
            (new VendorApplicationRejected($application, $rejectionReason))->send();

            Log::info('Vendor rejection email sent', [
                'application_id' => $application->id,
                'email'          => $application->email,
            ]);
        } catch (\Exception $e) {
            Log::warning('Vendor rejection email failed (application still rejected)', [
                'application_id' => $application->id,
                'email'          => $application->email,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * Export data
     */
    public function export(Request $request)
    {
        try {
            $query = VendorApplication::query();
            
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            $applications = $query->get();
            
            $csvData = "Application ID,Store Name,Owner Name,Email,Status,Submitted At\n";
            
            foreach ($applications as $app) {
                $csvData .= "{$app->id},{$app->store_name},{$app->owner_name},{$app->email},{$app->status},{$app->submitted_at}\n";
            }
            
            $filename = 'vendor-applications-' . date('Y-m-d') . '.csv';
            
            return response($csvData, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to export data'
            ], 500);
        }
    }

    /**
     * Test endpoint
     */
    public function test()
    {
        return response()->json(['message' => 'API is working']);
    }

    /**
     * Serve uploaded files
     */
    public function serveFile($path)
    {
        try {
            // Redirect to Cloudinary URL for documents (usually 'raw' type)
            $url = CloudinaryHelper::getUrl($path, 'raw');
            return redirect($url);
        } catch (\Exception $e) {
            Log::error('Error serving file', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            abort(404, 'File not found');
        }
    }
}
