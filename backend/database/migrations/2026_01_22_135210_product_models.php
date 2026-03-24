<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('model_url', 500);
            $table->string('model_path', 500);
            $table->enum('model_type', ['glb', 'gltf', 'obj', 'fbx'])->default('glb');
            $table->string('thumbnail_url', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_models');
    }
};