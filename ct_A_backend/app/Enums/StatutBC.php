<?php

namespace App\Enums;

enum StatutBC: string
{
    case NC = 'NC';
    case EN_COURS_A = 'EN_COURS_A';
    case EN_COURS_CDG = 'EN_COURS_CDG';
    case EN_COURS_DFC = 'EN_COURS_DFC';
    case EN_COURS_DG = 'EN_COURS_DG';
    case DAC_CDG = 'DAC_CDG';
    case DAC_DG = 'DAC_DG';
    case DAC_TRESO = 'DAC_TRESO';
    case EN_COURS_FSSEUR = 'EN_COURS_FSSEUR';
    case LIVRAISON_PARTIELLE = 'LIVRAISON_PARTIELLE';
    case LIVRE = 'LIVRE';
    case TRAITE = 'TRAITE';
    case ANNULE = 'ANNULE';

    public function label(): string
    {
        return match($this) {
            self::NC => 'N/C',
            self::EN_COURS_A => 'En Cours A',
            self::EN_COURS_CDG => 'En Cours CDG',
            self::EN_COURS_DFC => 'En cours DFC',
            self::EN_COURS_DG => 'En cours DG',
            self::DAC_CDG => 'DAC CDG',
            self::DAC_DG => 'DAC DG',
            self::DAC_TRESO => 'DAC Tréso',
            self::EN_COURS_FSSEUR => 'En cours F/sseur',
            self::LIVRAISON_PARTIELLE => 'Livraison Partielle',
            self::LIVRE => 'Livré',
            self::TRAITE => 'Traité',
            self::ANNULE => 'Annulé',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::NC => 'gray',
            self::EN_COURS_A => 'blue',
            self::EN_COURS_CDG => 'yellow',
            self::EN_COURS_DFC => 'orange',
            self::EN_COURS_DG => 'purple',
            self::DAC_CDG => 'cyan',
            self::DAC_DG => 'purple',
            self::DAC_TRESO => 'pink',
            self::EN_COURS_FSSEUR => 'indigo',
            self::LIVRAISON_PARTIELLE => 'amber',
            self::LIVRE => 'lime',
            self::TRAITE => 'green',
            self::ANNULE => 'red',
        };
    }

    public function isDac(): bool
    {
        return in_array($this, [self::DAC_CDG, self::DAC_DG, self::DAC_TRESO]);
    }

    public function isEnCours(): bool
    {
        return in_array($this, [
            self::NC,
            self::EN_COURS_A,
            self::EN_COURS_CDG,
            self::EN_COURS_DFC,
            self::EN_COURS_DG,
            self::DAC_CDG,
            self::DAC_DG,
            self::EN_COURS_FSSEUR,
        ]);
    }
}
