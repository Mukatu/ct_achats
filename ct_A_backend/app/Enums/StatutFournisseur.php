<?php

namespace App\Enums;

enum StatutFournisseur: string
{
    case PROSPECT = 'PROSPECT';
    case EN_VALIDATION = 'EN_VALIDATION';
    case ACTIF = 'ACTIF';
    case SUSPENDU = 'SUSPENDU';
    case BLOQUE = 'BLOQUE';
    case INACTIF = 'INACTIF';

    public function label(): string
    {
        return match($this) {
            self::PROSPECT => 'Prospect',
            self::EN_VALIDATION => 'En validation',
            self::ACTIF => 'Actif',
            self::SUSPENDU => 'Suspendu',
            self::BLOQUE => 'Bloqué',
            self::INACTIF => 'Inactif',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PROSPECT => 'gray',
            self::EN_VALIDATION => 'yellow',
            self::ACTIF => 'green',
            self::SUSPENDU => 'orange',
            self::BLOQUE => 'red',
            self::INACTIF => 'gray',
        };
    }

    public function canOrder(): bool
    {
        return $this === self::ACTIF;
    }
}
