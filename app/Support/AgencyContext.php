<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * MULTI-TENANT — CONTEXTE D'AGENCE (session)
 * -----------------------------------------------------------------
 * Petit service statique : mémorise en SESSION l'agence dans laquelle
 * l'utilisateur travaille. Toutes les requêtes métier sont filtrées
 * par ce contexte (scope global BelongsToAgency), garantissant
 * l'ISOLATION entre agences d'une même base.
 *
 *  - null = GROUPE (super-admin uniquement) : aucune contrainte,
 *    on voit tout, on peut créer des données globales (agency_id null) ;
 *  - id   = agence imposée : seules ses données sont visibles/éditables.
 */
class AgencyContext
{
    /** Clé de session. */
    public const SESSION_KEY = 'agency_id';

    /**
     * L'agence courante (id) pour l'utilisateur connecté.
     * Super-admin (users.agency_id null + rôle admin) : suit son
     * sélecteur de session, sinon null (= vue GROUPE).
     * Utilisateur d'agence : TOUJOURS sa propre agence — le sélecteur
     * ne peut pas la contourner.
     */
    public static function id(): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        // Un utilisateur rattaché à une agence y est enfermé.
        if ($user->agency_id !== null) {
            return (int) $user->agency_id;
        }

        // Super-admin : contexte choisi (sélecteur) ou groupe (null).
        $session = session(self::SESSION_KEY);

        return $session !== null ? (int) $session : null;
    }

    /** Vrai si l'utilisateur courant voit TOUT le groupe (super-admin sans sélection). */
    public static function isGroupScope(): bool
    {
        return self::id() === null;
    }

    /** Agence courante (modèle) ou null en vue groupe. */
    public static function agency(): ?object
    {
        $id = self::id();

        return $id !== null ? \App\Models\Agency::find($id) : null;
    }

    /**
     * Fixe le contexte de la session (super-admin uniquement).
     * $agencyId null = retour à la vue GROUPE.
     */
    public static function set(?int $agencyId): void
    {
        session([self::SESSION_KEY => $agencyId]);
    }

    /**
     * L'application est-elle installée avec la table agencies ?
     * (sécurise les commandes artisan et les seeders qui tournent
     * avant la migration multi-tenant).
     */
    public static function schemaReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('agencies');
    }
}
