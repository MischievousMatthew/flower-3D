<?php

use App\Support\Phone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saved contact numbers in the same "0917 123 4567" format new ones use.
 * Only numbers that are clearly mobile numbers change; anything else stays.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'users' => ['contact_number'],
        'vendor_applications' => ['contact_number'],
        'employees' => ['phone'],
        'employees_info' => ['mobile_number', 'emergency_contact_number'],
        'suppliers' => ['phone'],
        'supplier_contacts' => ['phone'],
        'site_settings' => ['contact_phone'],
    ];

    /** One number per account, so a user's number is skipped if someone already has it in the new format */
    private const UNIQUE = ['users.contact_number'];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                $unique = in_array("{$table}.{$column}", self::UNIQUE, true);
                DB::table($table)->whereNotNull($column)->where($column, '!=', '')->orderBy('id')
                    ->select(['id', $column])
                    ->chunkById(500, function ($rows) use ($table, $column, $unique) {
                        foreach ($rows as $row) {
                            $formatted = Phone::format($row->{$column});
                            if ($formatted === null || $formatted === $row->{$column}) {
                                continue;
                            }
                            if ($unique && DB::table($table)->where($column, $formatted)->exists()) {
                                continue;
                            }
                            DB::table($table)->where('id', $row->id)->update([$column => $formatted]);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        // The old formats were mixed; there is nothing to go back to.
    }
};
