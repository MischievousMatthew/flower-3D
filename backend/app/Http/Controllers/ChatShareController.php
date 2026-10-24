<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\ProductImage;
use App\Services\CustomerRecords;
use App\Services\NotificationService;
use App\Traits\ResolvesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Chat messages that are more than text, sent by the shop:
 * - an order update ("On its way", "Running late"…) shown as an order card,
 *   with a notification to the customer. It only messages the customer; it
 *   never changes the order itself.
 * - a product, shown as a card that opens the product page.
 */
class ChatShareController extends Controller
{
    use ResolvesOwner;

    /** Update kinds: [card title, notification title] */
    public const UPDATES = [
        'confirmed' => ['Order confirmed', 'Your order is confirmed'],
        'preparing' => ['Being prepared', 'Your flowers are being prepared'],
        'ready' => ['Ready for pickup', 'Your order is ready for pickup'],
        'on_the_way' => ['On its way', 'Your order is on its way'],
        'delivered' => ['Delivered', 'Your order was delivered'],
        'delayed' => ['Running a little late', 'Your order is running a little late'],
        'note' => ['Order update', 'An update on your order'],
    ];

    public function __construct(private CustomerRecords $records, private NotificationService $notifications) {}

    /** GET /chat/conversations/{id}/shareables?search — the customer's orders here, and products to share */
    public function options(Request $request, int $id): JsonResponse
    {
        $request->validate(['search' => 'nullable|string|max:100']);
        [$vendorId, $conversation] = $this->conversation($request, $id);

        $orders = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $conversation->customer_id)
            ->orderByDesc('created_at')->limit(20)
            ->get(['id', 'order_number', 'status', 'total_amount', 'created_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'total' => round((float) $o->total_amount, 2),
                'created_at' => $o->created_at?->toIso8601String(),
            ]);

        $search = trim((string) $request->input('search', ''));
        $products = $this->records->products($vendorId)->whereNull('deleted_at')
            ->when($search !== '', fn ($q) => $q->where('product_name', 'like', '%' . $search . '%'))
            ->orderBy('product_name')->limit(12)->get(['id', 'product_name', 'price', 'selling_price'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->product_name, 'price' => (float) ($p->selling_price ?? $p->price ?? 0), 'image' => $this->imageOf($p->id)]);

        return response()->json(['success' => true, 'data' => [
            'orders' => $orders,
            'products' => $products,
            'updates' => collect(self::UPDATES)->map(fn ($t, $key) => ['value' => $key, 'label' => $t[0]])->values(),
        ]]);
    }

    /** POST /chat/conversations/{id}/order-update {order_id, update, note} */
    public function orderUpdate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer',
            'update' => 'required|in:' . implode(',', array_keys(self::UPDATES)),
            'note' => 'nullable|string|max:500',
        ]);
        [$vendorId, $conversation] = $this->conversation($request, $id);
        $order = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $conversation->customer_id)->findOrFail($data['order_id']);
        $note = trim((string) ($data['note'] ?? ''));
        if ($data['update'] === 'note' && $note === '') {
            return response()->json(['success' => false, 'errors' => ['note' => ['Write the update for the customer.']]], 422);
        }

        [$title, $notice] = self::UPDATES[$data['update']];
        $text = "{$order->order_number}: {$title}" . ($note !== '' ? " — {$note}" : '');
        $message = $this->post($request, $conversation, 'order_update', $text, [
            'kind' => 'order',
            'update' => $data['update'],
            'title' => $title,
            'note' => $note ?: null,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'total' => round((float) $order->total_amount, 2),
        ]);

        $this->notifications->notify([$conversation->customer_id], 'order_update', $notice, $text, '/customer/orders', $data['update'] === 'delayed');

        return response()->json(['success' => true, 'message' => 'Update sent.', 'data' => $this->payload($message)]);
    }

    /** POST /chat/conversations/{id}/share-product {product_id, note} */
    public function shareProduct(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|integer', 'note' => 'nullable|string|max:500']);
        [$vendorId, $conversation] = $this->conversation($request, $id);
        $product = $this->records->products($vendorId)->whereNull('deleted_at')->findOrFail($data['product_id']);
        $note = trim((string) ($data['note'] ?? ''));
        $price = (float) ($product->selling_price ?? $product->price ?? 0);

        $message = $this->post($request, $conversation, 'product', $note !== '' ? $note : $product->product_name, [
            'kind' => 'product',
            'product_id' => $product->id,
            'name' => $product->product_name,
            'price' => $price,
            'image' => $this->imageOf($product->id),
        ]);

        return response()->json(['success' => true, 'message' => 'Product shared.', 'data' => $this->payload($message)]);
    }

    /** Saves the message the same way a typed one is: unread for the customer, latest in the list */
    private function post(Request $request, Conversation $conversation, string $type, string $text, array $meta): Message
    {
        $message = Message::create([
            'owner_id' => $conversation->vendor_id,
            'conversation_id' => $conversation->id,
            'sender_id' => $conversation->vendor_id,
            'message' => $text,
            'message_type' => $type,
            'meta' => $meta,
            'is_read' => false,
        ]);

        $conversation->increment('unread_count_customer');
        $conversation->update([
            'last_message' => Str::limit($text, 50),
            'last_message_time' => now(),
            'last_message_sender_id' => $conversation->vendor_id,
            // A new message brings an archived chat back for the customer
            'customer_archived_at' => null,
        ]);

        return $message;
    }

    private function payload(Message $m): array
    {
        return [
            'id' => $m->id,
            'sender_id' => $m->sender_id,
            'text' => $m->message,
            'created_at' => $m->created_at?->copy()->setTimezone('Asia/Manila')->toIso8601String(),
            'time' => $m->created_at?->diffForHumans(),
            'read' => false,
            'type' => $m->message_type,
            'attachments' => [],
            'meta' => $m->meta,
            'is_own' => true,
        ];
    }

    /** [vendorId, conversation] — only the shop's own conversations */
    private function conversation(Request $request, int $id): array
    {
        $vendorId = $this->resolveOwnerId($request);
        $conversation = Conversation::withoutGlobalScopes()->where('vendor_id', $vendorId)->findOrFail($id);

        return [$vendorId, $conversation];
    }

    private function imageOf(int $productId): ?string
    {
        $img = ProductImage::withoutGlobalScopes()->where('product_id', $productId)->orderByDesc('is_primary')->orderBy('display_order')->first(['image_url']);

        return $img?->image_url;
    }
}
