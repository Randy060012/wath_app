<?php

namespace App\Models\Concerns;

use App\Models\Agency;
use App\Support\AgencyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MULTI-TENANT — TRAIT BelongsToAgency
 * -----------------------------------------------------------------
 * À utiliser sur tout modèle portant une colonne `agency_id` :
 *  - applique un SCOPE GLOBAL : toute requête Eloquent est
 *    automatiquement restreinte à l'agence du contexte courant
 *    (AgencyContext::id()). Sauf pour la vue GROUPE (super-admin),
 *    qui voit toutes les agences ;
 *  - remplit agency_id À LA CRÉATION avec l'agence courante
 *    (une donnée créée en agence appartient à l'agence).
 *
 * Pour outrepasser le scope (ex: rapports groupe, jobs système),
 * utiliser explicitement Model::withoutGlobalScopes() ou
 * ->withAgency().
 */
trait BelongsToAgency
{
    /** L'agence propriétaire (null = donnée globale). */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Scope global : filtre sur l'agence du contexte de session.
     * Ne s'applique PAS en console/seeders (AgencyContext vide) ni
     * pour le super-admin en vue groupe — le reste de l'app est
     * responsable d'afficher l'origine dans ce cas.
     */
    public static function bootBelongsToAgency(): void
    {
        static::addGlobalScope('agency', function (Builder $builder): void {
            $agencyId = AgencyContext::id();

            if ($agencyId !== null) {
                $builder->where($builder->getModel()->getTable() . '.agency_id', $agencyId);
            }
        });

        // Création : l'agence courante est gravée dans la ligne.
        static::creating(function (Model $model): void {
            if ($model->agency_id === null) {
                $model->agency_id = AgencyContext::id();
            }
        });
    }

    /** Outrepasser le filtre d'agence (ex: super-admin, statistiques groupe). */
    public function scopeWithAgency(Builder $query): Builder
    {
        return $query->withoutGlobalScope('agency');
    }

    /** Restreindre explicitement à une agence donnée (vue groupe). */
    public function scopeForAgency(Builder $query, ?int $agencyId): Builder
    {
        return $agencyId === null
            ? $query->withAgency()
            : $query->withAgency()->where($query->getModel()->getTable() . '.agency_id', $agencyId);
    }
}
