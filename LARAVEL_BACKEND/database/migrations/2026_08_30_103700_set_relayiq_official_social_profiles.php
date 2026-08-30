<?php

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Support\BrandSocial;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $page = CmsPage::where('slug', 'global')->first();
        if (! $page) {
            return;
        }

        $section = CmsSection::where('cms_page_id', $page->id)
            ->where('section_key', 'footer')
            ->first();

        if (! $section) {
            return;
        }

        $content = $section->content ?? [];
        $content['socialLinks'] = BrandSocial::links();
        $section->content = $content;
        $section->save();
    }

    public function down(): void
    {
        $page = CmsPage::where('slug', 'global')->first();
        if (! $page) {
            return;
        }

        $section = CmsSection::where('cms_page_id', $page->id)
            ->where('section_key', 'footer')
            ->first();

        if (! $section) {
            return;
        }

        $content = $section->content ?? [];
        $content['socialLinks'] = [
            ['label' => 'Facebook', 'href' => '#'],
            ['label' => 'Instagram', 'href' => '#'],
            ['label' => 'Twitter', 'href' => '#'],
            ['label' => 'Linkedin', 'href' => '#'],
        ];
        $section->content = $content;
        $section->save();
    }
};
