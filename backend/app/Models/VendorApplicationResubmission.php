<?php

namespace App\Models;

use App\Helpers\CloudinaryHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorApplicationResubmission extends Model
{
    use HasFactory;

    public const PENDING = 'pending_resubmission';
    public const RESUBMITTED = 'resubmitted';
    public const APPROVED = 'approved';

    protected $fillable = [
        'resubmission_request_id', 'vendor_application_id', 'field_name', 'rejection_reason',
        'original_value', 'resubmitted_value', 'status', 'requested_at', 'resubmitted_at',
        'approved_at', 'approved_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resubmitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(VendorApplicationResubmissionRequest::class, 'resubmission_request_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(VendorApplication::class, 'vendor_application_id');
    }

    /** Single source of truth for fields that can safely be corrected after initial submission. */
    public static function fieldDefinitions(): array
    {
        return [
            'government_id_path' => ['label' => 'Valid Government ID', 'type' => 'file', 'input' => 'government_id_path', 'mimes' => 'jpg,jpeg,png,pdf', 'max' => 5120, 'folder' => 'ids', 'resource_type' => 'auto'],
            'selfie_with_id_path' => ['label' => 'Selfie with Government ID', 'type' => 'file', 'input' => 'selfie_with_id_path', 'mimes' => 'jpg,jpeg,png', 'max' => 5120, 'folder' => 'selfies', 'resource_type' => 'image'],
            'proof_of_address_path' => ['label' => 'Proof of Address', 'type' => 'file', 'input' => 'proof_of_address_path', 'mimes' => 'jpg,jpeg,png,pdf', 'max' => 5120, 'folder' => 'address-proof', 'resource_type' => 'auto'],
            'barangay_clearance_path' => ['label' => 'Barangay Clearance', 'type' => 'file', 'input' => 'barangay_clearance_path', 'mimes' => 'jpg,jpeg,png,pdf', 'max' => 10240, 'folder' => 'barangay', 'resource_type' => 'auto'],
            'mayor_permit_path' => ['label' => "Mayor's / Business Permit", 'type' => 'file', 'input' => 'mayor_permit_path', 'mimes' => 'jpg,jpeg,png,pdf', 'max' => 10240, 'folder' => 'permits', 'resource_type' => 'auto'],
            'store_logo_path' => ['label' => 'Store Logo', 'type' => 'file', 'input' => 'store_logo_path', 'mimes' => 'jpg,jpeg,png', 'max' => 2048, 'folder' => 'store-logos', 'resource_type' => 'image', 'store_public_id' => true],
            'government_id_number' => ['label' => 'Government ID Number', 'type' => 'text', 'input' => 'government_id_number', 'max' => 255],
            'dti_number' => ['label' => 'DTI Registration Number', 'type' => 'text', 'input' => 'dti_number', 'max' => 255],
            'sec_number' => ['label' => 'SEC Registration Number', 'type' => 'text', 'input' => 'sec_number', 'max' => 255],
            'barangay_clearance_number' => ['label' => 'Barangay Clearance Number', 'type' => 'text', 'input' => 'barangay_clearance_number', 'max' => 255],
            'mayor_permit_number' => ['label' => "Mayor's Permit Number", 'type' => 'text', 'input' => 'mayor_permit_number', 'max' => 255],
            'bir_tin' => ['label' => 'BIR Registration / TIN', 'type' => 'text', 'input' => 'bir_tin', 'max' => 255],
            'contact_number' => ['label' => 'Valid Contact Number', 'type' => 'text', 'input' => 'contact_number', 'max' => 20],
            'store_description' => ['label' => 'Store Description', 'type' => 'textarea', 'input' => 'store_description', 'max' => 5000],
        ];
    }

    public static function definition(string $field): ?array
    {
        return static::fieldDefinitions()[$field] ?? null;
    }

    public function toPayload(): array
    {
        $definition = static::definition($this->field_name) ?? [];
        $isFile = ($definition['type'] ?? null) === 'file';

        return [
            'id' => $this->id,
            'field_name' => $this->field_name,
            'label' => $definition['label'] ?? $this->field_name,
            'type' => $definition['type'] ?? 'text',
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'original_value' => $this->original_value,
            'resubmitted_value' => $this->resubmitted_value,
            'original_url' => $isFile ? $this->fileUrl($this->original_value, $definition) : null,
            'resubmitted_url' => $isFile ? $this->fileUrl($this->resubmitted_value, $definition) : null,
            'requested_at' => $this->requested_at,
            'resubmitted_at' => $this->resubmitted_at,
            'approved_at' => $this->approved_at,
        ];
    }

    private function fileUrl(?string $value, array $definition): ?string
    {
        if (!$value) return null;
        if (str_starts_with($value, 'http')) return $value;
        return !empty($definition['store_public_id']) ? CloudinaryHelper::getUrl($value) : $value;
    }
}
