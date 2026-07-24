<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align CMS footer copyright and legal copy with RelayIQ product attribution:
 * RelayIQ is a product of Essem Digital Innovation Limited (essemdigital.com).
 */
return new class extends Migration
{
    public function up(): void
    {
        $year = date('Y');
        $copyright = "{$year} © Essem Digital Innovation Limited. RelayIQ is a product of Essem Digital Innovation Limited. All rights reserved.";

        $textReplacements = [
            'Essem Global Solutions' => 'Essem Digital Innovation Limited',
            'RelayIQ is operated by Essem Digital Innovation Limited.' => 'RelayIQ is a product of Essem Digital Innovation Limited.',
            'support@essemglobalsolutions.com' => 'support@essemdigital.com',
            '© RelayIQ / All rights reserved.' => $copyright,
            "{$year} © RelayIQ / All rights reserved." => $copyright,
        ];

        if (! Schema::hasTable('cms_sections')) {
            return;
        }

        $sections = DB::table('cms_sections')->get(['id', 'key', 'content']);
        foreach ($sections as $section) {
            $content = $section->content;
            $decoded = null;

            if (is_string($content)) {
                $decoded = json_decode($content, true);
                if (! is_array($decoded)) {
                    $updated = $this->replaceAll($content, $textReplacements);
                    if ($updated !== $content) {
                        DB::table('cms_sections')->where('id', $section->id)->update([
                            'content' => $updated,
                        ]);
                    }
                    continue;
                }
            } elseif (is_array($content)) {
                $decoded = $content;
            } else {
                continue;
            }

            if (($section->key ?? '') === 'footer' || array_key_exists('copyright', $decoded)) {
                $decoded['copyright'] = $copyright;
            }

            $decoded = $this->replaceDeep($decoded, $textReplacements);

            DB::table('cms_sections')->where('id', $section->id)->update([
                'content' => json_encode($decoded),
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible branding attribution update.
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function replaceAll(string $value, array $replacements): string
    {
        return str_replace(array_keys($replacements), array_values($replacements), $value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $replacements
     * @return array<string, mixed>
     */
    private function replaceDeep(array $data, array $replacements): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $data[$key] = $this->replaceAll($value, $replacements);
            } elseif (is_array($value)) {
                $data[$key] = $this->replaceDeep($value, $replacements);
            }
        }

        return $data;
    }
};
