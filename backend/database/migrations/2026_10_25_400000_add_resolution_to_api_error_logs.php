<?php

use App\Models\ApiErrorLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Errors with the same cause are one problem (signature), and an admin can
 * mark a problem fixed (resolved_at). If it happens again, the new error
 * shows up as open again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_error_logs')) {
            return;
        }
        Schema::table('api_error_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('api_error_logs', 'signature')) {
                $table->string('signature', 32)->nullable()->index();
            }
            if (! Schema::hasColumn('api_error_logs', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->index();
            }
        });

        // Errors already logged get their problem, a few hundred at a time
        DB::table('api_error_logs')->whereNull('signature')->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('api_error_logs')->where('id', $row->id)->update(['signature' => ApiErrorLog::signatureFor((array) $row)]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('api_error_logs')) {
            Schema::table('api_error_logs', fn (Blueprint $table) => $table->dropColumn(['signature', 'resolved_at']));
        }
    }
};
