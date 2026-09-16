<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Events\OrderMarkedReady;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ÉTAPE 2 — MODÈLE Order (le ticket de dépôt).
 * Relations :
 *  - BelongsTo Client / User
 *  - HasMany OrderItem (les articles confiés)
 *  - HasMany Payment   (acomptes + soldes)
 *
 * Le statut global est DÉRIVÉ du statut des articles (méthode syncStatus)
 * : une commande est "Prêt" quand TOUS ses articles le sont.
 */
class Order extends Model
{
    use BelongsToAgency;    protected $fillable = [
        'ticket_no', 'client_id', 'user_id', 'status', 'total_amount',
        'discount_amount', 'is_express', 'promised_at', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'       => OrderStatus::class,
            'total_amount' => 'decimal:2',
            'is_express'   => 'boolean',
            'promised_at'  => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Le caissier qui a enregistré le dépôt. */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /* -----------------------------------------------------------------
     | Logique métier dérivée (argent & statut)
     | ----------------------------------------------------------------- */

    /** Montant total net après remise. */
    public function getNetAmountAttribute(): float
    {
        return (float) $this->total_amount - (float) $this->discount_amount;
    }

    /** Somme déjà encaissée (acompte(s) + soldes). */
    public function getPaidAmountAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /** Reste à payer au retrait. */
    public function getBalanceDueAttribute(): float
    {
        return max(0, $this->net_amount - $this->paid_amount);
    }

    /**
     * Recalcule le statut global de la commande à partir des articles,
     * puis le persiste. Appelé par l'Observer d'OrderItem après chaque
     * changement de statut individuel.
     */
    public function syncStatus(): void
    {
        $statuses = $this->items()->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        // Priorité : un article "perdu" marque la commande ; sinon le
        // statut global = statut le MOINS avancé des articles
        // (une commande n'est "Prêt" que si tous ses articles le sont).
        if ($statuses->contains(OrderStatus::Perdu->value)) {
            $this->update(['status' => OrderStatus::Perdu]);
            return;
        }

        $order = [
            OrderStatus::Recu->value    => 0,
            OrderStatus::EnCours->value => 1,
            OrderStatus::Repasse->value => 2,
            OrderStatus::Pret->value    => 3,
            OrderStatus::Livre->value   => 4,
        ];

        // Le pluck('status') retourne des ENUMS (cast Eloquent) :
        // on compare via ->value (représentation string en base).
        $lowest = $statuses->min(fn ($s) => $order[$s->value] ?? 0);

        $new = OrderStatus::from(array_search($lowest, $order, true));
        $this->update(['status' => $new]);

        // ÉTAPE 3 — WORKFLOW : au passage à PRÊT (et seulement lors de la
        // transition), on diffuse l'événement qui déclenchera les
        // notifications client (mail / SMS / WhatsApp / database).
        if ($new === OrderStatus::Pret && $this->wasChanged('status')) {
            OrderMarkedReady::dispatch($this);
        }
    }

    /**
     * Éligibilité au passage rapide "Prêt" (menu contextuel des dépôts) :
     * il faut que TOUS les articles soient déjà "Repassé" (le linge est
     * fini) et que la commande soit encore ouverte. Testé en mémoire
     * depuis la liste : pas de requête supplémentaire par ligne.
     */
    public function canBeMarkedReady(): bool
    {
        return $this->relationLoaded('items')
            && $this->items->isNotEmpty()
            && $this->status !== OrderStatus::Livre
            && $this->items->every(fn (OrderItem $i) => $i->status === OrderStatus::Repasse);
    }

    /* -----------------------------------------------------------------
     | Scopes réutilisables (listes caisse / tableaux de bord)
     | ----------------------------------------------------------------- */

    /** Dossiers encore ouverts (non livrés). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(fn ($s) => $s->value, OrderStatus::openOnes()));
    }

    /** Dépôts du jour. */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }
}
