<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A set of admin permissions, e.g. "Support agent" */
class AdminRole extends Model
{
    /**
     * Every permission, grouped as the Security page shows them.
     * Keys are what routes check with the `admin.can:` middleware.
     */
    public const PERMISSIONS = [
        'Overview' => [
            'dashboard.view' => 'See the dashboard',
            'revenue.view' => 'See subscription revenue',
            'reports.view' => 'See reports & analytics',
            'reports.export' => 'Download reports (CSV / PDF)',
        ],
        'Orders & money' => [
            'orders.view' => 'See orders',
            'orders.notes' => 'Add notes to orders',
            'finance.view' => 'See payments and finance',
            'finance.export' => 'Export financial reports',
        ],
        'Vendors & customers' => [
            'vendors.view' => 'See vendors and applications',
            'vendors.manage' => 'Approve, reject, suspend and reactivate vendors',
            'customers.view' => 'See customers (personal details masked)',
            'customers.reveal' => 'Reveal customers\' personal details',
            'customers.manage' => 'Suspend and reactivate customers',
        ],
        'Products' => [
            'products.view' => 'See products and reports',
            'products.moderate' => 'Approve, hide and remove listings; review reports',
            'products.taxonomy' => 'Manage categories, flower types and occasions',
        ],
        'Website & support' => [
            'content.manage' => 'Manage website content',
            'support.view' => 'See alerts, support requests and activity',
            'support.reply' => 'Reply to support requests',
            'support.broadcast' => 'Send announcements',
        ],
        'Security' => [
            'security.view' => 'See audit logs, sign-ins and system health',
            'security.manage' => 'Manage administrators, roles and platform settings',
        ],
    ];

    protected $fillable = ['name', 'description', 'permissions', 'is_system'];

    protected $casts = ['permissions' => 'array', 'is_system' => 'boolean'];

    public static function allKeys(): array
    {
        return collect(self::PERMISSIONS)->flatMap(fn ($group) => array_keys($group))->values()->all();
    }

    public function grants(string $permission): bool
    {
        $granted = $this->permissions ?? [];
        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    /** @return string[] */
    public function expanded(): array
    {
        return in_array('*', $this->permissions ?? [], true) ? self::allKeys() : array_values($this->permissions ?? []);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }
}
