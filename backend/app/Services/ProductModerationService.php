<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductModerationEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * BloomCraft's side of a listing. Moderation only decides whether customers
 * can see a product; the vendor's own status, stock and details stay theirs.
 */
class ProductModerationService
{
    /** Mark a live (or resubmitted) listing as reviewed and fine */
    public function approve(Product $product, ?User $admin, ?string $note = null): void
    {
        if ($product->moderation_status === Product::MODERATION_REMOVED) {
            throw new LogicException('This listing was removed. Restore it first if it should come back.');
        }
        $this->set($product, Product::MODERATION_APPROVED, $admin, 'approved', $note, clearReason: true);
    }

    /** Take a listing down until the vendor fixes it */
    public function hide(Product $product, ?User $admin, string $reason): void
    {
        if ($product->moderation_status === Product::MODERATION_REMOVED) {
            throw new LogicException('This listing is already removed.');
        }
        $this->set($product, Product::MODERATION_HIDDEN, $admin, 'hidden', $reason);
    }

    /** Take a listing down for good (the vendor can't resubmit it) */
    public function remove(Product $product, ?User $admin, string $reason): void
    {
        if ($product->moderation_status === Product::MODERATION_REMOVED) {
            throw new LogicException('This listing is already removed.');
        }
        $this->set($product, Product::MODERATION_REMOVED, $admin, 'removed', $reason);
    }

    /** Put a hidden or removed listing back on the shop */
    public function restore(Product $product, ?User $admin, ?string $note = null): void
    {
        if (!$product->isTakenDown()) {
            throw new LogicException('This listing is already live.');
        }
        $this->set($product, Product::MODERATION_APPROVED, $admin, 'restored', $note, clearReason: true);
    }

    /** A vendor added a listing: it goes live and waits for review */
    public function markNew(Product $product): void
    {
        $product->forceFill(['moderation_status' => Product::MODERATION_PENDING])->save();
    }

    /**
     * A vendor edited a hidden listing: it waits for an admin to look again
     * (and stays down until then). Edits to removed listings change nothing.
     */
    public function markEdited(Product $product): void
    {
        if ($product->moderation_status !== Product::MODERATION_HIDDEN) {
            return;
        }

        DB::transaction(function () use ($product) {
            $product->forceFill(['moderation_status' => Product::MODERATION_RESUBMITTED])->save();
            ProductModerationEvent::create(['product_id' => $product->id, 'action' => 'resubmitted']);
        });
    }

    private function set(Product $product, string $status, ?User $admin, string $action, ?string $reason, bool $clearReason = false): void
    {
        DB::transaction(function () use ($product, $status, $admin, $action, $reason, $clearReason) {
            $product->forceFill([
                'moderation_status' => $status,
                'moderation_reason' => $clearReason ? null : $reason,
                'moderated_at' => now(),
                'moderated_by' => $admin?->id,
            ])->save();

            ProductModerationEvent::create([
                'product_id' => $product->id,
                'admin_id' => $admin?->id,
                'action' => $action,
                'reason' => $reason,
            ]);
        });
    }
}
