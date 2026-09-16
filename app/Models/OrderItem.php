<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ÉTAPE 2 — MODÈLE OrderItem (un vêtement / lot confié).
 * Relations :
 *  - BelongsTo Order  (le dépôt parent)
 *  - BelongsTo Service (la prestation facturée)
 *  - HasMany StatusLog (la timeline d'audit de l'article)
 *
 * C'est LE modèle central du suivi atelier : code-barres unique,
 * statut individuel, emplacement physique.
 */
class OrderItem extends Model
{
    use BelongsToAgency;    protected $fillable = [
        'order_id', 'service_id', 'barcode', 'description', 'status',
        'unit_price', 'quantity', 'line_total', 'location',
    ];

    protected function casts(): array
    {
        return [
            'status'     => OrderStatus::class,
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(StatusLog::class)->latest('created_at');
    }

    /* -----------------------------------------------------------------
     | Suivi atelier : statut + emplacement
     | ----------------------------------------------------------------- */

    /**
     * Change le statut de l'article après validation par la machine
     * à états (OrderStatus::canTransitionTo). Journalisation et sync
     * de la commande parente sont confiés à l'Observer OrderItemObserver
     * (évt 'updated') → aucune logique dispersée dans les contrôleurs.
     *
     * @throws \InvalidArgumentException si la transition est interdite
     */
    public function markStatus(OrderStatus $target, ?string $location = null): void
    {
        $current = $this->status;

        if (! $current->canTransitionTo($target)) {
            throw new \InvalidArgumentException(
                "Transition interdite : {$current->label()} vers {$target->label()}."
            );
        }

        if ($location !== null) {
            $this->location = $location;
        }

        $this->status = $target;
        $this->save();
    }

    /** Scope : articles au statut donné (écrans atelier). */
    public function scopeWithStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /** Scope : articles scannables = pas encore livrés ni perdus. */
    public function scopeScannable(Builder $query): Builder
    {
        return $query->whereIn('status', [OrderStatus::Recu, OrderStatus::EnCours, OrderStatus::Repasse]);
    }
}
