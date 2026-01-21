<?php

namespace App\Enums;

enum PeriodiciteContrat: string
{
    case MENSUEL = 'MENSUEL';
    case TRIMESTRIEL = 'TRIMESTRIEL';
    case SEMESTRIEL = 'SEMESTRIEL';
    case ANNUEL = 'ANNUEL';
    case PONCTUEL = 'PONCTUEL';

    public function label(): string
    {
        return match($this) {
            self::MENSUEL => 'Mensuel',
            self::TRIMESTRIEL => 'Trimestriel',
            self::SEMESTRIEL => 'Semestriel',
            self::ANNUEL => 'Annuel',
            self::PONCTUEL => 'Ponctuel',
        };
    }

    public function nombreMois(): int
    {
        return match($this) {
            self::MENSUEL => 1,
            self::TRIMESTRIEL => 3,
            self::SEMESTRIEL => 6,
            self::ANNUEL => 12,
            self::PONCTUEL => 0,
        };
    }

    public function nombreEcheancesParAn(): int
    {
        return match($this) {
            self::MENSUEL => 12,
            self::TRIMESTRIEL => 4,
            self::SEMESTRIEL => 2,
            self::ANNUEL => 1,
            self::PONCTUEL => 1,
        };
    }
}
