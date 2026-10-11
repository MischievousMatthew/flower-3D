<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Message cards for the 3D flower customizer: a photo of the vendor's card,
 * how much the customer may write (a word or letter cap), and the sizes the
 * card comes in, each with its own price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            $table->text('image_url')->nullable()->after('model_path');
            $table->string('image_path')->nullable()->after('image_url');
            $table->string('card_limit_type', 10)->nullable()->after('image_path'); // words | letters
            $table->unsignedInteger('card_limit')->nullable()->after('card_limit_type');
            $table->json('card_sizes')->nullable()->after('card_limit'); // [{label, price}]
        });
    }

    public function down(): void
    {
        Schema::table('bouquet_customization_options', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'image_path', 'card_limit_type', 'card_limit', 'card_sizes']);
        });
    }
};
