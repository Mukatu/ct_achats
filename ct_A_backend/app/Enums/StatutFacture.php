<?php

namespace App\Enums;

enum StatutFacture: string
{
    case BROUILLON = 'BROUILLON';
    case A_RAPPROCHER = 'A_RAPPROCHER';
    case EN_RAPPROCHEMENT = 'EN_RAPPROCHEMENT';
    case RAPPROCHEE = 'RAPPROCHEE';
    case A_VALIDER = 'A_VALIDER';
    case VALIDEE = 'VALIDEE';
    case EN_LITIGE = 'EN_LITIGE';
    case REJETEE = 'REJETEE';
    case ANNULEE = 'ANNULEE';

    public function label(): string
    {
        return match($this) {
            self::BROUILLON => 'Brouillon',
            self::A_RAPPROCHER => 'A rapprocher',
            self::EN_RAPPROCHEMENT => 'En rapprochement',
            self::RAPPROCHEE => 'Rapprochee',
            self::A_VALIDER => 'A valider',
            self::VALIDEE => 'Validee',
            self::EN_LITIGE => 'En litige',
            self::REJETEE => 'Rejetee',
            self::ANNULEE => 'Annulee',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::BROUILLON => 'gray',
            self::A_RAPPROCHER => 'blue',
            self::EN_RAPPROCHEMENT => 'indigo',
            self::RAPPROCHEE => 'cyan',
            self::A_VALIDER => 'yellow',
            self::VALIDEE => 'green',
            self::EN_LITIGE => 'orange',
            self::REJETEE => 'red',
            self::ANNULEE => 'red',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::BROUILLON, self::A_RAPPROCHER]);
    }

    public function canValidate(): bool
    {
        return $this === self::A_VALIDER;
    }

    public function canRapprocher(): bool
    {
        return in_array($this, [self::A_RAPPROCHER, self::EN_RAPPROCHEMENT]);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::BROUILLON, self::A_RAPPROCHER, self::EN_LITIGE]);
    }
}
