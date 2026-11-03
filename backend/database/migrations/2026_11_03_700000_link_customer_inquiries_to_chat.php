<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ask the shop" on a product or order sends the question in chat. It is
 * also kept as a customer question (CRM → Questions), linked to the chat, so
 * answering in either place reaches the customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_inquiries', 'conversation_id')) {
                $table->unsignedBigInteger('conversation_id')->nullable()->index();
            }
            if (! Schema::hasColumn('customer_inquiries', 'message_id')) {
                $table->unsignedBigInteger('message_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_inquiries', function (Blueprint $table) {
            $table->dropColumn(['conversation_id', 'message_id']);
        });
    }
};
