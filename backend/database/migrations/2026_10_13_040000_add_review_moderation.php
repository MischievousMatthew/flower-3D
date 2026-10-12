<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Admins can hide a review (with a reason) without editing or deleting it */
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('is_anonymous');
            $table->string('hidden_reason', 500)->nullable()->after('hidden_at');
            $table->foreignId('hidden_by')->nullable()->after('hidden_reason')->constrained('users')->nullOnDelete();
            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropIndex(['hidden_at']);
            $table->dropColumn(['hidden_at', 'hidden_reason']);
        });
    }
};
