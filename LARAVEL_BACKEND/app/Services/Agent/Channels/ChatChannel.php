<?php

namespace App\Services\Agent\Channels;

/**
 * Supported customer conversation channels (Concept 23).
 */
final class ChatChannel
{
    public const WHATSAPP = 'whatsapp';

    public const WEB_WIDGET = 'web_widget';

    public const EMAIL = 'email';

    public const INSTAGRAM_DM = 'instagram_dm';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::WHATSAPP,
            self::WEB_WIDGET,
            self::EMAIL,
            self::INSTAGRAM_DM,
        ];
    }
}
