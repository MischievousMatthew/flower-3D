<?php

namespace Tests\Feature;

use App\Support\StaffActivityDetails;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Activity entries say exactly what was done and to whom. */
class StaffActivityDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 09:00:00');

        foreach (['employees_info', 'staff_shifts', 'employee_leaves', 'payrolls', 'products', 'orders', 'users'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('employees_info', function (Blueprint $t) {
            $t->id();
            $t->string('first_name');
            $t->string('last_name')->nullable();
            $t->softDeletes();
        });
        Schema::create('staff_shifts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('shift_date');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->boolean('is_off')->default(false);
        });
        Schema::create('employee_leaves', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('leave_type');
            $t->date('start_date');
            $t->date('end_date');
        });
        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('period_start');
            $t->date('period_end');
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('product_name');
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('surname')->nullable();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number');
            $t->unsignedBigInteger('user_id');
            $t->string('status');
            $t->string('delivery_contact_name')->nullable();
        });

        DB::table('employees_info')->insert([['id' => 1, 'first_name' => 'Badet', 'last_name' => null], ['id' => 2, 'first_name' => 'Miki', 'last_name' => 'Cruz']]);
    }

    private function describe(string $key, string $method, array $input = [], array $params = []): ?array
    {
        $request = Request::create('/x', $method, $input);
        $route = (new Route($method, 'x', []))->bind($request);
        foreach ($params as $name => $value) {
            $route->setParameter($name, $value);
        }
        $request->setRouteResolver(fn () => $route);

        return StaffActivityDetails::describe($key, $request);
    }

    public function test_taking_someone_off_a_shift_names_them_the_day_and_the_hours(): void
    {
        DB::table('staff_shifts')->insert(['id' => 5, 'employee_id' => 1, 'shift_date' => '2026-08-07', 'start_time' => '08:00:00', 'end_time' => '17:00:00']);

        $this->assertSame(
            ['Took Badet off their Fri, Aug 7 shift (8:00 AM – 5:00 PM)', 'Badet'],
            $this->describe('ScheduleController@remove', 'DELETE', [], ['id' => 5]),
        );
    }

    public function test_a_cover_names_both_people_and_why(): void
    {
        DB::table('staff_shifts')->insert(['id' => 6, 'employee_id' => 1, 'shift_date' => '2026-10-28', 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

        $this->assertSame(
            ["Had Miki Cruz cover Badet's Wed, Oct 28 shift (Badet is sick)", 'Badet'],
            $this->describe('ScheduleController@replace', 'POST', ['employee_id' => 2, 'reason' => 'sick'], ['id' => 6]),
        );
    }

    public function test_scheduling_several_days_and_a_day_off(): void
    {
        $this->assertSame(
            ['Scheduled Miki Cruz on 3 days from Mon, Nov 2 to Wed, Nov 4 (7:00 AM – 3:00 PM)', 'Miki Cruz'],
            $this->describe('StaffController@saveShift', 'POST', ['employee_id' => 2, 'dates' => ['2026-11-02', '2026-11-03', '2026-11-04'], 'start_time' => '07:00', 'end_time' => '15:00']),
        );
        $this->assertSame(
            ['Gave Badet a day off on Sun, Nov 1', 'Badet'],
            $this->describe('ScheduleController@assign', 'POST', ['employee_id' => 1, 'date' => '2026-11-01', 'day_off' => true]),
        );
    }

    public function test_leave_and_payroll_decisions_name_the_employee(): void
    {
        DB::table('employee_leaves')->insert(['id' => 3, 'employee_id' => 2, 'leave_type' => 'sick_leave', 'start_date' => '2026-10-20', 'end_date' => '2026-10-21']);
        DB::table('payrolls')->insert(['id' => 9, 'employee_id' => 1, 'period_start' => '2026-10-01', 'period_end' => '2026-10-15']);

        $this->assertSame(
            ["Approved Miki Cruz's sick leave, Tue, Oct 20 – Wed, Oct 21", 'Miki Cruz'],
            $this->describe('EmployeeLeaveController@updateStatus', 'PUT', ['status' => 'approved'], ['id' => 3]),
        );
        $this->assertSame(
            ["Approved for payment: Badet's payroll for Thu, Oct 1 – Thu, Oct 15", 'Badet'],
            $this->describe('PayrollController@financeApprove', 'POST', ['ids' => [9]]),
        );
    }

    public function test_products_and_orders_are_named_too(): void
    {
        DB::table('products')->insert(['id' => 4, 'product_name' => 'Red Roses']);
        DB::table('users')->insert(['id' => 7, 'name' => 'Ana', 'surname' => 'Reyes']);
        DB::table('orders')->insert(['id' => 8, 'order_number' => 'ORD-0042', 'user_id' => 7, 'status' => 'processing']);

        $this->assertSame(
            ['Changed the stock of "Red Roses" (+12), reason: restock', 'Red Roses'],
            $this->describe('VendorCatalogController@adjustStock', 'POST', ['mode' => 'add', 'quantity' => 12, 'reason' => 'restock'], ['id' => 4]),
        );
        $this->assertSame(
            ['Changed order ORD-0042 for Ana Reyes from processing to out for delivery', 'Ana Reyes'],
            $this->describe('VendorOrdersController@updateOrderStatus', 'PUT', ['status' => 'out_for_delivery'], ['orderId' => 8]),
        );
    }

    public function test_an_unknown_record_falls_back_to_the_general_wording(): void
    {
        $this->assertNull($this->describe('ScheduleController@remove', 'DELETE', [], ['id' => 999]));
        $this->assertNull($this->describe('SomethingController@else', 'POST'));
    }
}
