<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SharedFile;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Helpers\CloudinaryHelper;

class CustomerChatController extends Controller
{
    public function getCustomerConversations(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->isCustomer()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $conversations = Conversation::with([
                    'vendor:id,name,surname,email,profile_picture,contact_number,username,vendor_data'
                ])
                ->where('customer_id', $user->id)
                ->where('is_active', true)
                ->withCount([
                    'messages as unread_count' => fn ($q) =>
                        $q->where('sender_id', '!=', $user->id)
                        ->where('is_read', false)
                ])
                ->orderByDesc('last_message_time')
                ->get()
                ->map(function ($conv) {
                    if (!$conv->vendor) {
                        return null;
                    }

                    return [
                        'id' => $conv->id,
                        'vendor' => [
                            'id' => $conv->vendor->id,
                            'name' => $conv->vendor->full_name,
                            'email' => $conv->vendor->email,
                            'avatar' => $conv->vendor->avatar_url,
                            'online' => $conv->vendor->is_online,
                            'contact_number' => $conv->vendor->contact_number,
                            'username' => $conv->vendor->username,
                            'store_name' => $this->resolveVendorStoreName($conv->vendor),
                        ],
                        'last_message' => $conv->last_message
                            ? Str::limit($conv->last_message, 50)
                            : '',
                        'last_message_time' => $conv->last_message_time
                            ? $conv->last_message_time->diffForHumans()
                            : 'No messages',
                        'unread_count' => $conv->unread_count,
                        'is_active' => $conv->is_active,
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'conversations' => $conversations,
                'total_unread' => $conversations->sum('unread_count'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error in getCustomerConversations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function getVendorDetails(int $vendorId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user->isCustomer()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $vendor = User::findOrFail($vendorId);
            if (!$vendor->isVendor()) {
                return response()->json(['success' => false, 'message' => 'User is not a vendor'], 400);
            }
            
            $conversation = Conversation::where('customer_id', $user->id)->where('vendor_id', $vendorId)->first();
            $sharedFiles = $conversation 
                ? $conversation->sharedFiles()->orderBy('created_at', 'desc')->limit(10)->get()
                    ->map(fn($file) => [
                        'name' => $file->file_name,
                        'url' => CloudinaryHelper::getUrl($file->file_path, 'auto'),
                        'type' => $file->file_type,
                        'size' => $this->formatFileSize($file->file_size),
                        'icon' => $this->getFileIcon($file->file_type),
                    ])
                : collect();
            
            return response()->json([
                'success' => true,
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->full_name,
                    'email' => $vendor->email,
                    'contact_number' => $vendor->contact_number,
                    'avatar' => $vendor->avatar_url,
                    'online' => $vendor->is_online,
                    'address' => $vendor->address . ', ' . $vendor->city,
                    'store_name' => $this->resolveVendorStoreName($vendor),
                    'shared_files' => $sharedFiles,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getVendorDetails', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function startConversation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_id' => 'required|exists:users,id',
                'message' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }
            
            $customer = Auth::user();
            if (!$customer->isCustomer()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $vendor = User::findOrFail($request->vendor_id);
            if (!$vendor->isVendor()) {
                return response()->json(['success' => false, 'message' => 'You can only start conversations with vendors'], 400);
            }
            
            $conversation = Conversation::firstOrCreate(
                ['vendor_id' => $vendor->id, 'customer_id' => $customer->id],
                ['owner_id' => $vendor->id, 'is_active' => true]
            );
            
            $message = Message::create([
                'owner_id' => $vendor->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $customer->id,
                'message' => $request->message,
                'message_type' => 'text',
            ]);
            
            $conversation->update([
                'last_message' => $message->message,
                'last_message_time' => now(),
                'unread_count_vendor' => $conversation->unread_count_vendor + 1,
                'is_active' => true,
            ]);
            
            return response()->json([
                'success' => true,
                'conversation' => ['id' => $conversation->id],
                'message' => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'text' => $message->message,
                    'time' => $message->created_at->format('H:i'),
                    'sender_id' => $message->sender_id,
                    'is_own' => true, // ✅ customer is sending
                    'read' => false,
                    'attachments' => [],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in startConversation', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function searchVendors(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', '');
            $matchingVendorEmails = VendorApplication::query()
                ->where('store_name', 'like', "%{$search}%")
                ->pluck('email')
                ->filter()
                ->values()
                ->all();

            $vendors = User::where('role', User::ROLE_VENDOR)
                ->where('is_verified', true)
                ->where(fn($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhereIn('email', $matchingVendorEmails))
                ->select('id', 'name', 'surname', 'email', 'profile_picture', 'contact_number', 'username', 'vendor_data')
                ->limit(20)
                ->get()
                ->map(fn($vendor) => [
                    'id' => $vendor->id,
                    'name' => $vendor->full_name,
                    'email' => $vendor->email,
                    'contact_number' => $vendor->contact_number,
                    'avatar' => $vendor->avatar_url,
                    'username' => $vendor->username,
                    'store_name' => $this->resolveVendorStoreName($vendor),
                    'display_name' => $this->resolveVendorStoreName($vendor),
                ]);
            
            return response()->json(['success' => true, 'vendors' => $vendors]);
        } catch (\Exception $e) {
            Log::error('Error in searchVendors', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    private function getFileIcon(string $fileType): string
    {
        return match (true) {
            str_contains($fileType, 'image') => '🖼️',
            str_contains($fileType, 'pdf') => '📄',
            str_contains($fileType, 'document') => '📝',
            str_contains($fileType, 'spreadsheet') => '📊',
            str_contains($fileType, 'archive') => '📦',
            default => '📎',
        };
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function resolveVendorStoreName(User $vendor): string
    {
        $storeName = data_get($vendor->vendor_data, 'store_name');
        if (is_string($storeName) && trim($storeName) !== '') {
            return trim($storeName);
        }

        $applicationStoreName = VendorApplication::query()
            ->where('email', $vendor->email)
            ->value('store_name');

        if (is_string($applicationStoreName) && trim($applicationStoreName) !== '') {
            return trim($applicationStoreName);
        }

        return 'Vendor Store';
    }
}
