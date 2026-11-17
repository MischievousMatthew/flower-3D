<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finance and Procurement agree on how much to buy. When Finance can only
 * fund part of a request (75 of 100 roses), the request goes back to
 * Procurement ("Countered") with Finance's offer. Procurement accepts it, or
 * asks for what they really need (80), which sends it back to Finance. Each
 * turn is a row in funding_request_rounds.
 */
return new class extends Migration
{
    private const STATUSES = ['Draft', 'Pending', 'Countered', 'Approved', 'Rejected', 'Cancelled'];

    public function up(): void
    {
        // request_status is an enum (a CHECK constraint on Postgres): allow "Countered"
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn ($s) => "'{$s}'", self::STATUSES));
            DB::statement('ALTER TABLE funding_requests DROP CONSTRAINT IF EXISTS funding_requests_request_status_check');
            DB::statement("ALTER TABLE funding_requests ADD CONSTRAINT funding_requests_request_status_check CHECK (request_status::text IN ({$list}))");
        }

        Schema::table('funding_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('funding_requests', 'offered_amount')) {
                // The budget Finance offered with its counter-offer
                $table->decimal('offered_amount', 12, 2)->nullable();
            }
        });

        Schema::table('funding_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('funding_request_items', 'offered_quantity')) {
                // How many Finance can fund, while Procurement decides
                $table->integer('offered_quantity')->nullable();
            }
        });

        if (! Schema::hasTable('funding_request_rounds')) {
            Schema::create('funding_request_rounds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('funding_request_id')->constrained('funding_requests')->cascadeOnDelete();
                // finance: offered less | procurement: asked again | accepted: Procurement took the offer | approved: Finance agreed
                $table->string('kind', 20);
                $table->string('actor_name')->nullable();
                // [{item_id, product_name, uom, quantity}]
                $table->json('items')->nullable();
                $table->decimal('amount', 12, 2)->nullable();
                $table->string('note', 500)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funding_request_rounds');
        if (Schema::hasColumn('funding_request_items', 'offered_quantity')) {
            Schema::table('funding_request_items', fn (Blueprint $t) => $t->dropColumn('offered_quantity'));
        }
        if (Schema::hasColumn('funding_requests', 'offered_amount')) {
            Schema::table('funding_requests', fn (Blueprint $t) => $t->dropColumn('offered_amount'));
        }
    }
};
