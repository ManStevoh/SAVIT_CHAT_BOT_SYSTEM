<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppMessageTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppTemplateService
{
    public function __construct(
        protected WhatsAppGraphClient $graph
    ) {}

    public function syncFromMeta(WhatsAppAccount $account): array
    {
        $wabaId = (string) ($account->whatsapp_business_account_id ?? '');
        if ($wabaId === '') {
            return ['success' => false, 'message' => 'WhatsApp Business Account ID missing. Reconnect WhatsApp.'];
        }

        $result = $this->graph->get("{$wabaId}/message_templates", $account->access_token, [
            'limit' => 100,
            'fields' => 'id,name,language,status,category,components,rejected_reason',
        ]);

        if (! $result['ok']) {
            $error = $result['data']['error']['message'] ?? 'Failed to fetch templates from Meta';

            return ['success' => false, 'message' => $error];
        }

        $synced = 0;
        foreach ($result['data']['data'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $bodyPreview = $this->extractBodyPreview($row['components'] ?? []);
            WhatsAppMessageTemplate::updateOrCreate(
                [
                    'company_id' => $account->company_id,
                    'name' => (string) ($row['name'] ?? ''),
                    'language' => (string) ($row['language'] ?? 'en'),
                ],
                [
                    'meta_template_id' => $row['id'] ?? null,
                    'category' => strtolower((string) ($row['category'] ?? 'utility')),
                    'status' => strtolower((string) ($row['status'] ?? 'pending')),
                    'components' => $row['components'] ?? null,
                    'body_preview' => $bodyPreview,
                    'rejection_reason' => $row['rejected_reason'] ?? null,
                ]
            );
            $synced++;
        }

        return ['success' => true, 'message' => "Synced {$synced} template(s) from Meta.", 'count' => $synced];
    }

    public function createOnMeta(WhatsAppAccount $account, array $payload): array
    {
        $wabaId = (string) ($account->whatsapp_business_account_id ?? '');
        if ($wabaId === '') {
            return ['success' => false, 'message' => 'WhatsApp Business Account ID missing.'];
        }

        $name = Str::snake(preg_replace('/[^a-zA-Z0-9_]/', '_', $payload['name'] ?? ''));
        $language = $payload['language'] ?? 'en';
        $category = strtoupper($payload['category'] ?? 'UTILITY');
        $bodyText = trim((string) ($payload['body'] ?? ''));

        if ($name === '' || $bodyText === '') {
            return ['success' => false, 'message' => 'Template name and body are required.'];
        }

        $components = [
            [
                'type' => 'BODY',
                'text' => $bodyText,
            ],
        ];

        $result = $this->graph->post("{$wabaId}/message_templates", $account->access_token, [
            'name' => $name,
            'language' => $language,
            'category' => $category,
            'components' => $components,
        ]);

        if (! $result['ok']) {
            $error = $result['data']['error']['message'] ?? 'Meta rejected template creation';
            Log::warning('WhatsApp template create failed', ['error' => $error]);

            return ['success' => false, 'message' => $error];
        }

        $template = WhatsAppMessageTemplate::create([
            'company_id' => $account->company_id,
            'meta_template_id' => $result['data']['id'] ?? null,
            'name' => $name,
            'language' => $language,
            'category' => strtolower($category),
            'status' => 'pending',
            'components' => $components,
            'body_preview' => $bodyText,
        ]);

        return [
            'success' => true,
            'message' => 'Template submitted to Meta for approval.',
            'template' => $template,
        ];
    }

    public function deleteOnMeta(WhatsAppMessageTemplate $template, WhatsAppAccount $account): array
    {
        $metaId = (string) ($template->meta_template_id ?? '');
        if ($metaId !== '') {
            $result = $this->graph->delete($metaId, $account->access_token);
            if (! $result['ok']) {
                $error = $result['data']['error']['message'] ?? 'Failed to delete template on Meta';

                return ['success' => false, 'message' => $error];
            }
        }

        $template->delete();

        return ['success' => true, 'message' => 'Template deleted.'];
    }

    protected function extractBodyPreview(array $components): ?string
    {
        foreach ($components as $component) {
            if (is_array($component) && ($component['type'] ?? '') === 'BODY') {
                return $component['text'] ?? null;
            }
        }

        return null;
    }
}
