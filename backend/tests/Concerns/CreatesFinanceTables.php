<?php

namespace Tests\Concerns;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The money tables the finance pages read (ledger, orders and their items,
 * refund cases, recorded expenses) plus helpers to fill them.
 * Use after CreatesAttendanceTables and CreatesPayrollTables have built theirs.
 */
trait CreatesFinanceTables
{
    private int $orderSeq = 0;

    protected function createFinanceTables(): void
    {
        // Column checks are cached per process; look again for this schema
        (new \ReflectionProperty(\App\Services\VendorSalesService::class, 'orderMoneyColumns'))->setValue(null, null);
        foreach (['vendor_transactions', 'orders', 'order_items', 'products', 'order_requests', 'vendor_expenses', 'paymongo_webhook_events', 'cod_collections', 'vendor_expense_files', 'order_request_messages', 'deliveries', 'user_notifications'] as $table) {
            Schema::dropIfExists($table);
        }
        if (! Schema::hasColumn('users', 'surname')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('surname')->nullable());
        }
        Schema::create('vendor_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('type');
            $t->string('category');
            $t->decimal('amount', 12, 2);
            $t->decimal('balance_before', 12, 2)->default(0);
            $t->decimal('balance_after', 12, 2)->default(0);
            $t->string('description')->nullable();
            $t->string('status')->default('completed');
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('order_number');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('vendor_id');
            $t->string('status');
            $t->string('payment_status');
            $t->string('payment_method');
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->decimal('delivery_fee', 12, 2)->default(0);
            // No tax_amount: the real orders table dropped it (2026_02_01 migration)
            $t->decimal('discount_amount', 12, 2)->default(0);
            $t->decimal('total_amount', 12, 2);
            $t->string('delivery_contact_name')->nullable();
            $t->string('paymongo_payment_intent_id')->nullable();
            $t->json('paymongo_response')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id')->nullable();
            $t->string('product_name')->nullable();
            $t->integer('quantity');
            $t->decimal('unit_price', 12, 2)->default(0);
            $t->decimal('list_price', 12, 2)->nullable();
            $t->decimal('unit_cost', 12, 2)->nullable();
            $t->decimal('subtotal', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->decimal('purchase_price', 12, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('order_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('type')->default('refund');
            $t->string('status');
            $t->string('reason_category')->nullable();
            $t->text('reason')->nullable();
            $t->string('media_path')->nullable();
            $t->string('media_type')->nullable();
            $t->text('admin_notes')->nullable();
            $t->timestamp('offered_at')->nullable();
            $t->string('refund_method')->nullable();
            $t->string('refund_reference')->nullable();
            $t->string('closed_reason')->nullable();
            $t->text('replacement_note')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->string('resolution_requested')->nullable();
            $t->decimal('amount_requested', 12, 2)->nullable();
            $t->string('offer_resolution')->nullable();
            $t->decimal('offer_amount', 12, 2)->nullable();
            $t->string('offer_by')->nullable();
            $t->string('settled_resolution')->nullable();
            $t->decimal('settled_amount', 12, 2)->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->timestamp('item_received_at')->nullable();
            $t->timestamp('refund_sent_at')->nullable();
            $t->timestamp('replacement_sent_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('paymongo_webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_id')->unique();
            $t->string('event_type');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->json('payload');
            $t->string('status')->default('pending');
            $t->text('error_message')->nullable();
            $t->integer('retry_count')->default(0);
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('cod_collections', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('order_id')->unique();
            $t->decimal('expected_amount', 12, 2);
            $t->decimal('collected_amount', 12, 2);
            $t->string('collected_by')->nullable();
            $t->date('collected_on');
            $t->string('recorded_by')->nullable();
            $t->date('settled_on')->nullable();
            $t->string('settlement_reference')->nullable();
            $t->string('settled_by')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('order_request_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_request_id');
            $t->string('author_role');
            $t->string('author_name')->nullable();
            $t->unsignedBigInteger('author_user_id')->nullable();
            $t->unsignedBigInteger('author_employee_id')->nullable();
            $t->string('kind');
            $t->text('body')->nullable();
            $t->string('resolution')->nullable();
            $t->decimal('amount', 12, 2)->nullable();
            $t->string('media_path')->nullable();
            $t->string('media_type')->nullable();
            $t->timestamps();
        });
        Schema::create('deliveries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('order_id');
            $t->string('status')->default('pending');
            $t->timestamp('last_scanned_at')->nullable();
            $t->timestamps();
        });
        Schema::create('user_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('broadcast_id')->nullable();
            $t->string('type');
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('link_url')->nullable();
            $t->boolean('important')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_expenses', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('category');
            $t->string('description');
            $t->decimal('amount', 12, 2);
            $t->date('spent_on');
            $t->string('payment_method')->nullable();
            $t->string('payee')->nullable();
            $t->string('reference')->nullable();
            $t->string('receipt_path')->nullable();
            $t->string('receipt_type')->nullable();
            $t->text('notes')->nullable();
            $t->string('recorded_by')->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_expense_files', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('vendor_expense_id');
            $t->string('public_id');
            $t->string('resource_type')->default('image');
            $t->string('format')->nullable();
            $t->string('file_name');
            $t->unsignedInteger('size')->default(0);
            $t->string('uploaded_by')->nullable();
            $t->timestamps();
        });
    }

    protected function order(User $vendor, array $attrs): int
    {
        $this->orderSeq++;

        return DB::table('orders')->insertGetId($attrs + [
            'order_number' => 'ORD-' . $this->orderSeq, 'vendor_id' => $vendor->id, 'status' => 'completed',
            'payment_status' => 'paid', 'payment_method' => 'gcash', 'total_amount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A paid order credited to the shop. $items: [[name, qty, paid each, regular each|null, cost each]];
     * by default one item worth the whole amount.
     */
    protected function sale(User $vendor, float $amount, float $cost, string $at, array $order = [], ?array $items = null): int
    {
        $orderId = $this->order($vendor, $order + ['total_amount' => $amount, 'subtotal' => $amount, 'paid_at' => $at, 'created_at' => $at]);
        foreach ($items ?? [['Roses', 1, $amount, null, $cost]] as [$name, $qty, $paid, $regular, $unitCost]) {
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'product_name' => $name, 'quantity' => $qty, 'unit_price' => $paid,
                'list_price' => $regular, 'unit_cost' => $unitCost, 'subtotal' => $paid * $qty,
            ]);
        }
        $this->ledger($vendor, 'credit', 'order_revenue', $amount, $at, $orderId);

        return $orderId;
    }

    protected function ledger(User $vendor, string $type, string $category, float $amount, string $at, ?int $orderId = null, array $meta = []): void
    {
        DB::table('vendor_transactions')->insert([
            'vendor_id' => $vendor->id, 'order_id' => $orderId, 'type' => $type, 'category' => $category,
            'amount' => $amount, 'metadata' => json_encode($meta), 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** A Finance employee, with view access to the given ERP modules */
    protected function financeStaff(User $vendor, array $modules = []): Employee
    {
        $employee = Employee::forceCreate([
            'owner_id' => $vendor->id, 'name' => 'Finance ' . fake()->firstName(), 'email' => fake()->unique()->safeEmail(),
            'department' => 'Finance', 'password' => 'x', 'status' => 'Active',
        ]);
        foreach ($modules as $module) {
            DB::table('employee_module_permissions')->insert([
                'owner_id' => $vendor->id, 'employee_id' => $employee->id, 'module' => $module,
                'permission' => 'view', 'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $employee;
    }
}
