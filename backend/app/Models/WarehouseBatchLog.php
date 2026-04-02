<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseBatchLog extends Model
{
    use BelongsToOwner;

    public $timestamps = false;   // table has no updated_at — use created_at only

    protected $dates = ['created_at'];

    protected $fillable = [
        'owner_id',
        'warehouse_batch_id',
        'performed_by',
        'event_type',
        'condition_before',
        'condition_after',
        'qty_change',
        'qty_after',
        'from_location',
        'to_location',
        'notes',
    ];

    protected $casts = [
        'qty_change' => 'integer',
        'qty_after'  => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function batch(): BelongsTo
    {
        return $this->belongsTo(WarehouseBatch::class, 'warehouse_batch_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'performed_by');
    }
}
