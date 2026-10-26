<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activity logs say exactly what was done and to whom:
 * "Took Badet off their Thu, Aug 7 shift (8:00 AM – 5:00 PM)".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_activity_logs', 'details')) {
                $table->string('details', 500)->nullable()->after('action');
            }
            if (! Schema::hasColumn('staff_activity_logs', 'subject')) {
                // The person or thing affected, for searching ("Badet")
                $table->string('subject', 150)->nullable()->after('details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_activity_logs', function (Blueprint $table) {
            $table->dropColumn(['details', 'subject']);
        });
    }
};
