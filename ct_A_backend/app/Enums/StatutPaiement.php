<?php

namespace App\Enums;

enum StatutPaiement: string
{
    case NON_PAYEE = 'NON_PAYEE';
    case PARTIEL = 'PARTIEL';
    case EN_PAIEMENT = 'EN_PAIEMENT';
    case PAYEE = 'PAYEE';
    case SUSPENDUE = 'SUSPENDUE';

    public function label(): string
    {
        return match($this) {
            self::NON_PAYEE => 'Non payee',
            self::PARTIEL => 'Paiement partiel',
            self::EN_PAIEMENT => 'En paiement',
            self::PAYEE => 'Payee',
            self::SUSPENDUE => 'Suspendue',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NON_PAYEE => 'gray',
            self::PARTIEL => 'yellow',
            self::EN_PAIEMENT => 'blue',
            self::PAYEE => 'green',
            self::SUSPENDUE => 'red',
        };
    }
}
