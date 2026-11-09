<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FundingPaymentCheckout;
use App\Support\SupplierPaymentTerms;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Approving a funding request sets a budget; the supplier is paid when their
 * terms say, for what they confirmed or delivered, and owes back the rest.
 */
class FundingPaymentTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    private const BASE = '/api/finance/funding-requests';

    private $vendor;
    private Employee $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        foreach (['funding_payment_checkouts', 'funding_request_payments', 'funding_request_items', 'funding_requests', 'purchase_order_items', 'purchase_orders', 'suppliers', 'vendor_balances'] as $t) {
            Schema::dropIfExists($t);
        }
        foreach (['2026_02_07_085208_create_funding_requests_table', '2026_03_10_065051_add_linked_order_id_to_funding_requests_table',
            '2026_04_02_120100_add_requester_and_approver_to_funding_requests_table', '2026_04_05_110000_add_payment_status_to_funding_requests_table',
            '2026_11_09_120000_add_items_and_payment_method_to_funding_requests', '2026_11_09_130000_add_approved_quantity_to_funding_request_items',
            '2026_11_09_140000_funding_request_payments', '2026_11_09_160000_create_funding_payment_checkouts'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->string('company_name');
            $t->string('payment_terms')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('supplier_id')->nullable();
            $t->string('order_number')->nullable();
            $t->string('status')->default('pending');
            $t->decimal('total_amount', 15, 2)->default(0);
            $t->timestamp('supplier_confirmed_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('purchase_order_id');
            $t->string('product_name');
            $t->integer('quantity');
            $t->decimal('price', 15, 2);
            $t->integer('received_qty')->default(0);
            $t->integer('rejected_qty')->default(0);
            $t->integer('cancelled_qty')->default(0);
        });
        Schema::create('vendor_balances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->decimal('balance', 12, 2)->default(0);
            $t->timestamps();
        });
        Schema::disableForeignKeyConstraints();

        $this->vendor = $this->shopOwner();
        $this->finance = Employee::forceCreate(['owner_id' => $this->vendor->id, 'name' => 'Fe', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        foreach (['view', 'approve'] as $p) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $this->vendor->id, 'employee_id' => $this->finance->id, 'module' => 'funding_requests', 'permission' => $p, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** A pending request for 100 roses at ₱20 with a ₱100 delivery fee (₱2,100) */
    private function request(): int
    {
        $id = DB::table('funding_requests')->insertGetId([
            'owner_id' => $this->vendor->id, 'finance_request_id' => 'FR-' . fake()->unique()->numberBetween(1, 99999),
            'submitted_by_employee_id' => $this->finance->id, 'approver_id' => $this->finance->id,
            'request_date' => now()->toDateString(), 'request_status' => 'Pending', 'payment_status' => 'unpaid',
            'product_name' => 'Red rose', 'requested_qty' => 100, 'estimated_total_cost' => 2100, 'logistics_cost' => 100,
            'payment_method' => 'bank', 'payment_channel' => 'BDO', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('funding_request_items')->insert(['funding_request_id' => $id, 'product_name' => 'Red rose', 'uom' => 'Stems', 'quantity' => 100, 'unit_cost' => 20, 'line_total' => 2000, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function approve(int $id, float $amount = 2100): \Illuminate\Testing\TestResponse
    {
        return $this->signIn($this->finance)->postJson(self::BASE . "/{$id}/approve", ['approved_quantity' => 100, 'approved_amount' => $amount]);
    }

    /** The purchase order made from the request, as the supplier answered it */
    private function order(int $fundingId, string $terms, string $status, int $confirmed = 100, int $received = 0): int
    {
        $supplier = DB::table('suppliers')->insertGetId(['owner_id' => $this->vendor->id, 'company_name' => 'Bloom Farms', 'payment_terms' => $terms]);
        $order = DB::table('purchase_orders')->insertGetId([
            'owner_id' => $this->vendor->id, 'supplier_id' => $supplier, 'order_number' => 'PO-1', 'status' => $status,
            'supplier_confirmed_at' => $status === 'pending' ? null : now(), 'completed_at' => $status === 'completed' ? now() : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('purchase_order_items')->insert([
            'owner_id' => $this->vendor->id, 'purchase_order_id' => $order, 'product_name' => 'Red rose', 'quantity' => 100, 'price' => 20,
            'received_qty' => $received, 'cancelled_qty' => $status === 'completed' ? 100 - $received : 100 - $confirmed,
        ]);
        DB::table('funding_requests')->where('id', $fundingId)->update(['linked_order_id' => $order]);

        return $order;
    }

    private function pay(int $id, float $amount): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(self::BASE . "/{$id}/pay", ['amount' => $amount, 'reference' => 'BDO-123', 'paid_on' => now()->toDateString()]);
    }

    private function payment(int $id): array
    {
        return $this->getJson(self::BASE . "/{$id}")->assertOk()->json('data.payment');
    }

    public function test_terms_are_read_from_the_suppliers_words(): void
    {
        $this->assertSame(SupplierPaymentTerms::BEFORE, SupplierPaymentTerms::timing('Pay before delivery'));
        $this->assertSame(SupplierPaymentTerms::BEFORE, SupplierPaymentTerms::timing('50% down payment'));
        $this->assertSame(SupplierPaymentTerms::AFTER, SupplierPaymentTerms::timing('Cash on delivery'));
        $this->assertSame(SupplierPaymentTerms::AFTER, SupplierPaymentTerms::timing(null));
        $this->assertSame(15, SupplierPaymentTerms::dueDays('15 days'));
        $this->assertSame(30, SupplierPaymentTerms::dueDays('Net 30'));
        $this->assertNull(SupplierPaymentTerms::dueDays('Cash on delivery'));
    }

    public function test_approving_sets_a_budget_and_takes_nothing_from_the_balance(): void
    {
        DB::table('vendor_balances')->insert(['vendor_id' => $this->vendor->id, 'balance' => 0]);
        $id = $this->request();

        $this->approve($id, 0)->assertStatus(422);
        $this->approve($id, 2500)->assertStatus(422); // more than asked
        $this->approve($id, 2100)->assertOk();

        $row = DB::table('funding_requests')->find($id);
        $this->assertSame('Approved', $row->request_status);
        $this->assertSame('unpaid', $row->payment_status);
        $this->assertEquals(0, DB::table('vendor_balances')->where('vendor_id', $this->vendor->id)->value('balance'));
        $this->assertSame('needs_order', $this->payment($id)['stage']);
        $this->pay($id, 100)->assertStatus(422); // no order yet
    }

    public function test_pay_before_delivery_pays_only_what_the_supplier_confirmed(): void
    {
        $id = $this->request();
        $this->approve($id)->assertOk();
        $order = $this->order($id, 'Pay before delivery', 'pending');
        $this->assertSame('with_supplier', $this->payment($id)['stage']);
        $this->pay($id, 100)->assertStatus(422);

        // They can supply 60 of the 100
        DB::table('purchase_orders')->where('id', $order)->update(['status' => 'processing', 'supplier_confirmed_at' => now()]);
        DB::table('purchase_order_items')->where('purchase_order_id', $order)->update(['cancelled_qty' => 40]);
        $p = $this->payment($id);
        $this->assertSame('to_pay', $p['stage']);
        $this->assertEquals(1300, $p['to_pay']); // 60 × ₱20 + ₱100 delivery
        $this->assertEquals(800, $p['released']);

        $this->pay($id, 1400)->assertStatus(422);
        $this->pay($id, 1300)->assertOk()->assertJsonPath('data.stage', 'paid');
        $this->assertSame('paid', DB::table('funding_requests')->where('id', $id)->value('payment_status'));
        $this->assertSame(SupplierPaymentTerms::BEFORE, DB::table('funding_requests')->where('id', $id)->value('payment_timing'));
    }

    public function test_a_short_delivery_after_paying_leaves_the_supplier_owing(): void
    {
        $id = $this->request();
        $this->approve($id)->assertOk();
        $order = $this->order($id, 'Pay before delivery', 'processing');
        $this->pay($id, 2100)->assertOk();

        // Only 90 came; the order is closed
        DB::table('purchase_orders')->where('id', $order)->update(['status' => 'completed', 'completed_at' => now()]);
        DB::table('purchase_order_items')->where('purchase_order_id', $order)->update(['received_qty' => 90, 'cancelled_qty' => 10]);
        $p = $this->payment($id);
        $this->assertSame('supplier_owes', $p['stage']);
        $this->assertEquals(200, $p['supplier_owes']);

        $this->postJson(self::BASE . "/{$id}/supplier-credit", ['outcome' => 'refund', 'amount' => 300, 'reference' => 'R-1', 'paid_on' => now()->toDateString()])->assertStatus(422);
        $this->postJson(self::BASE . "/{$id}/supplier-credit", ['outcome' => 'refund', 'amount' => 200, 'reference' => 'R-1', 'paid_on' => now()->toDateString()])
            ->assertOk()->assertJsonPath('data.stage', 'paid')->assertJsonPath('data.supplier_owes', 0);
    }

    public function test_pay_after_delivery_waits_for_the_stock_and_pays_what_came(): void
    {
        $id = $this->request();
        $this->approve($id)->assertOk();
        $order = $this->order($id, '15 days', 'processing');
        $this->assertSame('awaiting_delivery', $this->payment($id)['stage']);
        $this->pay($id, 100)->assertStatus(422);

        DB::table('purchase_orders')->where('id', $order)->update(['status' => 'completed', 'completed_at' => now()]);
        DB::table('purchase_order_items')->where('purchase_order_id', $order)->update(['received_qty' => 80, 'cancelled_qty' => 20]);
        $p = $this->payment($id);
        $this->assertSame('to_pay', $p['stage']);
        $this->assertEquals(1700, $p['to_pay']); // 80 × ₱20 + ₱100
        $this->assertSame(now()->addDays(15)->toDateString(), $p['due_date']);

        $this->pay($id, 1000)->assertOk()->assertJsonPath('data.to_pay', 700);
        $this->pay($id, 700)->assertOk()->assertJsonPath('data.stage', 'paid');
    }

    public function test_paying_through_paymongo_records_the_payment_once_it_is_paid(): void
    {
        config(['services.paymongo.secret_key' => 'sk_test_x']);
        $id = $this->request();
        $this->approve($id)->assertOk();
        $this->order($id, 'Pay before delivery', 'processing');

        $paid = false;
        Http::fake(function ($request) use (&$paid) {
            if ($request->method() === 'POST') {
                $this->assertSame(['gcash'], $request['data']['attributes']['payment_method_types']);
                $this->assertSame(210000, $request['data']['attributes']['line_items'][0]['amount']);

                return Http::response(['data' => ['id' => 'cs_1', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.test/cs_1']]]);
            }
            $checkoutId = (string) FundingPaymentCheckout::value('id');

            return Http::response(['data' => ['id' => 'cs_1', 'attributes' => [
                'metadata' => ['funding_payment_checkout_id' => $checkoutId],
                'payments' => $paid ? [['id' => 'pay_ABC', 'attributes' => ['status' => 'paid', 'amount' => 210000]]] : [],
            ]]]);
        });

        // Only PayMongo's banks and e-wallets
        $this->postJson(self::BASE . "/{$id}/paymongo", ['amount' => 2100, 'payment_channel' => 'PNB'])->assertStatus(422);
        $this->postJson(self::BASE . "/{$id}/paymongo", ['amount' => 10, 'payment_channel' => 'GCash'])->assertStatus(422);
        $this->postJson(self::BASE . "/{$id}/paymongo", ['amount' => 2100, 'payment_channel' => 'GCash'])
            ->assertOk()->assertJsonPath('data.checkout_url', 'https://checkout.paymongo.test/cs_1');
        $checkout = FundingPaymentCheckout::firstOrFail();

        // Back before PayMongo has it as paid: nothing recorded
        $this->get("/api/funding-payments/callback?checkout_id={$checkout->id}&success=true")->assertRedirectContains('funding_payment=pending');
        $this->assertSame(0, DB::table('funding_request_payments')->count());

        $paid = true;
        $this->get("/api/funding-payments/callback?checkout_id={$checkout->id}&success=true")->assertRedirectContains('funding_payment=paid');
        $this->get("/api/funding-payments/callback?checkout_id={$checkout->id}&success=true")->assertRedirectContains('funding_payment=paid');

        $this->assertSame(1, DB::table('funding_request_payments')->count());
        $row = DB::table('funding_request_payments')->first();
        $this->assertSame('pay_ABC', $row->reference);
        $this->assertSame('GCash', $row->payment_channel);
        $this->assertSame('Fe', $row->recorded_by_name);
        $this->assertSame('paid', $checkout->fresh()->status);
        $this->signIn($this->finance);
        $this->assertSame('paid', $this->payment($id)['stage']);
        $this->assertSame('BC-R-' . str_pad((string) $row->id, 6, '0', STR_PAD_LEFT), $this->payment($id)['payments'][0]['receipt_number']);
    }
}
