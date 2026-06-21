<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Services\WhatsAppMessageSenderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChatMessageController extends Controller
{
    public function index(Request $request, string $chatId): JsonResponse
    {
        $user = $request->user();
        $chat = Chat::where('id', $chatId)->where('company_id', $user->company_id)->firstOrFail();

        $messages = Message::where('chat_id', $chat->id)->orderBy('created_at')->get();

        $data = $messages->map(fn (Message $m) => [
            'id' => (string) $m->id,
            'chatId' => (string) $chat->id,
            'content' => $m->content,
            'messageType' => $m->message_type ?? 'text',
            'attachmentUrl' => $m->attachment_url,
            'attachmentName' => $m->attachment_name,
            'attachmentMime' => $m->attachment_mime,
            'attachmentSize' => $m->attachment_size,
            'sender' => $m->sender,
            'timestamp' => Carbon::parse($m->created_at)->format('g:i A'),
            'status' => $m->status,
        ]);

        return response()->json($data);
    }

    public function store(Request $request, string $chatId): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
        ]);

        $user = $request->user();
        $chat = Chat::where('id', $chatId)->where('company_id', $user->company_id)->firstOrFail();
        $text = trim((string) $request->input('content', ''));
        $attachment = $request->file('attachment');

        if ($text === '' && ! $attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Message text or attachment is required.',
            ], 422);
        }

        $whatsappSent = false;
        $whatsappError = null;
        $waMessageId = null;
        $messageType = 'text';
        $attachmentUrl = null;
        $attachmentAbsolutePath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($attachment) {
            $attachmentPath = $attachment->store('chat-attachments', 'public');
            $attachmentUrl = Storage::disk('public')->url($attachmentPath);
            $attachmentAbsolutePath = Storage::disk('public')->path($attachmentPath);
            $attachmentName = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getMimeType();
            $attachmentSize = $attachment->getSize();
            $messageType = str_starts_with((string) $attachmentMime, 'image/') ? 'image' : 'file';
        }

        $account = $chat->company->whatsappAccount;
        if (! $account || ! $account->isActive()) {
            $whatsappError = 'No active WhatsApp connection';
        } elseif (empty($chat->customer_phone)) {
            $whatsappError = 'No customer phone number';
        } else {
            $waSender = app(WhatsAppMessageSenderService::class);
            if ($attachmentUrl && $messageType === 'image') {
                $result = $waSender->sendImageFile(
                    $account,
                    $chat->customer_phone,
                    $attachmentAbsolutePath,
                    $attachmentMime,
                    $text !== '' ? $text : null
                );
                if (! $result['success']) {
                    // Fallback to public-link method if upload-by-id fails in some environments.
                    $result = $waSender->sendImage($account, $chat->customer_phone, $attachmentUrl, $text !== '' ? $text : null);
                }
            } elseif ($attachmentUrl) {
                $result = $waSender->sendDocumentFile(
                    $account,
                    $chat->customer_phone,
                    $attachmentAbsolutePath,
                    $attachmentMime,
                    $attachmentName,
                    $text !== '' ? $text : null
                );
                if (! $result['success']) {
                    $result = $waSender->sendDocument($account, $chat->customer_phone, $attachmentUrl, $attachmentName, $text !== '' ? $text : null);
                }
            } else {
                $result = $waSender->sendText($account, $chat->customer_phone, $text);
            }
            $whatsappSent = $result['success'];
            $whatsappError = $result['error'] ?? null;
            $waMessageId = $result['message_id'] ?? null;
        }

        Message::create([
            'chat_id' => $chat->id,
            'content' => $text,
            'message_type' => $messageType,
            'attachment_url' => $attachmentUrl,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
            'sender' => 'agent',
            'status' => $whatsappSent ? 'sent' : 'failed',
            'whatsapp_message_id' => $waMessageId,
        ]);

        $chat->update([
            'last_message' => $text !== '' ? $text : ($attachmentName ?: '[Attachment]'),
            'last_message_at' => now(),
            'agent_handling_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $whatsappSent ? 'Message sent.' : 'Message saved but not delivered via WhatsApp.',
            'whatsappSent' => $whatsappSent,
            'whatsappError' => $whatsappError,
        ]);
    }
}
