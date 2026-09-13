<?php

namespace App\Enums;

/**
 * SPÉCIFICATIONS B — STATUT DYNAMIQUE ACTEUR / CLIENT
 * -----------------------------------------------------------------
 *  - Acteur (prospect) : personne enregistrée sans AUCUN dépôt validé
 *    (demande de renseignements, réception d'un proforma...).
 *  - Client : a effectué au moins un dépôt validé. La transition
 *    Acteur → Client est AUTOMATIQUE dès la validation d'un dépôt
 *    (OrderService::createOrder → Client::promoteToClientIfActeur()).
 */
enum ClientType: string
{
    case Acteur = 'acteur';
    case Client  = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Acteur => 'Acteur',
            self::Client => 'Client',
        };
    }

    /** Classes Tailwind des badges (détection via @source app/Enums). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Acteur => 'bg-amber-100 text-amber-800',
            self::Client => 'bg-teal-100 text-teal-800',
        };
    }
}
