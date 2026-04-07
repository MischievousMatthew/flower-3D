<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Conversation;
use App\Models\Employee;
use App\Models\Message;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function getConversations(Request $request): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess($request, 'view');
        if ($accessResponse) {
            return $accessResponse;
        }

        try {
            $actor = $this->resolveActor($request);

            $search = $request->input('search', '');
            $filter = $request->input('filter', 'all');
            $isVendorContext = $actor['is_vendor_context'];
            $participantId = $actor['participant_id'];

            $query = Conversation::query()
                ->where($isVendorContext ? 'vendor_id' : 'customer_id', $participantId)
                ->where('is_active', true);

            if ($isVendorContext) {
                $query->with(['customer:id,name,surname,email,profile_picture,contact_number,username']);
            } else {
                $query->with(['vendor:id,name,surname,email,profile_picture,contact_number,username,vendor_data']);
            }

            $query->withCount(['messages as unread_count' => function ($q) use ($participantId) {
                $q->where('sender_id', '!=', $participantId)->where('is_read', false);
            }]);

            if ($filter === 'unread') {
                $query->having('unread_count', '>', 0);
            }

            if ($search) {
                $otherUserTable = $isVendorContext ? 'customer' : 'vendor';
                $query->whereHas($otherUserTable, function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }

            $conversations = $query->orderBy('last_message_time', 'desc')
                ->get()
                ->map(function ($conversation) use ($isVendorContext) {
                    $otherUser = $isVendorContext ? $conversation->customer : $conversation->vendor;

                    if (!$otherUser) {
                        return null;
                    }

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

                    if (!$isVendorContext && $otherUser->isVendor()) {
                        $storeName = $this->resolveVendorStoreName($otherUser);
                        if ($storeName) {
                            $userData['store_name'] = $storeName;
                            $userData['display_name'] = $storeName;
                        }
                    }

                    return [
                        'id' => $conversation->id,
                        $isVendorContext ? 'customer' : 'vendor' => $userData,
                        'last_message' => $conversation->last_message ? Str::limit($conversation->last_message, 50) : '',
                        'last_message_time' => $conversation->last_message_time
                            ? $conversation->last_message_time->diffForHumans()
                            : 'No messages',
                        'unread_count' => $conversation->unread_count,
                        'is_active' => $conversation->is_active,
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
            Log::error('getConversations error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function getMessages(Request $request, int $conversationId): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess($request, 'view');
        if ($accessResponse) {
            return $accessResponse;
        }

        try {
            $actor = $this->resolveActor($request);
            $conversation = Conversation::find($conversationId);

            if (!$conversation) {
                return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
            }

            if (!$this->canAccessConversation($conversation, $actor)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $participantId = $actor['participant_id'];

            $messages = Message::where('conversation_id', $conversationId)
                ->with('sender:id,name,surname,profile_picture')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($message) use ($participantId) {
                    $attachments = [];

                    if (!empty($message->attachments)) {
                        if (is_array($message->attachments)) {
                            $attachments = $message->attachments;
                        } elseif (is_string($message->attachments) && json_decode($message->attachments) !== null) {
                            $attachments = json_decode($message->attachments, true);
                        }
                    }

                    return [
                        'id' => $message->id,
                        'sender_id' => $message->sender_id,
                        'sender_name' => $message->sender->full_name ?? 'Unknown',
                        'sender_avatar' => $message->sender->avatar_url ?? null,
                        'text' => $message->message,
                        'time' => $message->created_at->diffForHumans(),
                        'read' => (bool) $message->is_read,
                        'type' => $message->message_type ?? 'text',
                        'attachments' => $attachments,
                        'is_own' => $message->sender_id === $participantId,
                    ];
                });

            Message::where('conversation_id', $conversationId)
                ->where('sender_id', '!=', $participantId)
                ->update(['is_read' => true, 'read_at' => now()]);

            if ($actor['is_vendor_context']) {
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
        } catch (\Throwable $e) {
            Log::error('getMessages error', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function sendMessage(Request $request): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess($request, 'edit');
        if ($accessResponse) {
            return $accessResponse;
        }

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

            $actor = $this->resolveActor($request);
            $conversation = Conversation::find($request->integer('conversation_id'));

            if (!$conversation || !$this->canAccessConversation($conversation, $actor)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $attachments = [];
            $messageText = trim($request->input('message', ''));

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if (!$file->isValid()) {
                        continue;
                    }

                    try {
                        $result = CloudinaryHelper::upload($file->getRealPath(), [
                            'folder' => 'chat/attachments/' . date('Y/m'),
                            'resource_type' => 'auto',
                        ]);

                        $attachments[] = [
                            'url' => $result['secure_url'],
                            'name' => $file->getClientOriginalName(),
                            'type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                            'path' => $result['public_id'],
                        ];
                    } catch (\Throwable $e) {
                        Log::warning('Cloudinary chat attachment upload failed: ' . $e->getMessage());
                    }
                }
            }

            $hasAttachments = !empty($attachments);
            $hasText = !empty($messageText);

            $messageType = 'text';
            if ($hasAttachments) {
                $allImages = collect($attachments)->every(fn ($attachment) => str_starts_with($attachment['type'], 'image/'));
                $messageType = $allImages && !$hasText ? 'image' : 'file';
            }

            $messageData = [
                'owner_id' => $conversation->vendor_id,
                'conversation_id' => $conversation->id,
                'sender_id' => $actor['participant_id'],
                'message' => $hasText
                    ? $messageText
                    : ($hasAttachments ? ($messageType === 'image' ? 'Image' : 'File') : ' '),
                'message_type' => $messageType,
                'is_read' => false,
            ];

            if ($hasAttachments) {
                $messageData['attachments'] = $attachments;
            }

            $message = Message::create($messageData);

            $senderDisplayName = $this->getSenderDisplayName($actor);

            if ($hasAttachments) {
                $lastMessagePreview = $messageType === 'image'
                    ? $senderDisplayName . ' sent a photo'
                    : $senderDisplayName . ' sent a file';
            } else {
                $lastMessagePreview = Str::limit($messageText, 50);
            }

            if ($actor['is_vendor_context']) {
                $conversation->increment('unread_count_customer');
            } else {
                $conversation->increment('unread_count_vendor');
            }

            $conversation->update([
                'last_message' => $lastMessagePreview,
                'last_message_time' => now(),
                'last_message_sender_id' => $actor['participant_id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => [
                    'id' => $message->id,
                    'sender_id' => $actor['participant_id'],
                    'text' => $message->message,
                    'time' => $message->created_at->diffForHumans(),
                    'read' => false,
                    'type' => $messageType,
                    'attachments' => $message->attachments,
                    'is_own' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sendMessage error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function pollNewMessages(Request $request): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess($request, 'view');
        if ($accessResponse) {
            return $accessResponse;
        }

        try {
            $actor = $this->resolveActor($request);
            $participantId = $actor['participant_id'];
            $lastMessageId = $request->integer('last_message_id', 0);
            $conversationId = $request->integer('conversation_id', 0);

            $query = Message::query();

            if ($conversationId > 0) {
                $conversation = Conversation::find($conversationId);
                if (!$conversation || !$this->canAccessConversation($conversation, $actor)) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
                }

                $query->where('conversation_id', $conversationId);
            } else {
                $conversationIds = Conversation::query()
                    ->where($actor['is_vendor_context'] ? 'vendor_id' : 'customer_id', $participantId)
                    ->pluck('id');

                $query->whereIn('conversation_id', $conversationIds);
            }

            $newMessages = $query->where('id', '>', $lastMessageId)
                ->where('sender_id', '!=', $participantId)
                ->with('sender:id,name,surname,role')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($message) => [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'sender_id' => $message->sender_id,
                    'sender_name' => $message->sender->full_name,
                    'text' => $message->message,
                    'time' => $message->created_at->diffForHumans(),
                    'read' => $message->is_read,
                    'type' => $message->message_type,
                    'attachments' => $message->attachments ?? [],
                    'is_own' => false,
                ]);

            return response()->json([
                'success' => true,
                'new_messages' => $newMessages,
                'last_message_id' => $newMessages->max('id') ?? $lastMessageId,
            ]);
        } catch (\Throwable $e) {
            Log::error('pollNewMessages error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function getUserDetails(Request $request, int $userId): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess($request, 'view');
        if ($accessResponse) {
            return $accessResponse;
        }

        try {
            $actor = $this->resolveActor($request);
            $otherUser = User::findOrFail($userId);
            $participantId = $actor['participant_id'];

            $conversation = Conversation::where(function ($q) use ($participantId, $userId) {
                $q->where('vendor_id', $participantId)->where('customer_id', $userId);
            })->orWhere(function ($q) use ($participantId, $userId) {
                $q->where('customer_id', $participantId)->where('vendor_id', $userId);
            })->first();

            $sharedFiles = $conversation
                ? Message::where('conversation_id', $conversation->id)
                    ->whereNotNull('attachments')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get()
                    ->flatMap(fn ($message) => collect($message->attachments)->map(fn ($attachment) => [
                        'name' => $attachment['name'],
                        'url' => $attachment['url'],
                        'type' => $attachment['type'],
                        'size' => $this->formatFileSize($attachment['size']),
                        'icon' => $this->getFileIcon($attachment['type']),
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

            if ($otherUser->isVendor()) {
                $storeName = $this->resolveVendorStoreName($otherUser);
                if ($storeName) {
                    $userData['store_name'] = $storeName;
                }
            }

            $responseKey = $otherUser->isVendor() ? 'vendor' : 'customer';

            return response()->json([
                'success' => true,
                $responseKey => $userData,
            ]);
        } catch (\Throwable $e) {
            Log::error('getUserDetails error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function startConversation(Request $request): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess($request, 'edit');
        if ($accessResponse) {
            return $accessResponse;
        }

        try {
            $validator = Validator::make($request->all(), [
                'vendor_id' => 'required_without:customer_id|exists:users,id',
                'customer_id' => 'required_without:vendor_id|exists:users,id',
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $actor = $this->resolveActor($request);
            $isVendorContext = $actor['is_vendor_context'];
            $participantId = $actor['participant_id'];

            $vendorId = $isVendorContext ? $participantId : $request->integer('vendor_id');
            $customerId = $isVendorContext ? $request->integer('customer_id') : $participantId;

            $conversation = Conversation::firstOrCreate(
                ['vendor_id' => $vendorId, 'customer_id' => $customerId],
                ['owner_id' => $vendorId, 'is_active' => true]
            );

            $message = Message::create([
                'owner_id' => $vendorId,
                'conversation_id' => $conversation->id,
                'sender_id' => $participantId,
                'message' => $request->string('message')->toString(),
                'message_type' => 'text',
            ]);

            $conversation->update([
                'last_message' => $request->string('message')->toString(),
                'last_message_time' => now(),
                $isVendorContext ? 'unread_count_customer' : 'unread_count_vendor' =>
                    $conversation->{$isVendorContext ? 'unread_count_customer' : 'unread_count_vendor'} + 1,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'conversation' => ['id' => $conversation->id],
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('startConversation error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $accessResponse = $this->ensureChatAccess(
            $request,
            $request->user() instanceof Employee ? 'edit' : 'view'
        );
        if ($accessResponse) {
            return $accessResponse;
        }

        try {
            $authUser = $request->user();
            $search = $request->input('search', '');
            $isEmployee = $authUser instanceof Employee;
            $isVendorContext = $isEmployee ? true : ($authUser instanceof User && $authUser->isVendor());

            $targetRole = $isVendorContext ? User::ROLE_CUSTOMER : User::ROLE_VENDOR;

            $matchingVendorEmails = [];
            if ($targetRole === User::ROLE_VENDOR && $search !== '') {
                $matchingVendorEmails = VendorApplication::query()
                    ->where('store_name', 'like', "%{$search}%")
                    ->pluck('email')
                    ->filter()
                    ->values()
                    ->all();
            }

            $users = User::where('role', $targetRole)
                ->when($targetRole === User::ROLE_VENDOR, fn ($q) => $q->where('is_verified', true))
                ->where(function ($q) use ($search, $targetRole, $matchingVendorEmails) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");

                    if ($targetRole === User::ROLE_VENDOR && !empty($matchingVendorEmails)) {
                        $q->orWhereIn('email', $matchingVendorEmails);
                    }
                })
                ->select('id', 'name', 'surname', 'email', 'profile_picture', 'contact_number', 'username', 'vendor_data')
                ->limit(20)
                ->get()
                ->map(function ($user) {
                    $data = [
                        'id' => $user->id,
                        'name' => $user->full_name,
                        'email' => $user->email,
                        'contact_number' => $user->contact_number,
                        'avatar' => $user->avatar_url,
                        'username' => $user->username,
                        'display_name' => $user->full_name,
                    ];

                    if ($user->isVendor()) {
                        $storeName = $this->resolveVendorStoreName($user);
                        if ($storeName) {
                            $data['store_name'] = $storeName;
                            $data['display_name'] = $storeName;
                        }
                    }

                    return $data;
                });

            $responseKey = $isVendorContext ? 'customers' : 'vendors';

            return response()->json(['success' => true, $responseKey => $users]);
        } catch (\Throwable $e) {
            Log::error('searchUsers error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    private function ensureChatAccess(Request $request, string $requiredAccess): ?JsonResponse
    {
        $authUser = $request->user();

        if ($authUser instanceof Employee) {
            if (!$authUser->owner_id) {
                return response()->json(['success' => false, 'message' => 'CRM chat is not configured for this employee'], 403);
            }

            $allowed = $requiredAccess === 'edit'
                ? $authUser->canEditModule('crm')
                : $authUser->canViewModule('crm');

            if (!$allowed) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            return null;
        }

        if (!($authUser instanceof User)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return null;
    }

    private function resolveActor(Request $request): array
    {
        $authUser = $request->user();

        if ($authUser instanceof Employee) {
            return [
                'auth_user' => $authUser,
                'participant_id' => (int) $authUser->owner_id,
                'is_vendor_context' => true,
                'is_employee' => true,
            ];
        }

        /** @var User $authUser */
        return [
            'auth_user' => $authUser,
            'participant_id' => (int) $authUser->id,
            'is_vendor_context' => $authUser->isVendor(),
            'is_employee' => false,
        ];
    }

    private function canAccessConversation(Conversation $conversation, array $actor): bool
    {
        $participantId = $actor['participant_id'];

        if ($actor['is_vendor_context']) {
            return $conversation->vendor_id === $participantId;
        }

        return $conversation->customer_id === $participantId;
    }

    private function getSenderDisplayName(array $actor): string
    {
        $authUser = $actor['auth_user'];

        if ($authUser instanceof Employee) {
            $owner = $authUser->owner()->first();

            if ($owner instanceof User) {
                $vendorApplicant = VendorApplication::where('email', $owner->email)->first();

                return $vendorApplicant->store_name ?? $owner->full_name ?? $authUser->name ?? 'CRM';
            }

            return $authUser->name ?? 'CRM';
        }

        if ($authUser instanceof User && $authUser->role === User::ROLE_VENDOR) {
            $vendorApplicant = VendorApplication::where('email', $authUser->email)->first();

            return $vendorApplicant->store_name ?? $authUser->full_name ?? 'Vendor';
        }

        if ($authUser instanceof User && $authUser->role === User::ROLE_CUSTOMER) {
            return $authUser->full_name ?? 'Customer';
        }

        return 'User';
    }

    private function resolveVendorStoreName(User $user): ?string
    {
        $storeName = data_get($user->vendor_data, 'store_name');
        if (is_string($storeName) && trim($storeName) !== '') {
            return trim($storeName);
        }

        $applicationStoreName = VendorApplication::query()
            ->where('email', $user->email)
            ->value('store_name');

        return is_string($applicationStoreName) && trim($applicationStoreName) !== ''
            ? trim($applicationStoreName)
            : null;
    }

    private function getFileIcon(string $fileType): string
    {
        return match (true) {
            str_contains($fileType, 'image') => 'Image',
            str_contains($fileType, 'pdf') => 'PDF',
            str_contains($fileType, 'document') => 'Doc',
            str_contains($fileType, 'spreadsheet') => 'Sheet',
            str_contains($fileType, 'archive') => 'Archive',
            default => 'File',
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
