<?php

use App\Models\CmsPage;
use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = CmsPage::where('slug', 'global')->first();
        if (! $page) {
            return;
        }

        foreach (['navbar' => 'links', 'footer' => 'navLinks'] as $sectionKey => $linksKey) {
            $section = CmsSection::where('cms_page_id', $page->id)
                ->where('section_key', $sectionKey)
                ->first();

            if (! $section) {
                continue;
            }

            $content = $section->content ?? [];
            $links = is_array($content[$linksKey] ?? null) ? $content[$linksKey] : [];

            $hasBlog = collect($links)->contains(function ($link) {
                $href = is_array($link) ? ($link['href'] ?? '') : '';

                return rtrim((string) $href, '/') === '/blog';
            });

            if ($hasBlog) {
                continue;
            }

            $blogLink = ['label' => 'Blog', 'href' => '/blog'];
            $inserted = false;
            $next = [];

            foreach ($links as $link) {
                $next[] = $link;
                $href = is_array($link) ? ($link['href'] ?? '') : '';
                if (! $inserted && in_array(rtrim((string) $href, '/'), ['/about', '/about-us'], true)) {
                    $next[] = $blogLink;
                    $inserted = true;
                }
            }

            if (! $inserted) {
                $next[] = $blogLink;
            }

            $content[$linksKey] = $next;
            $section->content = $content;
            $section->save();
        }
    }

    public function down(): void
    {
        $page = CmsPage::where('slug', 'global')->first();
        if (! $page) {
            return;
        }

        foreach (['navbar' => 'links', 'footer' => 'navLinks'] as $sectionKey => $linksKey) {
            $section = CmsSection::where('cms_page_id', $page->id)
                ->where('section_key', $sectionKey)
                ->first();

            if (! $section) {
                continue;
            }

            $content = $section->content ?? [];
            $links = is_array($content[$linksKey] ?? null) ? $content[$linksKey] : [];
            $content[$linksKey] = array_values(array_filter($links, function ($link) {
                $href = is_array($link) ? ($link['href'] ?? '') : '';

                return rtrim((string) $href, '/') !== '/blog';
            }));
            $section->content = $content;
            $section->save();
        }
    }
};
