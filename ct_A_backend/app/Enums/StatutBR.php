<?php

namespace App\Enums;

enum StatutBR: string
{
    case BROUILLON = 'BROUILLON';
    case VALIDEE = 'VALIDEE';
    case EN_LITIGE = 'EN_LITIGE';
    case ANNULEE = 'ANNULEE';

    public function label(): string
    {
        return match($this) {
            self::BROUILLON => 'Brouillon',
            self::VALIDEE => 'Validée',
            self::EN_LITIGE => 'En litige',
            self::ANNULEE => 'Annulée',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::BROUILLON => 'gray',
            self::VALIDEE => 'green',
            self::EN_LITIGE => 'orange',
            self::ANNULEE => 'red',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::BROUILLON;
    }

    public function canValidate(): bool
    {
        return $this === self::BROUILLON;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::BROUILLON, self::EN_LITIGE]);
    }
}
