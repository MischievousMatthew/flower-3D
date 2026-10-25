<?php

use App\Http\Middleware\LogStaffActivity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Actions without their own wording used to be logged with internal code
 * names ("Added to employee auth", area "Vendor Books"). Reword the ones
 * already in the log the way new ones are written now. Sign-outs, the most
 * common case, become "Signed out".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_activity_logs')) {
            return;
        }

        DB::table('staff_activity_logs')
            ->where('area', 'Employee Auth')
            ->where('method', 'POST')
            ->update(['action' => 'Signed out', 'area' => 'Account']);

        foreach (DB::table('staff_activity_logs')->distinct()->pluck('area') as $old) {
            $words = Str::lower($old);
            $new = LogStaffActivity::areaFor(Str::studly($old) . 'Controller');
            $rows = DB::table('staff_activity_logs')->where('area', $old)
                ->where(fn ($q) => $q->where('action', 'like', "Added to {$words}%")
                    ->orWhere('action', 'like', "Removed something from {$words}%")
                    ->orWhere('action', 'like', "Updated {$words}%"))
                ->get(['id', 'action']);

            foreach ($rows as $row) {
                $name = Str::contains($row->action, ': ') ? ': ' . Str::after($row->action, ': ') : '';
                $verb = Str::startsWith($row->action, 'Removed') ? 'Removed something in ' : 'Made a change in ';
                DB::table('staff_activity_logs')->where('id', $row->id)->update([
                    'area' => $new,
                    'action' => Str::limit($verb . Str::lower($new) . $name, 157),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: the old wording was just unclear
    }
};
