<?php

namespace App\Enums;

enum TypeDemande: string
{
    case DA = 'DA';
    case DAC = 'DAC';

    public function label(): string
    {
        return match($this) {
            self::DA => 'Demande d\'Achat',
            self::DAC => 'Demande d\'Achat Caisse',
        };
    }
}
