<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use App\Support\PlatformDateTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformDateTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_utc_platform_setting_falls_back_to_nairobi_for_naive_datetimes(): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create(['platform_name' => 'RelayIQ', 'default_timezone' => 'UTC']);

        $this->assertSame('Africa/Nairobi', PlatformDateTime::timezone());

        $parsed = PlatformDateTime::parse('2026-08-28T12:32');
        $this->assertNotNull($parsed);
        $this->assertSame('09:32', $parsed->utc()->format('H:i'));
        $this->assertStringContainsString('+03:00', PlatformDateTime::toApiString($parsed));
    }

    public function test_explicit_offset_is_kept(): void
    {
        $parsed = PlatformDateTime::parse('2026-08-28T12:32:00Z');
        $this->assertSame('12:32', $parsed?->utc()->format('H:i'));
    }

    public function test_configured_platform_timezone_is_used(): void
    {
        PlatformSetting::query()->delete();
        PlatformSetting::create(['platform_name' => 'RelayIQ', 'default_timezone' => 'Africa/Lagos']);

        $this->assertSame('Africa/Lagos', PlatformDateTime::timezone());
        $parsed = PlatformDateTime::parse('2026-08-28T12:32');
        $this->assertSame('11:32', $parsed?->utc()->format('H:i'));
    }

    public function test_empty_value_is_null(): void
    {
        $this->assertNull(PlatformDateTime::parse(''));
        $this->assertNull(PlatformDateTime::parse(null));
        $this->assertNull(PlatformDateTime::toApiString(null));
        $this->assertInstanceOf(Carbon::class, PlatformDateTime::parse('2026-08-28 12:32'));
    }
}
