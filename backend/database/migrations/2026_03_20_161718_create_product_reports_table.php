<?php
// database/migrations/2026_xx_xx_create_product_reports_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->onDelete('cascade');

            $table->foreignId('reporter_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->string('reason');          // counterfeit | misleading | inappropriate | prohibited | spam | other
            $table->text('description')->nullable();

            // pending → reviewed by admin → approved (ban) or rejected (dismiss)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // One report per customer per product
            $table->unique(['product_id', 'reporter_id']);
        });

        // Track how many approved reports (bans) a vendor has accumulated
        // We use this to auto-suspend at 3.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('ban_count')->default(0)->after('role');
            $table->boolean('is_suspended')->default(false)->after('ban_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reports');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ban_count', 'is_suspended']);
        });
    }
};