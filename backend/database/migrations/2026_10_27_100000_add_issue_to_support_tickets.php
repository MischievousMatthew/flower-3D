<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The exact problem a customer or vendor picked, and what they'd like done about it */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'issue')) {
                $table->string('issue', 40)->nullable()->after('category');
            }
            if (! Schema::hasColumn('support_tickets', 'desired_outcome')) {
                $table->string('desired_outcome', 20)->nullable()->after('issue');
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            foreach (['issue', 'desired_outcome'] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
