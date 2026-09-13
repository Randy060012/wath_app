<?php

namespace App\Enums;

/**
 * ÉTAPE 2 — Moyens de paiement acceptés en caisse.
 * Le mobile money (Orange Money / MTN / Wave...) est incontournable
 * dans le secteur du pressing en Afrique francophone.
 */
enum PaymentMethod: string
{
    case Cash        = 'cash';
    case MobileMoney = 'mobile_money';
    case Card        = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Cash        => 'Espèces',
            self::MobileMoney => 'Mobile Money',
            self::Card        => 'Carte bancaire',
        };
    }
}
