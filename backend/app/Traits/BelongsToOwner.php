<?php

namespace App\Traits;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $query) {
            $ownerId = static::resolveAuthenticatedOwnerId();

            if ($ownerId !== null) {
                $query->where($query->getModel()->getQualifiedOwnerColumn(), $ownerId);
            }
        });

        static::creating(function (Model $model) {
            if (!empty($model->owner_id)) {
                return;
            }

            $ownerId = static::resolveAuthenticatedOwnerId();

            if ($ownerId !== null) {
                $model->owner_id = $ownerId;
            }
        });
    }

    public function getQualifiedOwnerColumn(): string
    {
        return $this->getTable() . '.owner_id';
    }

    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->withoutGlobalScope('owner')
            ->where($this->getQualifiedOwnerColumn(), $ownerId);
    }

    protected static function resolveAuthenticatedOwnerId(): ?int
    {
        $user = auth()->user();

        if ($user instanceof Employee) {
            return $user->owner_id ? (int) $user->owner_id : null;
        }

        if ($user instanceof User && $user->role === User::ROLE_VENDOR) {
            return (int) $user->id;
        }

        return null;
    }
}
