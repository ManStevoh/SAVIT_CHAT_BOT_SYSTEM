<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Services\WhatsAppMessageSenderService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'sender' => $m->sender,
            'timestamp' => Carbon::parse($m->created_at)->format('g:i A'),
            'status' => $m->status,
        ]);

        return response()->json($data);
    }

    public function store(Request $request, string $chatId): JsonResponse
    {
        $request->validate(['content' => 'required|string']);

        $user = $request->user();
        $chat = Chat::where('id', $chatId)->where('company_id', $user->company_id)->firstOrFail();

        Message::create([
            'chat_id' => $chat->id,
            'content' => $request->content,
            'sender' => 'agent',
            'status' => 'sent',
        ]);

        $chat->update([
            'last_message' => $request->content,
            'last_message_at' => now(),
        ]);

        // Send to WhatsApp if company has connected account (so customer sees reply in WhatsApp)
        $account = $chat->company->whatsappAccount;
        if ($account && $account->isActive() && $chat->customer_phone) {
            $waSender = app(WhatsAppMessageSenderService::class);
            $waSender->sendText($account, $chat->customer_phone, $request->content);
        }

        return response()->json(['success' => true, 'message' => 'Message sent.']);
    }
}
