<?php

namespace App\Enums;

enum TypeBC: string
{
    case BCAL = 'BCAL';
    case BCL = 'BCL';
    case BCAI = 'BCAI';
    case BCI = 'BCI';
    case IPO = 'IPO';

    public function label(): string
    {
        return match($this) {
            self::BCAL => 'BC Achat Local',
            self::BCL => 'BC Local',
            self::BCAI => 'BC Achat International',
            self::BCI => 'BC International',
            self::IPO => 'International Purchase Order',
        };
    }

    public function isInternational(): bool
    {
        return in_array($this, [self::BCAI, self::BCI, self::IPO]);
    }

    public function isLocal(): bool
    {
        return in_array($this, [self::BCAL, self::BCL]);
    }
}
