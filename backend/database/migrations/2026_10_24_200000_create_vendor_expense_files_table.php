<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Receipts and supporting documents for recorded expenses (several per
 * expense; photos or PDFs), stored privately and opened through short-lived
 * links. The older single receipt_path on vendor_expenses keeps working.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendor_expense_files')) {
            return;
        }

        Schema::create('vendor_expense_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_expense_id')->constrained('vendor_expenses')->cascadeOnDelete();
            $table->string('public_id');
            $table->string('resource_type', 10)->default('image');
            $table->string('format', 10)->nullable();
            $table->string('file_name', 160);
            $table->unsignedInteger('size')->default(0);
            $table->string('uploaded_by', 120)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_expense_files');
    }
};
