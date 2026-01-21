<?php

namespace App\Enums;

enum StatutContrat: string
{
    case BROUILLON = 'BROUILLON';
    case ACTIF = 'ACTIF';
    case SUSPENDU = 'SUSPENDU';
    case TERMINE = 'TERMINE';
    case RESILIE = 'RESILIE';

    public function label(): string
    {
        return match($this) {
            self::BROUILLON => 'Brouillon',
            self::ACTIF => 'Actif',
            self::SUSPENDU => 'Suspendu',
            self::TERMINE => 'Termine',
            self::RESILIE => 'Resilie',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::BROUILLON => 'gray',
            self::ACTIF => 'green',
            self::SUSPENDU => 'yellow',
            self::TERMINE => 'blue',
            self::RESILIE => 'red',
        };
    }

    public function isActif(): bool
    {
        return $this === self::ACTIF;
    }

    public function canBeEdited(): bool
    {
        return in_array($this, [self::BROUILLON, self::ACTIF, self::SUSPENDU]);
    }
}
