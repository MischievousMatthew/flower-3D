<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'conversations',
        'messages',
        'shared_files',
        'employee_assignments',
        'employee_module_permissions',
        'product_images',
        'product_models',
        'supplier_contacts',
        'purchase_order_items',
        'shipment_items',
        'warehouse_batch_logs',
        'deliveries',
        'delivery_logs',
        'order_requests',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (!Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
                $table->index('owner_id');
            });
        }

        $this->backfill('conversations', function () {
            DB::table('conversations')
                ->whereNull('owner_id')
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('conversations')->where('id', $row->id)->update(['owner_id' => $row->vendor_id]);
                    }
                });
        });

        $this->backfill('messages', function () {
            DB::table('messages as m')
                ->join('conversations as c', 'c.id', '=', 'm.conversation_id')
                ->whereNull('m.owner_id')
                ->select('m.id', 'c.vendor_id')
                ->orderBy('m.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('messages')->where('id', $row->id)->update(['owner_id' => $row->vendor_id]);
                    }
                });
        });

        $this->backfill('shared_files', function () {
            DB::table('shared_files as sf')
                ->join('conversations as c', 'c.id', '=', 'sf.conversation_id')
                ->whereNull('sf.owner_id')
                ->select('sf.id', 'c.vendor_id')
                ->orderBy('sf.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('shared_files')->where('id', $row->id)->update(['owner_id' => $row->vendor_id]);
                    }
                });
        });

        $this->backfill('employee_assignments', function () {
            DB::table('employee_assignments as ea')
                ->join('employees as e', 'e.id', '=', 'ea.employee_id')
                ->whereNull('ea.owner_id')
                ->select('ea.id', 'e.owner_id')
                ->orderBy('ea.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('employee_assignments')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('employee_module_permissions', function () {
            DB::table('employee_module_permissions as emp')
                ->join('employees as e', 'e.id', '=', 'emp.employee_id')
                ->whereNull('emp.owner_id')
                ->select('emp.id', 'e.owner_id')
                ->orderBy('emp.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('employee_module_permissions')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('product_images', function () {
            DB::table('product_images as pi')
                ->join('products as p', 'p.id', '=', 'pi.product_id')
                ->whereNull('pi.owner_id')
                ->select('pi.id', 'p.owner_id')
                ->orderBy('pi.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('product_images')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('product_models', function () {
            DB::table('product_models as pm')
                ->join('products as p', 'p.id', '=', 'pm.product_id')
                ->whereNull('pm.owner_id')
                ->select('pm.id', 'p.owner_id')
                ->orderBy('pm.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('product_models')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('supplier_contacts', function () {
            DB::table('supplier_contacts as sc')
                ->join('suppliers as s', 's.id', '=', 'sc.supplier_id')
                ->whereNull('sc.owner_id')
                ->select('sc.id', 's.owner_id')
                ->orderBy('sc.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('supplier_contacts')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('purchase_order_items', function () {
            DB::table('purchase_order_items as poi')
                ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
                ->whereNull('poi.owner_id')
                ->select('poi.id', 'po.owner_id')
                ->orderBy('poi.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('purchase_order_items')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('shipment_items', function () {
            DB::table('shipment_items as si')
                ->join('shipments as s', 's.id', '=', 'si.shipment_id')
                ->whereNull('si.owner_id')
                ->select('si.id', 's.owner_id')
                ->orderBy('si.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('shipment_items')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('warehouse_batch_logs', function () {
            DB::table('warehouse_batch_logs as wbl')
                ->join('warehouse_batches as wb', 'wb.id', '=', 'wbl.warehouse_batch_id')
                ->whereNull('wbl.owner_id')
                ->select('wbl.id', 'wb.owner_id')
                ->orderBy('wbl.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('warehouse_batch_logs')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('deliveries', function () {
            DB::table('deliveries as d')
                ->join('orders as o', 'o.id', '=', 'd.order_id')
                ->whereNull('d.owner_id')
                ->select('d.id', 'o.vendor_id')
                ->orderBy('d.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('deliveries')->where('id', $row->id)->update(['owner_id' => $row->vendor_id]);
                    }
                });
        });

        $this->backfill('delivery_logs', function () {
            DB::table('delivery_logs as dl')
                ->join('deliveries as d', 'd.id', '=', 'dl.delivery_id')
                ->whereNull('dl.owner_id')
                ->select('dl.id', 'd.owner_id')
                ->orderBy('dl.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('delivery_logs')->where('id', $row->id)->update(['owner_id' => $row->owner_id]);
                    }
                });
        });

        $this->backfill('order_requests', function () {
            DB::table('order_requests as orq')
                ->join('orders as o', 'o.id', '=', 'orq.order_id')
                ->whereNull('orq.owner_id')
                ->select('orq.id', 'o.vendor_id')
                ->orderBy('orq.id')
                ->chunk(500, function ($rows) {
                    foreach ($rows as $row) {
                        DB::table('order_requests')->where('id', $row->id)->update(['owner_id' => $row->vendor_id]);
                    }
                });
        });
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'owner_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('owner_id');
            });
        }
    }

    private function backfill(string $tableName, callable $callback): void
    {
        if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'owner_id')) {
            return;
        }

        $callback();
    }
};
