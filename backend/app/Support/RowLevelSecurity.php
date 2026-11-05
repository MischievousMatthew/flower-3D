<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Supabase serves every table in the `public` schema through its Data API,
 * where anyone with the project's public (anon) key could read it unless Row
 * Level Security is on. BloomCraft never uses that API: the Laravel backend
 * connects as the `postgres` role, which owns the tables and bypasses RLS.
 * So RLS is switched on with no policies: the Data API sees nothing, the app
 * is unchanged.
 *
 * Runs after every `php artisan migrate` (AppServiceProvider), so tables added
 * later are covered too. PostgreSQL only; a no-op anywhere else (tests, MySQL).
 */
class RowLevelSecurity
{
    /** Turns RLS on for every table in `public` that doesn't have it; returns their names */
    public static function enableEverywhere(): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return [];
        }

        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->where('n.nspname', 'public')
            ->whereIn('c.relkind', ['r', 'p']) // tables and partitioned tables
            ->where('c.relrowsecurity', false)
            ->orderBy('c.relname')
            ->pluck('c.relname')
            ->all();

        $done = [];
        foreach ($tables as $table) {
            try {
                // Quoted as an identifier: names come from the catalog, but stay safe anyway
                DB::statement('ALTER TABLE public."' . str_replace('"', '""', $table) . '" ENABLE ROW LEVEL SECURITY');
                $done[] = $table;
            } catch (\Throwable $e) {
                Log::warning('Could not turn on row level security.', ['table' => $table, 'error' => $e->getMessage()]);
            }
        }

        if ($done) {
            Log::info('Row level security turned on.', ['tables' => $done]);
        }

        return $done;
    }
}
