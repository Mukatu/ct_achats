<?php

namespace App\Enums;

enum TypeReception: string
{
    case LIVRAISON = 'LIVRAISON';
    case SERVICE_FAIT = 'SERVICE_FAIT';
    case PARTIELLE = 'PARTIELLE';

    public function label(): string
    {
        return match($this) {
            self::LIVRAISON => 'Livraison de marchandises',
            self::SERVICE_FAIT => 'Service fait (prestation)',
            self::PARTIELLE => 'Réception partielle',
        };
    }
}
