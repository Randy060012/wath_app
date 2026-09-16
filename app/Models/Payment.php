<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ÉTAPE 2 — MODÈLE Payment (encaissement).
 * Relations :
 *  - BelongsTo Order (le ticket concerné)
 *  - BelongsTo User  (l'employé qui a encaissé)
 * Immuable : jamais modifié/supprimé, on annule par écriture négative.
 */
class Payment extends Model
{
    use BelongsToAgency;
    protected $fillable = ['order_id', 'user_id', 'amount', 'method', 'reference'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            // Enum : $payment->method->label() / ->value partout (vues, stats)
            'method' => \App\Enums\PaymentMethod::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
