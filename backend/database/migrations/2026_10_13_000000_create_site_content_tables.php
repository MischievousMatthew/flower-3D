<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Website content admins manage and the shop / landing page show:
 * homepage banners, featured products, journal articles, announcements
 * and promotions, and the site's contact details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_banners', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow', 60)->nullable();
            $table->string('title', 140);
            $table->string('subtitle', 300)->nullable();
            $table->text('image_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('button_label', 40)->nullable();
            // Where the button goes: products | category | occasion | store | article | url
            $table->string('link_type', 20)->default('products');
            $table->string('link_value')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('featured_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('category', 30)->default('care'); // care | guide | trends | stories | news
            $table->string('excerpt', 300)->nullable();
            $table->longText('body');
            $table->text('cover_url')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft | published
            $table->timestamp('published_at')->nullable()->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20)->default('announcement'); // announcement | promotion
            $table->string('title', 120);
            $table->string('message', 300)->nullable();
            $table->string('promo_code', 40)->nullable(); // shown to customers; applied by the vendor
            $table->text('image_url')->nullable();
            $table->string('image_path')->nullable();
            $table->string('link_label', 40)->nullable();
            $table->string('link_type', 20)->nullable();
            $table->string('link_value')->nullable();
            $table->string('tone', 20)->default('brand'); // brand | info | success | warning
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Today's landing page text, so nothing changes until an admin edits it
        $now = now();
        $defaults = [
            'site_name' => 'BloomCraft',
            'tagline' => 'Your marketplace for custom flower arrangements',
            'about' => 'Your marketplace for custom flower arrangements. Connect with local vendors and design the perfect bouquet.',
            'contact_email' => null,
            'contact_phone' => null,
            'address' => null,
            'business_hours' => null,
            'socials' => ['facebook' => null, 'instagram' => null, 'tiktok' => null, 'x' => null],
            'footer_note' => null,
        ];
        DB::table('site_settings')->insert(collect($defaults)->map(fn ($value, $key) => [
            'key' => $key, 'value' => json_encode($value), 'created_at' => $now, 'updated_at' => $now,
        ])->values()->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('featured_products');
        Schema::dropIfExists('site_banners');
    }
};
