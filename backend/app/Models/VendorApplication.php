<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\CloudinaryHelper;

class VendorApplication extends Model
{
    use HasFactory;

    public const RESERVATION_TIMEZONE = 'Asia/Manila';

    protected $fillable = [
        'application_id', 'status_token', 'store_name', 'store_description',
        'business_type', 'store_address', 'service_areas', 'operating_hours',
        'owner_name', 'position', 'contact_number', 'email',
        'government_id_number', 'government_id_path', 'selfie_with_id_path',
        'proof_of_address_path', 'dti_number', 'sec_number',
        'barangay_clearance_number', 'barangay_clearance_path',
        'mayor_permit_number', 'mayor_permit_path', 'bir_tin',
        'payout_method', 'account_holder_name', 'account_number',
        'bank_name', 'billing_address', 'product_types', 'price_min',
        'price_max', 'same_day_delivery', 'cutoff_times',
        'delivery_handled_by', 'max_orders_per_day', 'lead_time',
        'cancellation_policy', 'store_logo_path', 'portfolio_photos_paths',
        'facebook_page', 'instagram_page', 'status', 'verification_level',
        'admin_notes', 'rejection_reason', 'submitted_at', 'reviewed_at',
        'reviewed_by', 'payment_details_completed', 'product_details_completed',
        'delivery_details_completed', 'profile_fully_completed',
    ];

    protected $casts = [
        'product_types'               => 'array',
        'cutoff_times'                => 'array',
        'portfolio_photos_paths'      => 'array',
        'same_day_delivery'           => 'boolean',
        'max_orders_per_day'          => 'integer',
        'price_min'                   => 'decimal:2',
        'price_max'                   => 'decimal:2',
        'submitted_at'                => 'datetime',
        'reviewed_at'                 => 'datetime',
        'payment_details_completed'   => 'boolean',
        'product_details_completed'   => 'boolean',
        'delivery_details_completed'  => 'boolean',
        'profile_fully_completed'     => 'boolean',
    ];

    protected $appends = [
        'government_id_url',
        'selfie_with_id_url',
        'proof_of_address_url',
        'barangay_clearance_url',
        'mayor_permit_url',
        'store_logo_url',
        'profile_photo_url',
        'portfolio_photos_urls',
        'formatted_business_type',
        'formatted_status',
        'formatted_date',
        'formatted_price_range',
        'decrypted_account_number',
        'profile_completion_percentage',
    ];

    protected $hidden = [
        'account_number',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->application_id ??=
                'VEN-' . strtoupper(Str::random(3)) . '-' .
                now()->format('Ymd') .
                str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);

            $model->status_token ??= Str::random(32);
            $model->submitted_at ??= now();
        });

        static::saving(function ($model) {
            $model->updateProfileCompletion();
        });
    }

    public function setAccountNumberAttribute($value)
    {
        if (!$value) {
            $this->attributes['account_number'] = null;
            return;
        }

        try {
            Crypt::decryptString($value);
            $this->attributes['account_number'] = $value;
        } catch (\Exception $e) {
            $this->attributes['account_number'] = Crypt::encryptString((string) $value);
        }
    }

    protected function decryptedAccountNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                $value = $this->attributes['account_number'] ?? null;
                if (!$value) return null;

                try {
                    $decrypted = Crypt::decryptString($value);
                    if (is_string($decrypted) && preg_match('/^s:\d+:"(.+)";$/', $decrypted, $matches)) {
                        return $matches[1];
                    }
                    return $decrypted;
                } catch (\Exception $e) {
                    \Log::error('Failed to decrypt account number', [
                        'application_id' => $this->application_id,
                        'error'          => $e->getMessage(),
                    ]);
                    return null;
                }
            }
        );
    }

    public function updateProfileCompletion()
    {
        $deliveryHandledBy = strtolower(trim((string) $this->delivery_handled_by));
        $isVendorManagedDelivery = in_array($deliveryHandledBy, ['self', 'vendor'], true);

        $this->payment_details_completed = !empty($this->payout_method)
            && !empty($this->account_holder_name)
            && !empty($this->account_number)
            && !empty($this->bank_name)
            && !empty($this->billing_address);

        $this->product_details_completed = !empty($this->product_types)
            && !empty($this->price_min)
            && !empty($this->price_max)
            && !is_null($this->same_day_delivery);

        $this->delivery_details_completed = $isVendorManagedDelivery
            && !empty($this->max_orders_per_day)
            && !empty($this->lead_time)
            && !empty($this->cancellation_policy);

        $this->profile_fully_completed = $this->payment_details_completed
            && $this->product_details_completed
            && $this->delivery_details_completed;
    }

    protected function profileCompletionPercentage(): Attribute
    {
        return Attribute::make(
            get: function () {
                $completed = 0;
                if ($this->payment_details_completed)  $completed++;
                if ($this->product_details_completed)  $completed++;
                if ($this->delivery_details_completed) $completed++;
                return round(($completed / 3) * 100);
            }
        );
    }

    /* Relationships */
    public function notes(): HasMany    { return $this->hasMany(VendorApplicationNote::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }

    // ─────────────────────────────────────────────────────────────────────
    // Document URLs
    // These fields store the full Cloudinary secure URL directly,
    // so we return them as-is from the accessor.
    // ─────────────────────────────────────────────────────────────────────

    protected function resolvePathUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        return secure_asset('storage/' . $path);
    }

    protected function governmentIdUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolvePathUrl($this->government_id_path)
        );
    }

    protected function selfieWithIdUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolvePathUrl($this->selfie_with_id_path)
        );
    }

    protected function proofOfAddressUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolvePathUrl($this->proof_of_address_path)
        );
    }

    protected function barangayClearanceUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolvePathUrl($this->barangay_clearance_path)
        );
    }

    protected function mayorPermitUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolvePathUrl($this->mayor_permit_path)
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Store logo — stores Cloudinary public_id, generates URL on the fly
    // ─────────────────────────────────────────────────────────────────────

    protected function storeLogoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolveLogoUrl($this->store_logo_path)
        );
    }

    protected function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->resolveLogoUrl($this->store_logo_path)
        );
    }

    protected function resolveLogoUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
            return secure_asset('storage/' . $path);
        }
        return CloudinaryHelper::getUrl($path);
    }

    // Portfolio stores array of full Cloudinary secure URLs
    protected function portfolioPhotosUrls(): Attribute
    {
        return Attribute::make(
            get: fn () => array_map(fn($p) => $this->resolvePathUrl($p), $this->portfolio_photos_paths ?? [])
        );
    }

    /* Formatting Helpers */
    protected function formattedBusinessType(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'individual'  => 'Sole Proprietor',
                'partnership' => 'Partnership',
                'corporation' => 'Corporation',
            ][$this->business_type] ?? $this->business_type
        );
    }

    protected function formattedStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => [
                'pending'      => 'Pending',
                'approved'     => 'Approved',
                'rejected'     => 'Rejected',
                'under_review' => 'Under Review',
            ][$this->status] ?? $this->status
        );
    }

    protected function formattedDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->submitted_at?->format('m/d/y \a\t H:i')
        );
    }

    protected function formattedPriceRange(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->price_min || !$this->price_max) return null;
                return '₱' . number_format($this->price_min, 2)
                     . ' - ₱' . number_format($this->price_max, 2);
            }
        );
    }

    /* Scopes */
    public function scopePending($q)         { return $q->where('status', 'pending'); }
    public function scopeApproved($q)        { return $q->where('status', 'approved'); }
    public function scopeRejected($q)        { return $q->where('status', 'rejected'); }
    public function scopeUnderReview($q)     { return $q->where('status', 'under_review'); }
    public function scopeProfileComplete($q) { return $q->where('profile_fully_completed', true); }
    public function scopeProfileIncomplete($q) { return $q->where('profile_fully_completed', false); }

    public function scopeRecent($q, $days = 7)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeSearch($q, $search)
    {
        if (!$search) return $q;

        return $q->where(fn ($x) =>
            $x->where('store_name', 'like', "%{$search}%")
              ->orWhere('owner_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('application_id', 'like', "%{$search}%")
              ->orWhere('contact_number', 'like', "%{$search}%")
        );
    }

    /* State Helpers */
    public function isPending(): bool          { return $this->status === 'pending'; }
    public function isApproved(): bool         { return $this->status === 'approved'; }
    public function isRejected(): bool         { return $this->status === 'rejected'; }
    public function isProfileComplete(): bool  { return $this->profile_fully_completed; }

    public function approve(): bool
    {
        return $this->update([
            'status'             => 'approved',
            'reviewed_at'        => now(),
            'reviewed_by'        => auth()->id(),
            'verification_level' => 'verified',
        ]);
    }

    public function reject(?string $reason = null): bool
    {
        return $this->update([
            'status'           => 'rejected',
            'reviewed_at'      => now(),
            'reviewed_by'      => auth()->id(),
            'rejection_reason' => $reason,
        ]);
    }

    public function putUnderReview(): bool
    {
        return $this->update([
            'status'      => 'under_review',
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
    }

    public static function findApprovedForVendorUser(?User $vendor): ?self
    {
        if (!$vendor) {
            return null;
        }

        return static::where('email', $vendor->email)
            ->where('status', 'approved')
            ->first();
    }

    public static function buildReservationSettings(?User $vendor, ?self $application = null, ?CarbonInterface $referenceNow = null): array
    {
        $application ??= static::findApprovedForVendorUser($vendor);

        $now = $referenceNow
            ? Carbon::instance($referenceNow)->setTimezone(self::RESERVATION_TIMEZONE)
            : Carbon::now(self::RESERVATION_TIMEZONE);

        $sameDayDelivery = (bool) ($application?->same_day_delivery ?? false);
        $leadTimeDays = $application?->reservationLeadTimeDays() ?? ($sameDayDelivery ? 0 : 3);
        $maxOrdersPerDay = max(1, (int) ($application?->max_orders_per_day ?? 10));
        $cutoffTimeToday = $application?->cutoffTimeForDate($now);
        $sameDayCutoffReached = $sameDayDelivery
            ? $application?->isSameDayCutoffReached($now) ?? false
            : true;

        return [
            'timezone' => self::RESERVATION_TIMEZONE,
            'same_day_delivery' => $sameDayDelivery,
            'lead_time_days' => max(0, $leadTimeDays),
            'max_orders_per_day' => $maxOrdersPerDay,
            'cutoff_time_today' => $cutoffTimeToday,
            'same_day_cutoff_reached' => $sameDayCutoffReached,
            'same_day_available_today' => $sameDayDelivery && !$sameDayCutoffReached,
        ];
    }

    public function reservationLeadTimeDays(): int
    {
        $leadTime = strtolower(trim((string) $this->lead_time));

        if ($leadTime === '') {
            return $this->same_day_delivery ? 0 : 3;
        }

        if (str_contains($leadTime, 'same') && str_contains($leadTime, 'day')) {
            return 0;
        }

        if (preg_match('/(\d+(?:\.\d+)?)/', $leadTime, $matches)) {
            $value = (float) $matches[1];

            if (str_contains($leadTime, 'hour')) {
                return max(0, (int) ceil($value / 24));
            }

            return max(0, (int) ceil($value));
        }

        return $this->same_day_delivery ? 0 : 3;
    }

    public function cutoffTimeForDate(CarbonInterface $date): ?string
    {
        $dayName = Carbon::instance($date)->setTimezone(self::RESERVATION_TIMEZONE)->englishDayOfWeek;

        foreach ((array) ($this->cutoff_times ?? []) as $cutoff) {
            $cutoffDay = data_get($cutoff, 'day');
            $cutoffTime = data_get($cutoff, 'time');

            if ($cutoffDay === $dayName && is_string($cutoffTime) && preg_match('/^\d{2}:\d{2}$/', $cutoffTime)) {
                return $cutoffTime;
            }
        }

        return null;
    }

    public function isSameDayCutoffReached(?CarbonInterface $referenceNow = null): bool
    {
        if (!$this->same_day_delivery) {
            return true;
        }

        $now = $referenceNow
            ? Carbon::instance($referenceNow)->setTimezone(self::RESERVATION_TIMEZONE)
            : Carbon::now(self::RESERVATION_TIMEZONE);

        $cutoffTime = $this->cutoffTimeForDate($now);
        if (!$cutoffTime) {
            return false;
        }

        $cutoffDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            $now->format('Y-m-d') . ' ' . $cutoffTime,
            self::RESERVATION_TIMEZONE
        );

        return $now->greaterThanOrEqualTo($cutoffDateTime);
    }
}
