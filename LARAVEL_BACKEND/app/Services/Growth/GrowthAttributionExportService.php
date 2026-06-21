<?php

namespace App\Services\Growth;

use App\Models\AttributionEvent;
use App\Models\Company;
use App\Models\SocialPost;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GrowthAttributionExportService
{
    public function csvResponse(Company $company, string $period = '30d'): StreamedResponse
    {
        $days = match ($period) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };
        $since = now()->subDays($days);

        $filename = 'attribution-'.$company->id.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($company, $since) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Date', 'Event', 'Platform', 'Post', 'Revenue', 'Chat ID', 'Order ID',
            ]);

            $events = AttributionEvent::where('company_id', $company->id)
                ->where('created_at', '>=', $since)
                ->orderBy('created_at')
                ->get();

            $postTitles = SocialPost::where('company_id', $company->id)
                ->pluck('title', 'id');

            foreach ($events as $event) {
                $title = $postTitles[$event->social_post_id] ?? ($event->social_post_id ? "Post #{$event->social_post_id}" : '');
                fputcsv($handle, [
                    $event->created_at->toDateTimeString(),
                    $event->event_type,
                    $event->platform ?? '',
                    $title,
                    $event->revenue ?? '',
                    $event->chat_id ?? '',
                    $event->order_id ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
