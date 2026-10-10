<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vendor-defined wrappers, fillers and ribbons for the 3D flower customizer.
        Schema::create('bouquet_customization_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color_hex', 7)->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('model_url')->nullable();
            $table->string('model_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['owner_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bouquet_customization_options');
    }
};
