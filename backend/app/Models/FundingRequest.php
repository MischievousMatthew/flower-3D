<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundingRequest extends Model
{
    use BelongsToOwner;
    protected $table = 'funding_requests';

    protected $fillable = [
        'owner_id',
        'finance_request_id',
        'submitted_by_employee_id',
        'requester_id',
        'accounting_manager_id',
        'approver_id',
        'related_sales_order_id',
        'request_date',
        'request_status',

        // Product info
        'product_name',
        'flower_category',
        'variant',
        'requested_qty',
        'uom',
        'moq',
        'preferred_supplier',
        'alternative_suppliers',
        'required_delivery_date',

        // Inventory
        'current_stock',
        'reserved_stock',
        'net_available_stock',
        'required_quantity',
        'stock_shortage_qty',
        'incoming_stock',
        'reason_for_shortage',

        // Financial
        'estimated_unit_cost',
        'estimated_total_cost',
        'currency',
        'payment_terms',
        'expected_selling_price',
        'expected_revenue',
        'estimated_gross_margin',
        'tax_vat_estimate',
        'logistics_cost',

        // Risk & urgency
        'urgency_level',
        'shelf_life',
        'expected_spoilage',
        'missed_sales_impact',
        'seasonal_tag',
        'demand_confidence',

        // Finance recommendation
        'finance_recommendation',
        'recommended_qty',
        'recommended_budget',
        'price_ceiling',
        'suggested_supplier',

        // Justification
        'business_justification',
        'approval_impact',
        'rejection_risk',
        'additional_notes',

        // Timestamps & review
        'submitted_to_accounting_at',
        'accounting_decision_at',
        'reviewed_by_employee_id',
        'approved_quantity',
        'approved_amount',
        'accounting_remarks',
        'rejection_reason',
        'rejection_notes',

        // Order link
        'linked_order_id',
    ];

    protected $casts = [
        'request_date'               => 'date',
        'required_delivery_date'     => 'date',
        'submitted_to_accounting_at' => 'datetime',
        'accounting_decision_at'     => 'datetime',

        'requested_qty'          => 'float',
        'moq'                    => 'float',
        'current_stock'          => 'float',
        'reserved_stock'         => 'float',
        'net_available_stock'    => 'float',
        'required_quantity'      => 'float',
        'stock_shortage_qty'     => 'float',
        'incoming_stock'         => 'float',
        'recommended_qty'        => 'float',
        'approved_quantity'      => 'float',

        'estimated_unit_cost'    => 'decimal:2',
        'estimated_total_cost'   => 'decimal:2',
        'expected_selling_price' => 'decimal:2',
        'expected_revenue'       => 'decimal:2',
        'estimated_gross_margin' => 'decimal:2',
        'tax_vat_estimate'       => 'decimal:2',
        'logistics_cost'         => 'decimal:2',
        'missed_sales_impact'    => 'decimal:2',
        'recommended_budget'     => 'decimal:2',
        'price_ceiling'          => 'decimal:2',
        'approved_amount'        => 'decimal:2',

        'shelf_life'             => 'float',
        'expected_spoilage'      => 'float',
    ];

    // ─── Status Constants ─────────────────────────────────────────────────────

    const STATUS_DRAFT     = 'Draft';
    const STATUS_PENDING   = 'Pending';
    const STATUS_APPROVED  = 'Approved';
    const STATUS_REJECTED  = 'Rejected';
    const STATUS_CANCELLED = 'Cancelled';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'submitted_by_employee_id');
    }

    public function accountingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'accounting_manager_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by_employee_id');
    }

    public function linkedOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'linked_order_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('request_status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('request_status', self::STATUS_PENDING);
    }

    public function scopeDraft($query)
    {
        return $query->where('request_status', self::STATUS_DRAFT);
    }

    public function scopeWithoutOrder($query)
    {
        return $query->whereNull('linked_order_id');
    }

    public function scopeForOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->request_status === self::STATUS_APPROVED;
    }

    public function isDraft(): bool
    {
        return $this->request_status === self::STATUS_DRAFT;
    }

    public function hasLinkedOrder(): bool
    {
        return !is_null($this->linked_order_id);
    }

    public function getEffectiveQty(): float
    {
        return (float) ($this->recommended_qty ?? $this->requested_qty ?? 0);
    }

    public function getEffectiveTotalCost(): float
    {
        return $this->getEffectiveQty() * (float) ($this->estimated_unit_cost ?? 0);
    }
}
