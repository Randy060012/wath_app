<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ÉTAPE 2 — MODÈLE StatusLog (entrée d'audit).
 * Relation : BelongsTo OrderItem + BelongsTo User (auteur du scan).
 * Créé automatiquement par l'Observer OrderItemObserver.
 */
class StatusLog extends Model
{
    public $timestamps = false; // seule created_at existe (useCurrent)

    protected $fillable = ['order_item_id', 'user_id', 'from', 'to', 'location', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
