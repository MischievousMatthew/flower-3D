<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique(); // 'hr-manager', 'finance-manager', 'inventory-manager'
            $table->integer('hierarchy_level')->default(1);
            // Higher = more permissions. Manager=10, Coordinator=5, Specialist=3
            $table->json('accessible_modules')->nullable();
            // e.g. ["employees","attendance","payroll","leave"]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
