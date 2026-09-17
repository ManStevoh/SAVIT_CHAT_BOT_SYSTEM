<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformMarketingMessage;
use App\Models\PlatformMarketingSend;
use App\Services\PlatformMarketingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformMarketingController extends Controller
{
    public function index(PlatformMarketingService $marketing): JsonResponse
    {
        $messages = PlatformMarketingMessage::query()
            ->withCount('sends')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PlatformMarketingMessage $m) => $this->toArray($m, $marketing));

        return response()->json(['success' => true, 'messages' => $messages]);
    }

    public function store(Request $request, PlatformMarketingService $marketing): JsonResponse
    {
        $message = new PlatformMarketingMessage;
        $this->fill($message, $this->validated($request));
        $message->created_by = $request->user()?->id;
        $message->save();

        return response()->json([
            'success' => true,
            'message' => $this->toArray($message, $marketing),
        ], 201);
    }

    public function update(Request $request, PlatformMarketingMessage $marketingMessage, PlatformMarketingService $marketing): JsonResponse
    {
        $this->fill($marketingMessage, $this->validated($request, updating: true));
        $marketingMessage->save();

        return response()->json([
            'success' => true,
            'message' => $this->toArray($marketingMessage->fresh(), $marketing),
        ]);
    }

    public function destroy(PlatformMarketingMessage $marketingMessage): JsonResponse
    {
        $marketingMessage->delete();

        return response()->json(['success' => true]);
    }

    public function sendNow(PlatformMarketingMessage $marketingMessage, PlatformMarketingService $marketing): JsonResponse
    {
        $sent = $marketing->sendNow($marketingMessage);

        return response()->json([
            'success' => true,
            'sent' => $sent,
            'message' => $this->toArray($marketingMessage->fresh(), $marketing),
        ]);
    }

    public function sends(Request $request, ?PlatformMarketingMessage $marketingMessage = null): JsonResponse
    {
        $query = PlatformMarketingSend::query()
            ->with(['user:id,name,email,phone', 'company:id,name', 'message:id,name,title'])
            ->orderByDesc('id');

        if ($marketingMessage) {
            $query->where('message_id', $marketingMessage->id);
        }

        $sends = $query
            ->limit(min(200, max(1, (int) $request->integer('limit', 50))))
            ->get()
            ->map(fn (PlatformMarketingSend $row) => [
                'id' => $row->id,
                'messageId' => $row->message_id,
                'messageName' => $row->message?->name,
                'userId' => $row->user_id,
                'userEmail' => $row->user?->email,
                'userName' => $row->user?->name,
                'companyName' => $row->company?->name,
                'channel' => $row->channel,
                'status' => $row->status,
                'periodKey' => $row->period_key,
                'error' => $row->error,
                'sentAt' => $row->sent_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'sends' => $sends]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $updating = false): array
    {
        $req = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'name' => $req.'|string|max:120',
            'title' => $req.'|string|max:180',
            'bodyHtml' => 'nullable|string|max:20000',
            'bodyText' => $req.'|string|max:4000',
            'ctaLabel' => 'nullable|string|max:80',
            'ctaUrl' => 'nullable|string|max:500',
            'channelEmail' => 'sometimes|boolean',
            'channelWhatsapp' => 'sometimes|boolean',
            'channelPopup' => 'sometimes|boolean',
            'audience' => 'sometimes|string|in:'.implode(',', PlatformMarketingMessage::AUDIENCES),
            'status' => 'sometimes|string|in:draft,scheduled,active,paused,archived',
            'sendMode' => 'sometimes|string|in:manual,scheduled,recurring,trigger',
            'scheduledAt' => 'nullable|date',
            'recurringInterval' => 'nullable|string|in:daily,weekly,monthly',
            'trigger' => 'nullable|string|in:registered,verified,login',
            'triggerDelaySeconds' => 'nullable|integer|min:0|max:2592000',
            'popupOnce' => 'sometimes|boolean',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(PlatformMarketingMessage $message, array $data): void
    {
        $map = [
            'name' => 'name',
            'title' => 'title',
            'bodyHtml' => 'body_html',
            'bodyText' => 'body_text',
            'ctaLabel' => 'cta_label',
            'ctaUrl' => 'cta_url',
            'channelEmail' => 'channel_email',
            'channelWhatsapp' => 'channel_whatsapp',
            'channelPopup' => 'channel_popup',
            'audience' => 'audience',
            'status' => 'status',
            'sendMode' => 'send_mode',
            'scheduledAt' => 'scheduled_at',
            'recurringInterval' => 'recurring_interval',
            'trigger' => 'trigger',
            'triggerDelaySeconds' => 'trigger_delay_seconds',
            'popupOnce' => 'popup_once',
        ];
        foreach ($data as $key => $value) {
            $col = $map[$key] ?? null;
            if ($col) {
                $message->setAttribute($col, $value);
            }
        }
        if (($message->send_mode === 'scheduled') && $message->scheduled_at && $message->status === 'draft') {
            $message->status = PlatformMarketingMessage::STATUS_SCHEDULED;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(PlatformMarketingMessage $message, PlatformMarketingService $marketing): array
    {
        return [
            'id' => $message->id,
            'name' => $message->name,
            'title' => $message->title,
            'bodyHtml' => $message->body_html,
            'bodyText' => $message->body_text,
            'ctaLabel' => $message->cta_label,
            'ctaUrl' => $message->cta_url,
            'channelEmail' => (bool) $message->channel_email,
            'channelWhatsapp' => (bool) $message->channel_whatsapp,
            'channelPopup' => (bool) $message->channel_popup,
            'audience' => $message->audience,
            'status' => $message->status,
            'sendMode' => $message->send_mode,
            'scheduledAt' => $message->scheduled_at?->toIso8601String(),
            'recurringInterval' => $message->recurring_interval,
            'trigger' => $message->trigger,
            'triggerDelaySeconds' => (int) $message->trigger_delay_seconds,
            'popupOnce' => (bool) $message->popup_once,
            'lastRunAt' => $message->last_run_at?->toIso8601String(),
            'sendsCount' => (int) ($message->sends_count ?? $message->sends()->count()),
            'audienceCount' => $marketing->audienceCount($message),
            'createdAt' => $message->created_at?->toIso8601String(),
            'updatedAt' => $message->updated_at?->toIso8601String(),
        ];
    }
}
