<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop the old unique index first
            $table->dropUnique(['contact_number']);
            // Re-add as nullable + unique
            $table->string('contact_number')->nullable()->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['contact_number']);
            $table->string('contact_number')->nullable(false)->unique()->change();
        });
    }
};
