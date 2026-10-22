<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeInfo;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Employment documents on an HR profile. The same endpoints serve the shop owner
 * (vendor portal) and HR staff (ERP), so both see every file whoever uploaded it.
 */
class EmployeeDocumentController extends Controller
{
    use ScopesOwner;

    /** GET /employees-info/{id}/documents */
    public function index(int $id): JsonResponse
    {
        $profile = EmployeeInfo::forOwner($this->getOwnerId())->findOrFail($id);
        $documents = EmployeeDocument::forOwner($this->getOwnerId())
            ->where('employee_info_id', $profile->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $documents->map(fn (EmployeeDocument $d) => $this->format($d))->values(),
            'categories' => EmployeeDocument::CATEGORIES,
        ]);
    }

    /** POST /employees-info/{id}/documents */
    public function store(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! EmployeeInfo::canBeManagedBy($user)) {
            return response()->json(['success' => false, 'message' => 'Only HR department staff can add documents.'], 403);
        }
        $ownerId = $this->getOwnerId();
        $profile = EmployeeInfo::forOwner($ownerId)->findOrFail($id);

        $data = $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
            'category' => ['required', Rule::in(EmployeeDocument::CATEGORIES)],
            'title' => 'nullable|string|max:150',
            'expires_on' => 'nullable|date',
        ], [
            'file.max' => 'Files can be up to 10 MB.',
            'file.mimes' => 'Upload a PDF, image or Word file.',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();

        try {
            // Word files must be "raw"; PDFs and images stay "image" so they open in the browser
            $upload = CloudinaryHelper::upload($file, [
                'folder' => "employee-documents/{$ownerId}",
                'type' => 'authenticated',
                'resource_type' => in_array($extension, ['doc', 'docx'], true) ? 'raw' : 'image',
            ]);
        } catch (\Throwable $e) {
            Log::error('Employee document upload failed', ['error' => $e->getMessage(), 'profile' => $profile->id]);

            return response()->json(['success' => false, 'message' => "Couldn't upload the file. Try again."], 502);
        }

        $document = EmployeeDocument::create([
            'owner_id' => $ownerId,
            'employee_info_id' => $profile->id,
            'category' => $data['category'],
            'title' => trim($data['title'] ?? '') ?: pathinfo($originalName, PATHINFO_FILENAME),
            'file_name' => $originalName,
            'public_id' => $upload['public_id'],
            'resource_type' => $upload['resource_type'] ?? 'image',
            'format' => $upload['format'] ?? $extension,
            'size' => $size,
            'expires_on' => $data['expires_on'] ?? null,
            'uploaded_by' => $user instanceof Employee ? $user->name : 'Shop owner',
        ]);

        return response()->json(['success' => true, 'message' => 'Document added.', 'data' => $this->format($document)], 201);
    }

    /** GET /employees-info/{id}/documents/{documentId}/link — a short-lived link to open the file. */
    public function link(int $id, int $documentId): JsonResponse
    {
        $document = $this->find($id, $documentId);

        try {
            $url = CloudinaryHelper::privateDownloadUrl($document->public_id, $document->format, $document->resource_type);
        } catch (\Throwable $e) {
            Log::error('Employee document link failed', ['error' => $e->getMessage(), 'document' => $document->id]);

            return response()->json(['success' => false, 'message' => "Couldn't open the file right now."], 502);
        }

        return response()->json(['success' => true, 'data' => ['url' => $url]]);
    }

    /** DELETE /employees-info/{id}/documents/{documentId} */
    public function destroy(Request $request, int $id, int $documentId): JsonResponse
    {
        if (! EmployeeInfo::canBeManagedBy($request->user())) {
            return response()->json(['success' => false, 'message' => 'Only HR department staff can remove documents.'], 403);
        }
        $document = $this->find($id, $documentId);
        $document->delete();
        CloudinaryHelper::destroy($document->public_id, ['type' => 'authenticated', 'resource_type' => $document->resource_type]);

        return response()->json(['success' => true, 'message' => 'Document removed.']);
    }

    private function find(int $profileId, int $documentId): EmployeeDocument
    {
        return EmployeeDocument::forOwner($this->getOwnerId())
            ->where('employee_info_id', $profileId)
            ->findOrFail($documentId);
    }

    private function format(EmployeeDocument $d): array
    {
        return [
            'id' => $d->id,
            'category' => $d->category,
            'title' => $d->title,
            'file_name' => $d->file_name,
            'format' => $d->format,
            'size' => $d->size,
            'expires_on' => $d->expires_on?->toDateString(),
            'uploaded_by' => $d->uploaded_by,
            'uploaded_at' => $d->created_at?->toIso8601String(),
        ];
    }
}
