<?php

namespace App\Enums;

enum StatutEcheance: string
{
    case A_VENIR = 'A_VENIR';
    case A_TRAITER = 'A_TRAITER';
    case EN_COURS = 'EN_COURS';
    case PAYE = 'PAYE';
    case ANNULE = 'ANNULE';

    public function label(): string
    {
        return match($this) {
            self::A_VENIR => 'A venir',
            self::A_TRAITER => 'A traiter',
            self::EN_COURS => 'En cours',
            self::PAYE => 'Paye',
            self::ANNULE => 'Annule',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::A_VENIR => 'gray',
            self::A_TRAITER => 'yellow',
            self::EN_COURS => 'blue',
            self::PAYE => 'green',
            self::ANNULE => 'red',
        };
    }

    public function isPayable(): bool
    {
        return in_array($this, [self::A_TRAITER, self::EN_COURS]);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::PAYE, self::ANNULE]);
    }
}
