<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CT_Achats Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration spécifique pour l'application de gestion des achats
    | République du Congo
    |
    */

    'app_name' => 'CT_Achats',
    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Paramètres régionaux
    |--------------------------------------------------------------------------
    */
    'pays' => 'COG',
    'devise' => 'XAF',
    'symbole_devise' => 'FCFA',
    'taux_tva_defaut' => 18.00,
    'taux_tva_reduit' => 5.00,

    // Parité fixe EUR/XAF
    'taux_change_eur' => 655.957,

    /*
    |--------------------------------------------------------------------------
    | Seuils de validation (en XAF)
    |--------------------------------------------------------------------------
    */
    'seuils' => [
        'acheteur' => 0,              // Toutes les demandes
        'cdg' => 500000,              // > 500 000 FCFA
        'dfc' => 5000000,             // > 5 000 000 FCFA
        'dg' => 50000000,             // > 50 000 000 FCFA
    ],

    /*
    |--------------------------------------------------------------------------
    | Numérotation
    |--------------------------------------------------------------------------
    */
    'numerotation' => [
        'eb' => [
            'format' => 'NNNN/AA - EB',
            'longueur' => 4,
        ],
        'da' => [
            'format' => 'NNNN/AA - DA',
            'longueur' => 4,
        ],
        'dac' => [
            'format' => 'NNNN/AA - DAC',
            'longueur' => 4,
        ],
        'bc' => [
            'format' => 'NNN/AA',
            'longueur' => 3,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documents fournisseur obligatoires
    |--------------------------------------------------------------------------
    */
    'documents_fournisseur' => [
        'obligatoires' => ['RCCM', 'NIU', 'PATENTE', 'CNSS', 'ATT_FISCALE', 'RIB'],
        'optionnels' => ['ASSURANCE_RC', 'AGREMENT', 'CERTIFICATION', 'CONTRAT'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validité des documents (en jours)
    |--------------------------------------------------------------------------
    */
    'validite_documents' => [
        'PATENTE' => 365,       // Annuelle
        'CNSS' => 90,           // Trimestrielle
        'ATT_FISCALE' => 90,    // 3 mois
        'ASSURANCE_RC' => 365,  // Annuelle
    ],

    /*
    |--------------------------------------------------------------------------
    | Conditions de paiement
    |--------------------------------------------------------------------------
    */
    'conditions_paiement' => [
        'COMPTANT' => 'Paiement comptant',
        '30J_NET' => '30 jours net',
        '30J_FDM' => '30 jours fin de mois',
        '45J_NET' => '45 jours net',
        '60J_NET' => '60 jours net',
        'AVANCE_30' => '30% à la commande',
        'AVANCE_50' => '50% à la commande',
        'ECHELONNE' => 'Paiement échelonné',
    ],

    /*
    |--------------------------------------------------------------------------
    | Banques principales Congo
    |--------------------------------------------------------------------------
    */
    'banques' => [
        '30001' => 'BGFI Bank Congo',
        '30002' => 'Société Générale Congo',
        '30003' => 'Crédit du Congo',
        '30004' => 'LCB Bank',
        '30005' => 'UBA Congo',
        '30006' => 'Ecobank Congo',
        '30007' => 'BSCA Congo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Interface
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'couleur_menu' => '#3b82f6',      // Bleu
        'couleur_bouton' => '#f97316',    // Orange
    ],
];
