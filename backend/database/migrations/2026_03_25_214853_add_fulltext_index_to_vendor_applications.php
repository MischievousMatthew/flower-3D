<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL full-text index using GIN
        DB::statement("
            CREATE INDEX vendor_fulltext 
            ON vendor_applications 
            USING GIN (
                to_tsvector('english', store_name || ' ' || store_description)
            )
        ");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX IF EXISTS vendor_fulltext");
    }
};