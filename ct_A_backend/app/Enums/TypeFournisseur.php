<?php

namespace App\Enums;

enum TypeFournisseur: string
{
    case LOCAL = 'LOCAL';
    case CEMAC = 'CEMAC';
    case INTERNATIONAL = 'INTERNATIONAL';

    public function label(): string
    {
        return match($this) {
            self::LOCAL => 'Fournisseur Local (Congo)',
            self::CEMAC => 'Fournisseur Zone CEMAC',
            self::INTERNATIONAL => 'Fournisseur International',
        };
    }
}
