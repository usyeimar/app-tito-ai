<?php

declare(strict_types=1);

namespace App\Enums;

enum DeploymentChannel: string
{
    case WebWidget = 'web-widget';
    case Sip = 'sip';
    case Whatsapp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::WebWidget => 'Web Widget',
            self::Sip => 'SIP',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
