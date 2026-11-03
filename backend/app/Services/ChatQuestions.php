<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CustomerInquiry;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Questions customers send from "Ask the shop" (a product or an order) land
 * in chat. They are also kept as customer questions (CRM → Questions), linked
 * to that chat, so the shop can answer from either place:
 *  - answered on the Questions page → the answer is posted in the chat too
 *  - answered in the chat → the question is marked answered with that reply
 */
class ChatQuestions
{
    /** Keep a customer's chat question about a product or order as a customer question */
    public static function recordQuestion(Conversation $conversation, Message $message, array $about, string $text): ?CustomerInquiry
    {
        if (! self::ready() || trim($text) === '') {
            return null;
        }
        try {
            return CustomerInquiry::create([
                'vendor_id' => $conversation->vendor_id,
                'customer_id' => $conversation->customer_id,
                'product_id' => $about['kind'] === 'product_question' ? ($about['product_id'] ?? null) : null,
                'order_id' => $about['kind'] === 'order_question' ? ($about['order_id'] ?? null) : null,
                'question' => Str::limit(trim($text), 1000, ''),
                'status' => 'open',
                // Asked privately in chat: shown on the product page only if the shop chooses
                'is_public' => false,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
            ]);
        } catch (\Throwable $e) {
            // The chat message is already sent; never fail it over this
            Log::warning('Chat question not kept as a customer question', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** The shop replied in chat: questions still waiting in that chat are answered by it */
    public static function answeredInChat(Conversation $conversation, string $reply, string $answeredBy): void
    {
        if (! self::ready() || trim($reply) === '') {
            return;
        }
        try {
            CustomerInquiry::query()
                ->where('conversation_id', $conversation->id)
                ->where('status', 'open')
                ->update([
                    'answer' => Str::limit(trim($reply), 2000, ''),
                    'answered_by' => Str::limit($answeredBy, 117),
                    'answered_at' => now(),
                    'status' => 'answered',
                    // Read in the chat already
                    'customer_seen_at' => now(),
                    'updated_at' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Chat reply not recorded as an answer', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
        }
    }

    /** Answered on the Questions page: the customer sees it in the chat they asked in */
    public static function postAnswer(CustomerInquiry $inquiry, string $answer): bool
    {
        if (! $inquiry->conversation_id) {
            return false;
        }
        $conversation = Conversation::find($inquiry->conversation_id);
        if (! $conversation) {
            return false;
        }
        try {
            Message::create([
                'owner_id' => $conversation->vendor_id,
                'conversation_id' => $conversation->id,
                'sender_id' => $conversation->vendor_id,
                'message' => Str::limit(trim($answer), 1000, ''),
                'message_type' => 'text',
                'is_read' => false,
            ]);
            $conversation->increment('unread_count_customer');
            $conversation->update([
                'last_message' => Str::limit(trim($answer), 50),
                'last_message_time' => now(),
                'last_message_sender_id' => $conversation->vendor_id,
                'is_active' => true,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Answer not posted in chat', ['inquiry_id' => $inquiry->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private static function ready(): bool
    {
        static $ready = false;

        return $ready = $ready || (Schema::hasTable('customer_inquiries') && Schema::hasColumn('customer_inquiries', 'conversation_id'));
    }
}
