<?php

namespace App\Enums;

/**
 * ÉTAPE 2 — MODÉLISATION MÉTIER
 * -----------------------------------------------------------------
 * Cycle de vie d'un article confié au pressing :
 *   RECU  →  EN_COURS  →  REPASSE  →  PRET  →  LIVRE
 * Statut exceptionnel : PERDU (article égaré, déclenché par l'atelier).
 *
 * Un enum PHP backed est utilisé (et non des constantes de classe) car
 * il est nativement sérialisable en base (string), auto-complété par
 * l'IDE et offre un seul point de vérité pour labels + couleurs + règles
 * de transition (machine à états).
 */
enum OrderStatus: string
{
    case Recu      = 'recu';       // Article réceptionné à la caisse
    case EnCours   = 'en_cours';   // Lavage / nettoyage à sec en cours
    case Repasse   = 'repasse';    // Repassage / finition terminés
    case Pret      = 'pret';       // Conditionné et prêt au retrait
    case Livre     = 'livre';      // Remis au client, dossier soldé
    case Perdu     = 'perdu';      // Incident : article égaré

    /**
     * Libellé affichable (français) — utilisé dans les vues Blade,
     * les notifications et les status_logs.
     */
    public function label(): string
    {
        return match ($this) {
            self::Recu    => 'Reçu',
            self::EnCours => 'En cours de lavage',
            self::Repasse => 'Repassé',
            self::Pret    => 'Prêt',
            self::Livre   => 'Livré',
            self::Perdu   => 'Perdu',
        };
    }

    /**
     * Classes Tailwind du badge coloré associé au statut.
     * Centralisé ici pour garantir une UI cohérente partout.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Recu    => 'bg-slate-100 text-slate-700 ring-slate-300',
            self::EnCours => 'bg-amber-100 text-amber-800 ring-amber-300',
            self::Repasse => 'bg-violet-100 text-violet-800 ring-violet-300',
            self::Pret    => 'bg-emerald-100 text-emerald-800 ring-emerald-300',
            self::Livre   => 'bg-teal-100 text-teal-800 ring-teal-300',
            self::Perdu   => 'bg-rose-100 text-rose-800 ring-rose-300',
        };
    }

    /**
     * MACHINE À ÉTATS : transitions autorisées depuis ce statut.
     * Les allers-retours d'un cran en arrière sont tolérés pour
     * permettre la correction d'erreurs de scan en atelier.
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Recu    => [self::EnCours],
            self::EnCours => [self::Repasse, self::Recu, self::Perdu],
            self::Repasse => [self::Pret, self::EnCours],
            self::Pret    => [self::Livre, self::Repasse],
            self::Livre   => [],   // Terminé — plus aucune transition
            self::Perdu   => [self::EnCours], // Retrouvé !
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Statuts qui comptent comme "dossier encore ouvert" (caisse/atelier). */
    public static function openOnes(): array
    {
        return [self::Recu, self::EnCours, self::Repasse, self::Pret];
    }
}
