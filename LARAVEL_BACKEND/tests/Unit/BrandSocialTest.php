<?php

namespace Tests\Unit;

use App\Support\BrandSocial;
use Tests\TestCase;

class BrandSocialTest extends TestCase
{
    public function test_official_profiles_are_instagram_and_facebook(): void
    {
        $urls = BrandSocial::urls();

        $this->assertContains('https://www.instagram.com/relayiq.app', $urls);
        $this->assertContains('https://www.facebook.com/share/1KxpxJ2VtK', $urls);
        $this->assertCount(2, $urls);
    }

    public function test_footer_links_use_stable_profile_urls(): void
    {
        $labels = array_column(BrandSocial::links(), 'label');

        $this->assertSame(['Facebook', 'Instagram'], $labels);
        $this->assertSame(
            'https://www.instagram.com/relayiq.app',
            BrandSocial::links()[1]['href']
        );
    }
}
