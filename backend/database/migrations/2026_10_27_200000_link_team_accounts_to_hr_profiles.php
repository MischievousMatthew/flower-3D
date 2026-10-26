<?php

use App\Models\Employee;
use App\Services\Staff\AccountProfileService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every team account gets an HR profile, so they have a QR code for attendance
 * and show up in HR. The profile starts as a draft with what the account already
 * knows; HR fills in the rest, so those details can't be required in the table.
 */
return new class extends Migration
{
    /** Details HR fills in later (never known when the shop owner adds a team account) */
    private const LATER = [
        'last_name', 'gender', 'date_of_birth', 'civil_status', 'personal_email', 'mobile_number', 'address',
        'emergency_contact_name', 'emergency_contact_number', 'emergency_relationship',
        'position', 'department', 'work_location', 'reporting_manager', 'shift_start', 'shift_end', 'rest_days',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('employees_info')) {
            return;
        }

        if (! Schema::hasColumn('employees_info', 'account_id')) {
            Schema::table('employees_info', function (Blueprint $table) {
                // The team account (sign-in) this profile belongs to, if any
                $table->foreignId('account_id')->nullable()->after('created_by_employee_id')
                    ->constrained('employees')->nullOnDelete();
            });
        }

        $columns = array_values(array_filter(self::LATER, fn ($c) => Schema::hasColumn('employees_info', $c)));
        if (DB::getDriverName() === 'pgsql') {
            // Keeps the allowed-value checks on gender and civil status; only the NOT NULL goes
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE employees_info ALTER COLUMN {$column} DROP NOT NULL");
            }
        } elseif (DB::getDriverName() === 'mysql') {
            Schema::table('employees_info', function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    match ($column) {
                        'gender' => $table->enum($column, ['Male', 'Female', 'Other'])->nullable()->change(),
                        'civil_status' => $table->enum($column, ['Single', 'Married', 'Divorced', 'Widowed'])->nullable()->change(),
                        'date_of_birth' => $table->date($column)->nullable()->change(),
                        'shift_start', 'shift_end' => $table->time($column)->nullable()->change(),
                        'address' => $table->text($column)->nullable()->change(),
                        default => $table->string($column, 200)->nullable()->change(),
                    };
                }
            });
        }

        // Link the profiles that already match an account by email, and give
        // every other account a draft. Never changes what HR already typed.
        $service = app(AccountProfileService::class);
        Employee::query()->whereNotNull('owner_id')->orderBy('id')->each(fn (Employee $e) => $service->ensureProfile($e));
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees_info', 'account_id')) {
            Schema::table('employees_info', function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }
    }
};
