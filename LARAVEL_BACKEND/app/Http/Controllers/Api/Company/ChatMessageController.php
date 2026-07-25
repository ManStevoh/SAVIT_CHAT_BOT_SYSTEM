<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Company;
use App\Models\ConversationLearningSample;
use App\Models\Message;
use App\Services\Conversation\ConversationLearningRecorder;
use App\Services\ConversationLearningService;
use App\Services\WhatsApp\ChatAutoReplyService;
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

        if ((int) $chat->unread_count > 0) {
            $chat->update(['unread_count' => 0]);
        }

        // AI auto-reply mode: catch unanswered customer messages without a manual "Ask AI" click.
        if (! $chat->isAgentHandling(30)) {
            $chat->load(['company.settings', 'company.whatsappAccount']);
            app(ChatAutoReplyService::class)->ensureReplyIfNeeded($chat);
        }

        $messages = Message::where('chat_id', $chat->id)
            ->with('replyTo:id,content,sender,message_type')
            ->orderBy('created_at')
            ->get();

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
            'replySource' => $m->reply_source,
            'learningFeedback' => $m->learning_feedback,
            'learningSampleId' => $m->learning_sample_id ? (string) $m->learning_sample_id : null,
            'replyToMessageId' => $m->reply_to_message_id ? (string) $m->reply_to_message_id : null,
            'replyTo' => $m->replyTo ? [
                'id' => (string) $m->replyTo->id,
                'content' => $m->replyTo->content,
                'sender' => $m->replyTo->sender,
                'messageType' => $m->replyTo->message_type ?? 'text',
            ] : null,
        ]);

        return response()->json($data);
    }

    public function store(Request $request, string $chatId, ConversationLearningRecorder $learningRecorder): JsonResponse
    {
        $request->validate([
            'content' => 'nullable|string',
            'attachment' => 'nullable|file|max:10240',
            'replyToMessageId' => 'nullable|integer',
            'reply_to_message_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $chat = Chat::where('id', $chatId)->where('company_id', $user->company_id)->firstOrFail();
        $text = trim((string) $request->input('content', ''));
        $attachment = $request->file('attachment');
        $replyToId = (int) ($request->input('replyToMessageId') ?? $request->input('reply_to_message_id') ?? 0);

        if ($text === '' && ! $attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Message text or attachment is required.',
            ], 422);
        }

        $replyTo = null;
        $contextWaId = null;
        if ($replyToId > 0) {
            $replyTo = Message::query()
                ->where('id', $replyToId)
                ->where('chat_id', $chat->id)
                ->first();
            if (! $replyTo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quoted message not found in this chat.',
                ], 422);
            }
            $contextWaId = trim((string) ($replyTo->whatsapp_message_id ?? ''));
            if ($contextWaId === '') {
                $contextWaId = null;
            }
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
                $result = $waSender->sendText($account, $chat->customer_phone, $text, $contextWaId);
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
            'reply_to_message_id' => $replyTo?->id,
        ]);

        $chat->update([
            'last_message' => $text !== '' ? $text : ($attachmentName ?: '[Attachment]'),
            'last_message_at' => now(),
            'agent_handling_at' => now(),
        ]);

        if ($text !== '') {
            $lastCustomer = Message::query()
                ->where('chat_id', $chat->id)
                ->where('sender', 'customer')
                ->orderByDesc('created_at')
                ->value('content');
            if (is_string($lastCustomer) && trim($lastCustomer) !== '') {
                $company = Company::find($user->company_id);
                if ($company) {
                    $learningRecorder->recordAgentExchange($company, $lastCustomer, $text, (int) $chat->id);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => $whatsappSent ? 'Message sent.' : 'Message saved but not delivered via WhatsApp.',
            'whatsappSent' => $whatsappSent,
            'whatsappError' => $whatsappError,
        ]);
    }

    public function learningFeedback(
        Request $request,
        string $chatId,
        string $messageId,
        ConversationLearningService $learningService,
    ): JsonResponse {
        $validated = $request->validate([
            'feedback' => 'required|integer|in:-1,1',
        ]);

        $user = $request->user();
        $chat = Chat::where('id', $chatId)->where('company_id', $user->company_id)->firstOrFail();
        $message = Message::where('id', $messageId)
            ->where('chat_id', $chat->id)
            ->where('sender', 'bot')
            ->firstOrFail();

        $message->update(['learning_feedback' => (int) $validated['feedback']]);

        if ($message->learning_sample_id) {
            $sample = ConversationLearningSample::query()
                ->where('id', $message->learning_sample_id)
                ->where('company_id', $user->company_id)
                ->first();
            if ($sample) {
                $learningService->applyFeedback($sample, (int) $validated['feedback']);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Feedback recorded.',
            'learningFeedback' => (int) $validated['feedback'],
        ]);
    }
}
