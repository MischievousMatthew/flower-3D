<?php

use App\Support\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;

/**
 * Turns on Row Level Security for every table in `public` (Supabase's
 * "RLS Disabled in Public" warnings). The backend's database role bypasses
 * RLS, so the app works as before; only Supabase's public Data API is shut out.
 * See App\Support\RowLevelSecurity.
 */
return new class extends Migration
{
    public function up(): void
    {
        RowLevelSecurity::enableEverywhere();
    }

    public function down(): void
    {
        // Left on: switching it off would expose the tables again
    }
};
