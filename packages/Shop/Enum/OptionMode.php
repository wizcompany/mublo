<?php

namespace Mublo\Packages\Shop\Enum;

enum OptionMode: string
{
    case NONE = 'NONE';
    case SINGLE = 'SINGLE';
    case COMBINATION = 'COMBINATION';

    public function label(): string
    {
        return match ($this) {
            self::NONE => '옵션 없음',
            self::SINGLE => '단독형',
            self::COMBINATION => '조합형',
        };
    }

    public function hasOptions(): bool
    {
        return $this !== self::NONE;
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
