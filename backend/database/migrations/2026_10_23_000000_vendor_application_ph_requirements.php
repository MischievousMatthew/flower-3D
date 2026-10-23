<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a Philippine business registers with, on the vendor application:
 * the registered business name, the CDA number for cooperatives (DTI for
 * sole proprietors and SEC for partnerships and corporations already exist),
 * the registration certificate, BIR Form 2303, the business permit's
 * validity, and the type of government ID.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'registered_business_name' => 'string',
        'cda_number' => 'string',
        'business_registration_path' => 'text',
        'bir_certificate_path' => 'text',
        'mayor_permit_valid_until' => 'date',
        'government_id_type' => 'string',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('vendor_applications')) {
            return;
        }
        Schema::table('vendor_applications', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $type) {
                if (! Schema::hasColumn('vendor_applications', $column)) {
                    $table->{$type}($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('vendor_applications')) {
            return;
        }
        Schema::table('vendor_applications', function (Blueprint $table) {
            foreach (array_keys(self::COLUMNS) as $column) {
                if (Schema::hasColumn('vendor_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
