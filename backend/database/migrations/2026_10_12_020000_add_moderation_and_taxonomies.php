<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BloomCraft's moderation of a listing, separate from the vendor's
        // own status (draft/active/inactive) so vendors keep control of theirs.
        Schema::table('products', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('approved')->index();
            $table->text('moderation_reason')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('product_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20); // approved | hidden | removed | restored | resubmitted
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        // Products banned through a report used to have their vendor status
        // overwritten with "banned"; move that onto moderation instead.
        DB::table('products')->where('status', 'banned')->update([
            'status' => 'inactive',
            'moderation_status' => 'removed',
            'moderation_reason' => 'Removed after a customer report was upheld.',
            'moderated_at' => now(),
        ]);

        // Admin-managed choices for the vendor product forms
        Schema::create('product_taxonomies', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // category | flower_type | occasion
            $table->string('value', 80); // stored on products; never changes
            $table->string('label', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index(['type', 'is_active', 'sort_order']);
        });

        $now = now();
        $seed = [
            'category' => [
                'roses' => 'Roses', 'tulips' => 'Tulips', 'lilies' => 'Lilies', 'orchids' => 'Orchids',
                'sunflowers' => 'Sunflowers', 'mixed-bouquets' => 'Mixed Bouquets', 'arrangements' => 'Arrangements',
                'plants' => 'Plants', 'gifts' => 'Gifts & Add-ons', 'seasonal' => 'Seasonal Flowers',
            ],
            'flower_type' => [
                'focal' => 'Focal Flowers', 'secondary' => 'Secondary Flowers', 'filler' => 'Filler Flowers',
                'line' => 'Line Flowers', 'greenery' => 'Greenery',
            ],
            'occasion' => [
                'Wedding' => 'Wedding', 'Birthday' => 'Birthday', 'Anniversary' => 'Anniversary', 'Funeral' => 'Funeral',
                'Get Well' => 'Get Well', 'Congratulations' => 'Congratulations', 'Sympathy' => 'Sympathy',
                'Valentine' => 'Valentine', "Mother's Day" => "Mother's Day", 'Christmas' => 'Christmas',
                'Corporate' => 'Corporate', 'Just Because' => 'Just Because',
            ],
        ];
        $descriptions = [
            'focal' => 'Main attraction', 'secondary' => 'Support & volume', 'filler' => 'Small texture',
            'line' => 'Height & direction', 'greenery' => 'Foliage',
        ];

        $rows = [];
        foreach ($seed as $type => $values) {
            $order = 0;
            foreach ($values as $value => $label) {
                $rows[] = [
                    'type' => $type, 'value' => $value, 'label' => $label,
                    'description' => $type === 'flower_type' ? $descriptions[$value] : null,
                    'is_active' => true, 'sort_order' => ++$order, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('product_taxonomies')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_taxonomies');
        Schema::dropIfExists('product_moderation_events');
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['moderation_status', 'moderation_reason', 'moderated_at']);
        });
    }
};
