<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'store_name' => $this->store_name,
            'store_description' => $this->store_description,
            'business_type' => $this->business_type,
            'formatted_business_type' => $this->formatted_business_type,
            'store_address' => $this->store_address,
            'service_areas' => $this->service_areas,
            'operating_hours' => $this->operating_hours,
            'owner_name' => $this->owner_name,
            'position' => $this->position,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'government_id_url' => $this->government_id_url,
            'selfie_with_id_url' => $this->selfie_with_id_url,
            'proof_of_address_url' => $this->proof_of_address_url,
            'dti_number' => $this->dti_number,
            'sec_number' => $this->sec_number,
            'barangay_clearance_url' => $this->barangay_clearance_url,
            'mayor_permit_url' => $this->mayor_permit_url,
            'bir_tin' => $this->bir_tin,
            'payout_method' => $this->payout_method,
            'account_holder_name' => $this->account_holder_name,
            'decrypted_account_number' => $this->decrypted_account_number,
            'bank_name' => $this->bank_name,
            'billing_address' => $this->billing_address,
            'product_types' => $this->product_types,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
            'formatted_price_range' => $this->formatted_price_range,
            'same_day_delivery' => $this->same_day_delivery,
            'cutoff_times' => $this->cutoff_times,
            'delivery_handled_by' => $this->delivery_handled_by,
            'max_orders_per_day' => $this->max_orders_per_day,
            'lead_time' => $this->lead_time,
            'cancellation_policy' => $this->cancellation_policy,
            'store_logo_url' => $this->store_logo_url,
            'portfolio_photos_urls' => $this->portfolio_photos_urls,
            'facebook_page' => $this->facebook_page,
            'instagram_page' => $this->instagram_page,
            'status' => $this->status,
            'formatted_status' => $this->formatted_status,
            'verification_level' => $this->verification_level,
            'admin_notes' => $this->admin_notes,
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->submitted_at,
            'formatted_date' => $this->formatted_date,
            'reviewed_at' => $this->reviewed_at,
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $this->reviewer->name,
                'email' => $this->reviewer->email
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}