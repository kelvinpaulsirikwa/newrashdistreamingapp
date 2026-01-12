<?php

namespace App\Http\Controllers\SuperStar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserGoogle;

class ChatController extends Controller
{
    public function getConversations(Request $request)
    {
        $superstar = $request->user();
        
        // Pagination parameters
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 15);
        
        // Filter parameters
        $status = $request->get('status');
        
        $query = Conversation::where('superstar_id', $superstar->id)
            ->with(['user', 'messages' => function($query) {
                $query->latest()->first();
            }]);
        
        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }
        
        $conversations = $query->orderBy('updated_at', 'desc')
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
    
    public function getMessages(Request $request, $conversationId)
    {
        $superstar = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify superstar is part of this conversation
        if ($conversation->superstar_id !== $superstar->id) {
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
    
    public function sendMessage(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required_without:file|string',
            'message_type' => 'required|in:text,image,video,file',
            'file' => 'nullable|file|max:10240' // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $superstar = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify superstar is part of this conversation
        if ($conversation->superstar_id !== $superstar->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $messageData = [
            'conversation_id' => $conversationId,
            'sender_type' => 'superstar',
            'sender_id' => $superstar->id,
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
    
    public function markMessagesAsRead(Request $request, $conversationId)
    {
        $superstar = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify superstar is part of this conversation
        if ($conversation->superstar_id !== $superstar->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Mark user messages as read
        $updatedCount = Message::where('conversation_id', $conversationId)
            ->where('sender_type', 'user')
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        
        return response()->json([
            'message' => 'Messages marked as read',
            'messages_marked' => $updatedCount
        ]);
    }
    
    public function getUnreadCount(Request $request)
    {
        $superstar = $request->user();
        
        $unreadCount = Message::join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.superstar_id', $superstar->id)
            ->where('messages.sender_type', 'user')
            ->where('messages.is_read', false)
            ->count();
            
        return response()->json(['unread_count' => $unreadCount]);
    }
    
    public function updateConversationStatus(Request $request, $conversationId)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,ended,blocked'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $superstar = $request->user();
        $conversation = Conversation::findOrFail($conversationId);
        
        // Verify superstar is part of this conversation
        if ($conversation->superstar_id !== $superstar->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $updateData = [
            'status' => $request->status
        ];
        
        // Set timestamps based on status
        if ($request->status === 'active' && !$conversation->started_at) {
            $updateData['started_at'] = now();
        } elseif ($request->status === 'ended') {
            $updateData['ended_at'] = now();
        }
        
        $conversation->update($updateData);
        
        return response()->json([
            'message' => 'Conversation status updated successfully',
            'conversation' => $conversation->load('user')
        ]);
    }
    
    public function deleteMessage(Request $request, $messageId)
    {
        $superstar = $request->user();
        
        $message = Message::where('id', $messageId)
            ->where('sender_type', 'superstar')
            ->where('sender_id', $superstar->id)
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
