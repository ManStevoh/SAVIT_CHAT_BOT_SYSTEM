<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            'Essem Chat' => 'RelayIQ',
            'Essem Assistant' => 'RelayIQ Assistant',
        ];

        if (Schema::hasTable('cms_pages')) {
            $pages = DB::table('cms_pages')->get(['id', 'title', 'meta_title', 'meta_description']);
            foreach ($pages as $page) {
                DB::table('cms_pages')->where('id', $page->id)->update([
                    'title' => $this->replaceAll((string) $page->title, $replacements),
                    'meta_title' => $this->replaceAll((string) ($page->meta_title ?? ''), $replacements),
                    'meta_description' => $this->replaceAll((string) ($page->meta_description ?? ''), $replacements),
                ]);
            }
        }

        if (Schema::hasTable('cms_sections')) {
            $sections = DB::table('cms_sections')->get(['id', 'label', 'content']);
            foreach ($sections as $section) {
                $content = $section->content;
                if (is_string($content)) {
                    $content = $this->replaceAll($content, $replacements);
                }
                DB::table('cms_sections')->where('id', $section->id)->update([
                    'label' => $this->replaceAll((string) $section->label, $replacements),
                    'content' => $content,
                ]);
            }
        }

        if (Schema::hasTable('blog_posts')) {
            $posts = DB::table('blog_posts')->get(['id', 'title', 'excerpt', 'body', 'meta_title', 'meta_description']);
            foreach ($posts as $post) {
                DB::table('blog_posts')->where('id', $post->id)->update([
                    'title' => $this->replaceAll((string) $post->title, $replacements),
                    'excerpt' => $this->replaceAll((string) ($post->excerpt ?? ''), $replacements),
                    'body' => $this->replaceAll((string) ($post->body ?? ''), $replacements),
                    'meta_title' => $this->replaceAll((string) ($post->meta_title ?? ''), $replacements),
                    'meta_description' => $this->replaceAll((string) ($post->meta_description ?? ''), $replacements),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible content rename.
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceAll(string $value, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }
};
