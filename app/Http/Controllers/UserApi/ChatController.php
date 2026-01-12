<?php

namespace App\Http\Controllers\UserApi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserGoogle;
use App\Models\Superstar;

class ChatController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/user/chat/start/{superstarId}",
     *     summary="Start chat with superstar",
     *     description="Find or create conversation with a superstar",
     *     tags={"User Chat"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="superstarId",
     *         in="path",
     *         description="Superstar ID to start chat with",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chat started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Chat started successfully"),
     *             @OA\Property(property="conversation", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Superstar not found"
     *     )
     * )
     */
    public function startChat($superstarId)
    {
        $user = auth()->user();
        
        // Find or create conversation
        $conversation = Conversation::firstOrCreate(
            [
                'user_google_id' => $user->id,
                'superstar_id' => $superstarId
            ],
            [
                'status' => 'active',
                'started_at' => now()
            ]
        );
        
        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status
        ]);
    }
    
    public function sendMessage(Request $request, $conversationId)
    {
        $request->validate([
            'message' => 'required_without:file|string',
            'message_type' => 'required|in:text,image,video,file',
            'file' => 'nullable|file|max:10240' // 10MB max
        ]);
        
        $user = auth()->user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify user is part of this conversation
        if ($conversation->user_google_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'user',
            'sender_id' => $user->id,
            'message_type' => $request->message_type,
            'message' => $request->message,
            'is_read' => false
        ];
        
        // Handle file upload if present
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('chat_files', $fileName, 'public');
            
            $messageData['file_path'] = $filePath;
            $messageData['file_name'] = $file->getClientOriginalName();
            $messageData['file_size'] = $file->getSize();
        }
        
        $message = Message::create($messageData);
        
        return response()->json([
            'message' => $message->load('conversation')
        ]);
    }
    
    public function getMessages(Request $request, $conversationId)
    {
        $user = auth()->user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify user is part of this conversation
        if ($conversation->user_google_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Pagination parameters
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 20);
        
        // Get messages from most recent to oldest (for pagination)
        $messages = Message::where('conversation_id', $conversationId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
        
        // Reverse the messages array to show oldest first in the chat
        $reversedMessages = $messages->getCollection()->reverse()->values();
        
        // Mark messages as read (only superstar messages)
        Message::where('conversation_id', $conversationId)
            ->where('sender_type', 'superstar')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        
        return response()->json([
            'messages' => $reversedMessages,
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
                'has_more_pages' => $messages->hasMorePages()
            ]
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/user/chat/conversations",
     *     summary="Get user conversations",
     *     description="Get all conversations for the authenticated user",
     *     tags={"User Chat"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Conversations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="conversations", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function getConversations(Request $request)
    {
        $user = auth()->user();
        
        // Pagination parameters
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        
        $conversations = Conversation::where('user_google_id', $user->id)
            ->with(['superstar', 'messages' => function($query) {
                $query->latest()->first();
            }])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
            
        return response()->json([
            'conversations' => $conversations->items(),
            'pagination' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
                'from' => $conversations->firstItem(),
                'to' => $conversations->lastItem(),
                'has_more_pages' => $conversations->hasMorePages()
            ]
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/user/chat/unread-count",
     *     summary="Get unread message count",
     *     description="Get count of unread messages for the authenticated user",
     *     tags={"User Chat"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Unread count retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="unread_count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function getUnreadCount()
    {
        $user = auth()->user();
        
        $unreadCount = Message::join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.user_google_id', $user->id)
            ->where('messages.sender_type', 'superstar')
            ->where('messages.is_read', false)
            ->count();
            
        return response()->json(['unread_count' => $unreadCount]);
    }
    
    public function deleteMessage(Request $request, $messageId)
    {
        $user = auth()->user();
        
        $message = Message::where('id', $messageId)
            ->where('sender_type', 'user')
            ->where('sender_id', $user->id)
            ->first();
            
        if (!$message) {
            return response()->json([
                'message' => 'Message not found or unauthorized'
            ], 404);
        }
        
        // Delete file if it's an upload
        if ($message->file_path && Storage::disk('public')->exists($message->file_path)) {
            Storage::disk('public')->delete($message->file_path);
        }
        
        $message->delete();
        
        return response()->json([
            'message' => 'Message deleted successfully'
        ]);
    }
}
