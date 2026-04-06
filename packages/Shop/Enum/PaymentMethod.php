<?php

namespace Mublo\Packages\Shop\Enum;

enum PaymentMethod: string
{
    case CARD = 'CARD';
    case PHONE = 'PHONE';
    case VBANK = 'VBANK';
    case BANK = 'BANK';

    public function label(): string
    {
        return match ($this) {
            self::CARD => '신용카드',
            self::PHONE => '휴대폰 결제',
            self::VBANK => '가상계좌',
            self::BANK => '무통장입금',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }
}
