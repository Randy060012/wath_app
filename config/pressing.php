<?php

/**
 * ÉTAPE 2 — CONFIGURATION MÉTIER du pressing
 * -----------------------------------------------------------------
 * Centralise les paramètres métier modifiables sans toucher au code
 * (surchargés via .env si besoin) :
 *   PRESSING_CURRENCY="FCFA"
 *   PRESSING_EXPRESS_SURCHARGE=25
 */
return [

    // Devise affichée partout dans l'interface et les notifications
    'currency' => env('PRESSING_CURRENCY', 'FCFA'),

    // Surcharge tarifaire (%) appliquée aux commandes "express / presse"
    'express_surcharge' => env('PRESSING_EXPRESS_SURCHARGE', 25),

    // Préfixe des codes-barres articles (ex: BC-2026-000001)
    'barcode_prefix' => env('PRESSING_BARCODE_PREFIX', 'BC'),

    // Listes d'aide au rangement proposées en atelier (menus déroulants).
    // Alimente l'attribution d'emplacement "Convoyeur A - Étagère 12".
    'locations' => [
        'Convoyeur A', 'Convoyeur B', 'Convoyeur C',
        'Étagère 1', 'Étagère 2', 'Étagère 3', 'Étagère 4',
        'Étagère 5', 'Étagère 6', 'Étagère 7', 'Étagère 8',
        'Étagère 9', 'Étagère 10', 'Étagère 11', 'Étagère 12',
        'Zone express', 'Zone dépôt-vente',
    ],

    // Formats d'étiquettes supportés à l'impression (largeur en mm)
    'label_width_mm' => 50,
];
