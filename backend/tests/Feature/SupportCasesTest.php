<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesFinanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Complaints and service issues run on the existing support tickets: a
 * customer's complaint about an order reaches that shop, the shop works it
 * (status, priority, assignee, linked order and refund), and both sides follow
 * the same ticket. Team notes stay with the team.
 */
class SupportCasesTest extends TestCase
{
    use CreatesAttendanceTables, CreatesFinanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        $this->createFinanceTables();
        foreach (['support_messages', 'support_tickets', 'conversations', 'customer_inquiries'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'contact_number')) {
                $t->string('contact_number')->nullable();
            }
        });
        Schema::table('vendor_applications', fn (Blueprint $t) => $t->string('store_name')->nullable());
        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->string('reference', 20)->unique();
            $t->unsignedBigInteger('requester_id');
            $t->string('requester_role', 20);
            $t->string('subject', 160);
            $t->string('category', 30);
            $t->string('priority', 20)->default('normal');
            $t->string('status', 20)->default('open');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->timestamp('last_activity_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });
        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('ticket_id');
            $t->unsignedBigInteger('author_id')->nullable();
            $t->boolean('from_admin')->default(false);
            $t->text('body');
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_24_300000_add_shop_cases_to_support_tickets.php'))->up();
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->timestamps();
        });
        Schema::create('customer_inquiries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('vendor_id');
            $t->unsignedBigInteger('customer_id');
            $t->text('question');
            $t->string('status')->default('open');
            $t->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as($account)
    {
        $this->app['auth']->forgetGuards();

        return $this->signIn($account);
    }

    private function staff(User $vendor, string $name, array $perms): Employee
    {
        $e = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => $name, 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'status' => 'Active']);
        foreach ($perms as $perm) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => 'crm_cases', 'permission' => $perm, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }

        return $e;
    }

    private function shop(string $store): User
    {
        $vendor = $this->shopOwner();
        DB::table('vendor_applications')->where('email', $vendor->email)->update(['store_name' => $store]);

        return $vendor;
    }

    public function test_a_customer_complaint_reaches_the_shop_and_both_sides_follow_it(): void
    {
        $shop = $this->shop('Petal & Stem');
        $ana = User::create(['name' => 'Ana', 'surname' => 'Reyes', 'email' => 'ana@example.com', 'password' => 'x', 'role' => 'customer']);
        $order = $this->order($shop, ['user_id' => $ana->id, 'order_number' => 'ORD-77', 'total_amount' => 1850]);
        $refund = DB::table('order_requests')->insertGetId(['owner_id' => $shop->id, 'order_id' => $order, 'user_id' => $ana->id, 'type' => 'refund', 'status' => 'pending', 'amount_requested' => 500, 'created_at' => now(), 'updated_at' => now()]);

        // A complaint needs the order, so it knows which shop to go to
        $this->as($ana)->postJson('/api/support/tickets', ['subject' => 'Wilted roses', 'category' => 'quality', 'message' => 'Half the roses arrived wilted.'])
            ->assertStatus(422)->assertJsonValidationErrors('order_id');
        $sent = $this->postJson('/api/support/tickets', ['subject' => 'Wilted roses', 'category' => 'quality', 'message' => 'Half the roses arrived wilted.', 'order_id' => $order])
            ->assertCreated()->assertJsonPath('data.handled_by', 'Petal & Stem');
        $this->assertStringContainsString('sent to Petal & Stem', $sent->json('message'));
        $id = $sent->json('data.id');

        // The shop sees it, works it, and keeps a team note
        $mara = $this->staff($shop, 'Mara', ['view', 'create', 'edit']);
        $this->as($mara)->getJson('/api/crm/cases')->assertOk()
            ->assertJsonPath('data.rows.0.reference', DB::table('support_tickets')->value('reference'))
            ->assertJsonPath('data.rows.0.order_number', 'ORD-77')
            ->assertJsonPath('data.counts.active', 1)
            ->assertJsonPath('data.unassigned', 1);
        $this->postJson("/api/crm/cases/{$id}/reply", ['message' => 'So sorry! We will send fresh roses today.'])->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/crm/cases/{$id}/reply", ['message' => 'Supplier batch from Benguet, check the cooler.', 'internal' => true])->assertOk();
        $this->patchJson("/api/crm/cases/{$id}", ['priority' => 'urgent', 'assigned_employee_id' => $mara->id, 'order_request_id' => $refund])->assertOk()
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.assignee.name', 'Mara')
            ->assertJsonPath('data.refund.amount', 500);

        // Someone from another shop can't be assigned, nor can another customer's refund be linked
        $other = $this->shop('Other');
        $outsider = $this->staff($other, 'Out', ['view']);
        $this->patchJson("/api/crm/cases/{$id}", ['assigned_employee_id' => $outsider->id])->assertStatus(422);

        // The customer sees the shop's reply under the shop's name, not the team note, and was notified
        $mine = $this->as($ana)->getJson("/api/support/tickets/{$id}")->assertOk();
        $bodies = array_column($mine->json('data.messages'), 'body');
        $this->assertContains('So sorry! We will send fresh roses today.', $bodies);
        $this->assertNotContains('Supplier batch from Benguet, check the cooler.', $bodies);
        $this->assertSame('Petal & Stem', collect($mine->json('data.messages'))->firstWhere('body', 'So sorry! We will send fresh roses today.')['author']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $ana->id, 'title' => 'Petal & Stem replied to ' . DB::table('support_tickets')->value('reference')]);

        // Resolving tells the customer; another shop can't see the case
        $this->as($mara)->patchJson("/api/crm/cases/{$id}", ['status' => 'resolved'])->assertOk()->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull(DB::table('support_tickets')->value('resolved_at'));
        $this->as($other)->getJson("/api/crm/cases/{$id}")->assertNotFound();
    }

    public function test_the_shop_can_record_a_case_and_permissions_hold(): void
    {
        $shop = $this->shop('Petal & Stem');
        $ana = User::create(['name' => 'Ana', 'surname' => 'Reyes', 'email' => 'ana@example.com', 'password' => 'x', 'role' => 'customer']);
        $order = $this->order($shop, ['user_id' => $ana->id, 'order_number' => 'ORD-90']);
        $stranger = User::create(['name' => 'Zed', 'surname' => 'X', 'email' => 'zed@example.com', 'password' => 'x', 'role' => 'customer']);

        $this->as($shop)->postJson('/api/crm/cases', ['customer_id' => $stranger->id, 'subject' => 'Late delivery', 'category' => 'delivery', 'priority' => 'normal', 'message' => 'Called to say it was two hours late.'])
            ->assertNotFound();
        $this->postJson('/api/crm/cases', ['customer_id' => $ana->id, 'subject' => 'Late delivery', 'category' => 'delivery', 'priority' => 'high', 'message' => 'Called to say it was two hours late.', 'order_id' => $order])
            ->assertCreated()->assertJsonPath('data.recorded_by_shop', true)->assertJsonPath('data.status', 'open');

        // It shows on the customer's Help page as handled by the shop
        $this->as($ana)->getJson('/api/support/tickets')->assertOk()->assertJsonPath('data.0.handled_by', 'Petal & Stem');

        // Read-only staff can look but not reply or change things
        $viewer = $this->staff($shop, 'Vic', ['view']);
        $id = DB::table('support_tickets')->value('id');
        $this->as($viewer)->getJson("/api/crm/cases/{$id}")->assertOk();
        $this->postJson("/api/crm/cases/{$id}/reply", ['message' => 'Hello'])->assertForbidden();
        $this->patchJson("/api/crm/cases/{$id}", ['status' => 'closed'])->assertForbidden();

        // Shop accounts can't use the complaint topics on their own Help page
        $this->as($shop)->postJson('/api/support/tickets', ['subject' => 'Something', 'category' => 'complaint', 'message' => 'Testing the shop topics.'])->assertStatus(422);
    }
}
