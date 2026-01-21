<?php

namespace App\Enums;

enum TypeFacture: string
{
    case FACTURE = 'FACTURE';
    case AVOIR = 'AVOIR';
    case ACOMPTE = 'ACOMPTE';
    case SITUATION = 'SITUATION';

    public function label(): string
    {
        return match($this) {
            self::FACTURE => 'Facture',
            self::AVOIR => 'Avoir',
            self::ACOMPTE => 'Acompte',
            self::SITUATION => 'Situation',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::FACTURE => 'blue',
            self::AVOIR => 'red',
            self::ACOMPTE => 'yellow',
            self::SITUATION => 'purple',
        };
    }
}
