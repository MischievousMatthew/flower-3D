<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employment documents (contracts, IDs, certificates…) attached to an HR profile.
 * Shared by the shop owner (vendor portal) and HR staff (ERP): both read and write
 * the same rows, scoped by owner_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_documents')) {
            return;
        }

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_info_id')->constrained('employees_info')->cascadeOnDelete();
            $table->string('category', 40);
            $table->string('title', 150);
            $table->string('file_name', 255);
            $table->string('public_id', 255);
            $table->string('resource_type', 20)->default('image');
            $table->string('format', 20)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('uploaded_by', 150)->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'employee_info_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
