<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Mail\VendorApplicationResubmissionRequested;
use App\Mail\VendorApplicationResubmitted;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorApplicationResubmission;
use App\Models\VendorApplicationResubmissionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VendorApplicationResubmissionController extends Controller
{
    public function availableFields()
    {
        return response()->json([
            'fields' => collect(VendorApplicationResubmission::fieldDefinitions())
                ->map(fn (array $field, string $key) => [
                    'field_name' => $key,
                    'label' => $field['label'],
                    'type' => $field['type'],
                ])->values(),
        ]);
    }

    public function indexForApplication($applicationId)
    {
        $application = VendorApplication::findOrFail($applicationId);
        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Only pending vendor applications can receive a resubmission request.'], 422);
        }

        $items = $application->resubmissions()
            ->with('request')
            ->latest('requested_at')
            ->get()
            ->map(fn (VendorApplicationResubmission $item) => array_merge($item->toPayload(), [
                'request_id' => $item->resubmission_request_id,
            ]));

        return response()->json(['items' => $items]);
    }

    public function requestResubmission(Request $request, $applicationId)
    {
        $validated = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.field_name' => ['required', 'string'],
            'fields.*.rejection_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $application = VendorApplication::findOrFail($applicationId);
        $definitions = VendorApplicationResubmission::fieldDefinitions();
        $selected = collect($validated['fields'])->keyBy('field_name');

        if ($selected->count() !== count($validated['fields']) || $selected->keys()->contains(fn ($key) => !isset($definitions[$key]))) {
            return response()->json(['message' => 'One or more selected fields cannot be resubmitted.'], 422);
        }

        if ($application->hasOutstandingResubmissions()) {
            return response()->json(['message' => 'This application already has requirements awaiting resubmission or review.'], 422);
        }

        $token = Str::random(64);
        $resubmissionRequest = DB::transaction(function () use ($application, $selected, $token) {
            $request = $application->resubmissionRequests()->create([
                'token_hash' => hash('sha256', $token),
                'status' => 'requested',
                'expires_at' => now()->addDays(30),
                'requested_by' => auth()->id(),
            ]);

            foreach ($selected as $fieldName => $selectedField) {
                $request->items()->create([
                    'vendor_application_id' => $application->id,
                    'field_name' => $fieldName,
                    'rejection_reason' => $selectedField['rejection_reason'] ?? null,
                    'original_value' => $application->getRawOriginal($fieldName) ?? $application->{$fieldName},
                    'status' => VendorApplicationResubmission::PENDING,
                    'requested_at' => now(),
                ]);
            }

            $application->update(['resubmission_status' => 'needs_resubmission']);
            return $request;
        });

        $this->sendRequestEmail($application, $token);

        return response()->json([
            'message' => 'Resubmission request sent to the vendor.',
            'request_id' => $resubmissionRequest->id,
        ], 201);
    }

    public function showForVendor(string $token)
    {
        $request = $this->findTokenRequest($token);
        if (!$request) {
            return response()->json(['message' => 'This resubmission link is invalid or has expired.'], 404);
        }

        $items = $request->items()
            ->whereIn('status', [VendorApplicationResubmission::PENDING, VendorApplicationResubmission::RESUBMITTED])
            ->get()
            ->map(fn (VendorApplicationResubmission $item) => $item->toPayload());

        return response()->json([
            'vendor_name' => $request->application->owner_name ?: $request->application->store_name,
            'expires_at' => $request->expires_at,
            'items' => $items,
        ]);
    }

    public function submitForVendor(Request $request, string $token)
    {
        $resubmissionRequest = $this->findTokenRequest($token);
        if (!$resubmissionRequest) {
            return response()->json(['message' => 'This resubmission link is invalid or has expired.'], 404);
        }

        $pendingItems = $resubmissionRequest->items()
            ->where('status', VendorApplicationResubmission::PENDING)
            ->get();

        if ($pendingItems->isEmpty()) {
            return response()->json(['message' => 'There are no requirements awaiting resubmission.'], 422);
        }

        $rules = [];
        foreach ($pendingItems as $item) {
            $definition = VendorApplicationResubmission::definition($item->field_name);
            if (($definition['type'] ?? null) === 'file') {
                $rules["fields.{$item->field_name}"] = ['required', 'file', 'mimes:' . $definition['mimes'], 'max:' . $definition['max']];
            } else {
                $rules["fields.{$item->field_name}"] = ['required', 'string', 'max:' . $definition['max']];
            }
        }
        $request->validate($rules);

        foreach ($pendingItems as $item) {
            $definition = VendorApplicationResubmission::definition($item->field_name);
            $newValue = $request->input("fields.{$item->field_name}");

            if (($definition['type'] ?? null) === 'file') {
                $file = $request->file("fields.{$item->field_name}");
                $upload = CloudinaryHelper::upload($file, [
                    'folder' => 'vendor-applications/resubmissions/' . $resubmissionRequest->application->application_id . '/' . $definition['folder'],
                    'resource_type' => $definition['resource_type'],
                    'public_id' => 'resubmission_' . $resubmissionRequest->id . '_' . $item->field_name . '_' . Str::random(8),
                ]);
                $newValue = !empty($definition['store_public_id']) ? $upload['public_id'] : $upload['secure_url'];
            }

            $item->update([
                'resubmitted_value' => $newValue,
                'status' => VendorApplicationResubmission::RESUBMITTED,
                'resubmitted_at' => now(),
            ]);
        }

        $resubmissionRequest->update(['status' => 'submitted', 'submitted_at' => now()]);
        $resubmissionRequest->application->update(['resubmission_status' => 'pending_review']);
        $this->notifyAdministrators($resubmissionRequest->fresh('application'));

        return response()->json(['message' => 'Your updated requirements have been submitted for review.']);
    }

    public function approveItem($itemId)
    {
        $item = VendorApplicationResubmission::with('application')->findOrFail($itemId);
        if ($item->status !== VendorApplicationResubmission::RESUBMITTED) {
            return response()->json(['message' => 'Only resubmitted requirements can be approved.'], 422);
        }

        DB::transaction(function () use ($item) {
            // The original submission remains archived in original_value; only the approved correction is applied.
            $item->application->setAttribute($item->field_name, $item->resubmitted_value);
            $item->application->save();
            $item->update([
                'status' => VendorApplicationResubmission::APPROVED,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]);

            if (!$item->application->hasOutstandingResubmissions()) {
                $item->application->update(['resubmission_status' => 'completed']);
            }
        });

        return response()->json(['message' => 'Resubmitted requirement approved.', 'item' => $item->fresh()->toPayload()]);
    }

    public function requestAgain(Request $request, $itemId)
    {
        $validated = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:2000']]);
        $item = VendorApplicationResubmission::with(['request', 'application'])->findOrFail($itemId);

        if ($item->status !== VendorApplicationResubmission::RESUBMITTED) {
            return response()->json(['message' => 'Only resubmitted requirements can be requested again.'], 422);
        }

        $token = Str::random(64);
        $item->update([
            'status' => VendorApplicationResubmission::PENDING,
            'rejection_reason' => $validated['rejection_reason'] ?? $item->rejection_reason,
            'requested_at' => now(),
            'resubmitted_at' => null,
        ]);
        $item->request->update([
            'status' => 'requested',
            'submitted_at' => null,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
        ]);
        $item->application->update(['resubmission_status' => 'needs_resubmission']);
        $this->sendRequestEmail($item->application, $token);

        return response()->json(['message' => 'The requirement is ready for the vendor to submit again.']);
    }

    private function findTokenRequest(string $token): ?VendorApplicationResubmissionRequest
    {
        return VendorApplicationResubmissionRequest::with('application')
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    private function sendRequestEmail(VendorApplication $application, string $token): void
    {
        try {
            $url = rtrim(config('app.frontend_url'), '/')
                . config('frontend_routes.vendor_resubmission_path')
                . '/' . $token;
            (new VendorApplicationResubmissionRequested($application, $url))->send();
        } catch (\Throwable $e) {
            Log::warning('Vendor resubmission email failed; request remains active.', ['application_id' => $application->id, 'error' => $e->getMessage()]);
        }
    }

    private function notifyAdministrators(VendorApplicationResubmissionRequest $request): void
    {
        foreach (User::where('role', 'admin')->whereNotNull('email')->pluck('email') as $email) {
            try {
                (new VendorApplicationResubmitted($request->application))->send($email);
            } catch (\Throwable $e) {
                Log::warning('Admin resubmission notification failed.', ['email' => $email, 'error' => $e->getMessage()]);
            }
        }
    }
}
