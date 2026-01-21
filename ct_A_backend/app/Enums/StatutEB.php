<?php

namespace App\Enums;

enum StatutEB: string
{
    case EN_SUSPENS = 'EN_SUSPENS';
    case EN_COURS_ACH = 'EN_COURS_ACH';
    case EN_COURS_CDG = 'EN_COURS_CDG';
    case EN_COURS_DFC = 'EN_COURS_DFC';
    case EN_COURS_DG = 'EN_COURS_DG';
    case TRAITE = 'TRAITE';
    case ANNULE = 'ANNULE';
    case NA = 'NA';

    public function label(): string
    {
        return match($this) {
            self::EN_SUSPENS => 'En Suspens / NC',
            self::EN_COURS_ACH => 'OK / En cours ACH',
            self::EN_COURS_CDG => 'OK / En cours CDG',
            self::EN_COURS_DFC => 'OK / En cours DFC',
            self::EN_COURS_DG => 'OK / En cours DG',
            self::TRAITE => 'OK / Traitée',
            self::ANNULE => 'OK / Annulée',
            self::NA => 'OK/NA',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::EN_SUSPENS => 'gray',
            self::EN_COURS_ACH => 'blue',
            self::EN_COURS_CDG => 'yellow',
            self::EN_COURS_DFC => 'orange',
            self::EN_COURS_DG => 'purple',
            self::TRAITE => 'green',
            self::ANNULE => 'red',
            self::NA => 'gray',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::EN_SUSPENS, self::EN_COURS_ACH]);
    }

    public function canCancel(): bool
    {
        return !in_array($this, [self::TRAITE, self::ANNULE]);
    }

    public static function actifs(): array
    {
        return [
            self::EN_SUSPENS,
            self::EN_COURS_ACH,
            self::EN_COURS_CDG,
            self::EN_COURS_DFC,
            self::EN_COURS_DG,
        ];
    }
}
