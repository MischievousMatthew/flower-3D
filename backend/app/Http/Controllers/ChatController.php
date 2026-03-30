<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    /**
     * Get conversations for authenticated user (vendor or customer)
     */
    public function getConversations(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $isVendor = $user->isVendor();
            $search = $request->input('search', '');
            $filter = $request->input('filter', 'all');

            // Build query based on user role
            $query = Conversation::query()
                ->where($isVendor ? 'vendor_id' : 'customer_id', $user->id)
                ->where('is_active', true);

            // Load the other participant
            if ($isVendor) {
                $query->with(['customer:id,name,surname,email,profile_picture,contact_number,username']);
            } else {
                $query->with(['vendor:id,name,surname,email,profile_picture,contact_number,username,vendor_data']);
            }

            // Count unread messages for this user
            $query->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)->where('is_read', false);
            }]);

            // Filter by unread
            if ($filter === 'unread') {
                $query->having('unread_count', '>', 0);
            }

            // Search
            if ($search) {
                $otherUserTable = $isVendor ? 'customer' : 'vendor';
                $query->whereHas($otherUserTable, function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('surname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            $conversations = $query->orderBy('last_message_time', 'desc')
                ->get()
                ->map(function ($c) use ($isVendor) {
                    $otherUser = $isVendor ? $c->customer : $c->vendor;
                    
                    if (!$otherUser) return null;

                    $userData = [
                        'id' => $otherUser->id,
                        'name' => $otherUser->full_name,
                        'email' => $otherUser->email,
                        'avatar' => $otherUser->avatar_url,
                        'online' => $otherUser->is_online,
                        'contact_number' => $otherUser->contact_number,
                        'username' => $otherUser->username,
                        'display_name' => $otherUser->full_name,
                    ];

                    // Add store_name for vendors
                    if (!$isVendor && isset($otherUser->vendor_data['store_name'])) {
                        $userData['store_name'] = $otherUser->vendor_data['store_name'];
                        $userData['display_name'] = $otherUser->vendor_data['store_name'];
                    }

                    return [
                        'id' => $c->id,
                        $isVendor ? 'customer' : 'vendor' => $userData,
                        'last_message' => $c->last_message ? Str::limit($c->last_message, 50) : '',
                        'last_message_time' => $c->last_message_time ? $c->last_message_time->diffForHumans() : 'No messages',
                        'unread_count' => $c->unread_count,
                        'is_active' => $c->is_active,
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'conversations' => $conversations,
                'total_unread' => $conversations->sum('unread_count'),
            ]);
        } catch (\Exception $e) {
            Log::error('getConversations error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * Get messages for a conversation
     */
    public function getMessages(int $conversationId): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $conversation = Conversation::find($conversationId);
            
            if (!$conversation) {
                return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
            }

            // Check authorization
            if ($user->id !== $conversation->vendor_id && $user->id !== $conversation->customer_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            // Get messages
            $messages = Message::where('conversation_id', $conversationId)
                ->with('sender:id,name,surname,profile_picture')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($m) use ($user) {
                    $attachments = [];
                    
                    if (!empty($m->attachments)) {
                        if (is_array($m->attachments)) {
                            $attachments = $m->attachments;
                        } elseif (is_string($m->attachments) && json_decode($m->attachments) !== null) {
                            $attachments = json_decode($m->attachments, true);
                        }
                    }

                    return [
                        'id' => $m->id,
                        'sender_id' => $m->sender_id,
                        'sender_name' => $m->sender->full_name ?? 'Unknown',
                        'sender_avatar' => $m->sender->avatar_url ?? null,
                        'text' => $m->message,
                        'time' => $m->created_at->diffForHumans(),
                        'read' => (bool) $m->is_read,
                        'type' => $m->message_type ?? 'text',
                        'attachments' => $attachments,
                        'is_own' => $m->sender_id === $user->id,
                    ];
                });

            // Mark messages as read
            Message::where('conversation_id', $conversationId)
                ->where('sender_id', '!=', $user->id)
                ->update(['is_read' => true, 'read_at' => now()]);

            // Reset unread count
            if ($user->id === $conversation->vendor_id) {
                $conversation->unread_count_vendor = 0;
            } else {
                $conversation->unread_count_customer = 0;
            }
            $conversation->save();

            return response()->json([
                'success' => true,
                'messages' => $messages,
                'conversation_id' => $conversationId,
            ]);

        } catch (\Exception $e) {
            Log::error('getMessages error', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Server error'
            ], 500);
        }
    }

    /**
     * Send a message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'conversation_id' => 'required|exists:conversations,id',
                'message' => 'nullable|string|max:1000',
                'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,gif,bmp,webp,pdf,doc,docx,txt|max:5120',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false, 
                    'errors' => $validator->errors(),
                ], 422);
            }

            /** @var User $user */
            $user = Auth::user();
            $conversation = Conversation::find($request->conversation_id);

            // Check authorization
            if ($user->id !== $conversation->vendor_id && $user->id !== $conversation->customer_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $attachments = [];
            $messageText = trim($request->input('message', ''));
            
            // ── Handle file uploads to Cloudinary ────────────────────────────────
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        try {
                            $result = cloudinary()->upload($file->getRealPath(), [
                                'folder'        => 'chat/attachments/' . date('Y/m'),
                                'resource_type' => 'auto',
                            ]);

                            $attachments[] = [
                                'url'  => $result->getSecurePath(),
                                'name' => $file->getClientOriginalName(),
                                'type' => $file->getMimeType(),
                                'size' => $file->getSize(),
                                'path' => $result->getPublicId(),
                            ];
                        } catch (\Exception $e) {
                            Log::warning('Cloudinary chat attachment upload failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            // Determine message type
            $hasAttachments = !empty($attachments);
            $hasText = !empty($messageText);
            
            $messageType = 'text';
            if ($hasAttachments) {
                $allImages = collect($attachments)->every(fn($a) => str_starts_with($a['type'], 'image/'));
                $messageType = $allImages && !$hasText ? 'image' : 'file';
            }

            $messageData = [
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'message' => $hasText ? $messageText : ($hasAttachments ? ($messageType === 'image' ? '📷 Image' : '📎 File') : ' '),
                'message_type' => $messageType,
                'is_read' => false,
            ];
            
            if ($hasAttachments) {
                $messageData['attachments'] = $attachments;
            }
            
            $message = Message::create($messageData);

            $senderDisplayName = '';
            
            if ($user->role === "vendor") {
                $vendorApplicant = VendorApplication::where('email', $user->email)->first();
                
                $senderDisplayName = $vendorApplicant->store_name ?? $user->full_name ?? 'Vendor';
            } else if ($user->role === "customer") {
                $senderDisplayName = $user->full_name ?? 'Customer';
            }

            if ($hasAttachments) {
                if ($messageType === 'image') {
                    $lastMessagePreview = $senderDisplayName . ' sent a photo';
                } else {
                    $lastMessagePreview = $senderDisplayName . ' sent a file';
                }
            } else {
                $lastMessagePreview = Str::limit($messageText, 50);
            }

            if ($user->id === $conversation->vendor_id) {
                $conversation->increment('unread_count_customer');
            } else {
                $conversation->increment('unread_count_vendor');
            }

            $conversation->update([
                'last_message' => $lastMessagePreview,
                'last_message_time' => now(),
                'last_message_sender_id' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'sender_id' => $user->id,
                    'text' => $message->message,
                    'time' => $message->created_at->diffForHumans(),
                    'read' => false,
                    'type' => $messageType,
                    'attachments' => $message->attachments,
                    'is_own' => true,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('sendMessage error', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'Server error'
            ], 500);
        }
    }

    /**
     * Poll for new messages
     */
    public function pollNewMessages(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $lastMessageId = $request->integer('last_message_id', 0);
            $conversationId = $request->integer('conversation_id', 0);

            $query = Message::query();

            if ($conversationId > 0) {
                $query->where('conversation_id', $conversationId);
            } else {
                $conversationIds = $user->isVendor()
                    ? Conversation::where('vendor_id', $user->id)->pluck('id')
                    : Conversation::where('customer_id', $user->id)->pluck('id');
                $query->whereIn('conversation_id', $conversationIds);
            }

            $newMessages = $query->where('id', '>', $lastMessageId)
                ->where('sender_id', '!=', $user->id)
                ->with('sender:id,name,surname,role')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'conversation_id' => $m->conversation_id,
                    'sender_id' => $m->sender_id,
                    'sender_name' => $m->sender->full_name,
                    'text' => $m->message,
                    'time' => $m->created_at->diffForHumans(),
                    'read' => $m->is_read,
                    'type' => $m->message_type,
                    'attachments' => $m->attachments ?? [],
                    'is_own' => false,
                ]);

            return response()->json([
                'success' => true,
                'new_messages' => $newMessages,
                'last_message_id' => $newMessages->max('id') ?? $lastMessageId,
            ]);
        } catch (\Exception $e) {
            Log::error('pollNewMessages error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * Get user details (customer or vendor)
     */
    public function getUserDetails(int $userId): JsonResponse
    {
        try {
            /** @var User $currentUser */
            $currentUser = Auth::user();
            $otherUser = User::findOrFail($userId);

            $conversation = Conversation::where(function($q) use ($currentUser, $userId) {
                $q->where('vendor_id', $currentUser->id)->where('customer_id', $userId);
            })->orWhere(function($q) use ($currentUser, $userId) {
                $q->where('customer_id', $currentUser->id)->where('vendor_id', $userId);
            })->first();

            $sharedFiles = $conversation
                ? Message::where('conversation_id', $conversation->id)
                    ->whereNotNull('attachments')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->flatMap(fn($m) => collect($m->attachments)->map(fn($a) => [
                        'name' => $a['name'],
                        'url' => $a['url'],
                        'type' => $a['type'],
                        'size' => $this->formatFileSize($a['size']),
                        'icon' => $this->getFileIcon($a['type']),
                    ]))
                : collect();

            $userData = [
                'id' => $otherUser->id,
                'name' => $otherUser->full_name,
                'email' => $otherUser->email,
                'contact_number' => $otherUser->contact_number,
                'avatar' => $otherUser->avatar_url,
                'online' => $otherUser->is_online,
                'address' => $otherUser->address . ', ' . $otherUser->city,
                'shared_files' => $sharedFiles,
            ];

            if ($otherUser->isVendor() && isset($otherUser->vendor_data['store_name'])) {
                $userData['store_name'] = $otherUser->vendor_data['store_name'];
            }

            $responseKey = $otherUser->isVendor() ? 'vendor' : 'customer';

            return response()->json([
                'success' => true,
                $responseKey => $userData
            ]);
        } catch (\Exception $e) {
            Log::error('getUserDetails error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * Start a new conversation
     */
    public function startConversation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'vendor_id' => 'required_without:customer_id|exists:users,id',
                'customer_id' => 'required_without:vendor_id|exists:users,id',
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            /** @var User $currentUser */
            $currentUser = Auth::user();
            $isVendor = $currentUser->isVendor();

            $vendorId = $isVendor ? $currentUser->id : $request->vendor_id;
            $customerId = $isVendor ? $request->customer_id : $currentUser->id;

            $conversation = Conversation::firstOrCreate(
                ['vendor_id' => $vendorId, 'customer_id' => $customerId],
                ['is_active' => true]
            );

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $currentUser->id,
                'message' => $request->message,
                'message_type' => 'text',
            ]);

            $conversation->update([
                'last_message' => $request->message,
                'last_message_time' => now(),
                $isVendor ? 'unread_count_customer' : 'unread_count_vendor' => $conversation->{$isVendor ? 'unread_count_customer' : 'unread_count_vendor'} + 1,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'conversation' => ['id' => $conversation->id],
                'message' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('startConversation error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * Search for users to chat with
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = Auth::user();
            $search = $request->input('search', '');
            
            // Search for opposite role
            $targetRole = $user->isVendor() ? User::ROLE_CUSTOMER : User::ROLE_VENDOR;

            $users = User::where('role', $targetRole)
                ->when($targetRole === User::ROLE_VENDOR, fn($q) => $q->where('is_verified', true))
                ->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                })
                ->select('id', 'name', 'surname', 'email', 'profile_picture', 'contact_number', 'username', 'vendor_data')
                ->limit(20)
                ->get()
                ->map(function($u) {
                    $data = [
                        'id' => $u->id,
                        'name' => $u->full_name,
                        'email' => $u->email,
                        'contact_number' => $u->contact_number,
                        'avatar' => $u->avatar_url,
                        'username' => $u->username,
                        'display_name' => $u->full_name,
                    ];

                    if ($u->isVendor() && isset($u->vendor_data['store_name'])) {
                        $data['store_name'] = $u->vendor_data['store_name'];
                        $data['display_name'] = $u->vendor_data['store_name'];
                    }

                    return $data;
                });

            $responseKey = $user->isVendor() ? 'customers' : 'vendors';

            return response()->json(['success' => true, $responseKey => $users]);
        } catch (\Exception $e) {
            Log::error('searchUsers error', ['error' => $e->getMessage()]);
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
}