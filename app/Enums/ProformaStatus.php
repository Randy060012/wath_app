<?php

namespace App\Enums;

/**
 * SPÉCIFICATIONS C — CYCLE DE VIE D'UN PROFORMA (devis)
 * -----------------------------------------------------------------
 * brouillon : créé, non encore transmis au tiers ;
 * envoye    : transmis par e-mail (ou remis en main propre) ;
 * accepte   : le tiers a donné suite favorable ;
 * refuse    : suite défavorable (motif éventuel en notes) ;
 * expire    : validité dépassée sans réponse (marqué à l'affichage).
 */
enum ProformaStatus: string
{
    case Brouillon = 'brouillon';
    case Envoye    = 'envoye';
    case Accepte   = 'accepte';
    case Refuse    = 'refuse';
    case Expire    = 'expire';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Envoye    => 'Envoyé',
            self::Accepte   => 'Accepté',
            self::Refuse    => 'Refusé',
            self::Expire    => 'Expiré',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Brouillon => 'bg-slate-100 text-slate-700',
            self::Envoye    => 'bg-sky-100 text-sky-800',
            self::Accepte   => 'bg-emerald-100 text-emerald-800',
            self::Refuse    => 'bg-rose-100 text-rose-800',
            self::Expire    => 'bg-amber-100 text-amber-800',
        };
    }
}
