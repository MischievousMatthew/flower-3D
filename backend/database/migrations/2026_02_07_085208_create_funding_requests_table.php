<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('funding_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('finance_request_id')->unique();
            
            // Request Identity
            $table->foreignId('submitted_by_employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('accounting_manager_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->string('related_sales_order_id')->nullable();
            $table->date('request_date');
            $table->enum('request_status', ['Draft', 'Pending', 'Approved', 'Rejected'])->default('Draft');
            
            // Product Details (some nullable for drafts)
            $table->string('product_name')->nullable();
            $table->enum('flower_category', ['Fresh', 'Dried', 'Artificial'])->nullable();
            $table->enum('variant', ['Premium', 'Grade A', 'Grade B', 'Standard'])->nullable();
            $table->integer('requested_qty')->nullable();
            $table->enum('uom', ['Stems', 'Bunches', 'Boxes'])->nullable();
            $table->integer('moq')->nullable();
            $table->string('preferred_supplier')->nullable();
            $table->text('alternative_suppliers')->nullable();
            $table->date('required_delivery_date')->nullable();
            
            // Inventory Context (nullable for drafts)
            $table->integer('current_stock')->nullable();
            $table->integer('reserved_stock')->nullable();
            $table->integer('net_available_stock')->nullable();
            $table->integer('required_quantity')->nullable();
            $table->integer('stock_shortage_qty')->nullable();
            $table->integer('incoming_stock')->default(0);
            $table->enum('reason_for_shortage', ['New Order', 'Spoilage', 'Forecast Error', 'Seasonal Demand', 'Supplier Delay'])->nullable();
            
            // Financial Details (nullable for drafts)
            $table->decimal('estimated_unit_cost', 10, 2)->nullable();
            $table->decimal('estimated_total_cost', 10, 2)->nullable();
            $table->string('currency')->default('PHP');
            $table->enum('payment_terms', ['Cash', '7 Days', '15 Days', '30 Days'])->nullable();
            $table->decimal('expected_selling_price', 10, 2)->nullable();
            $table->decimal('expected_revenue', 10, 2)->nullable();
            $table->decimal('estimated_gross_margin', 5, 2)->nullable();
            $table->decimal('tax_vat_estimate', 10, 2)->nullable();
            $table->decimal('logistics_cost', 10, 2)->nullable();
            
            // Risk & Urgency (nullable for drafts)
            $table->enum('urgency_level', ['Normal', 'Urgent', 'Critical'])->nullable();
            $table->integer('shelf_life')->nullable();
            $table->decimal('expected_spoilage', 5, 2)->nullable();
            $table->decimal('missed_sales_impact', 10, 2)->nullable();
            $table->string('seasonal_tag')->nullable();
            $table->enum('demand_confidence', ['High', 'Medium', 'Low'])->nullable();
            
            // Finance Recommendation (nullable for drafts)
            $table->enum('finance_recommendation', ['Approve', 'Approve Partial', 'Reject'])->nullable();
            $table->integer('recommended_qty')->nullable();
            $table->decimal('recommended_budget', 10, 2)->nullable();
            $table->decimal('price_ceiling', 10, 2)->nullable();
            $table->string('suggested_supplier')->nullable();
            
            // Decision Brief (nullable for drafts)
            $table->string('business_justification', 150)->nullable();
            $table->string('approval_impact', 150)->nullable();
            $table->string('rejection_risk', 150)->nullable();
            $table->text('additional_notes')->nullable();
            
            // Accounting Decision
            $table->timestamp('submitted_to_accounting_at')->nullable();
            $table->timestamp('accounting_decision_at')->nullable();
            $table->foreignId('reviewed_by_employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->integer('approved_quantity')->nullable();
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->text('accounting_remarks')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->text('rejection_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['owner_id', 'request_status']);
            $table->index(['submitted_by_employee_id']);
            $table->index(['accounting_manager_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('funding_requests');
    }
};