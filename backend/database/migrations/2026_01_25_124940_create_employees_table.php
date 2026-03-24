<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('department');
            $table->date('joining_date');
            $table->enum('status', ['Active', 'On Leave', 'Resign'])->default('Active');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'department']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }
};