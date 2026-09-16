<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

/**
 * ÉTAPE 2 — MODÈLE Inventory (fourniture en stock).
 * Pas de relation : table de gestion interne.
 * Accessoire helper : isLowStock() pour l'alerte dashboard.
 */
class Inventory extends Model
{
    use BelongsToAgency;
    protected $fillable = ['name', 'unit', 'quantity', 'min_quantity', 'unit_cost', 'notes'];

    protected function casts(): array
    {
        return [
            'quantity'    => 'decimal:2',
            'min_quantity' => 'decimal:2',
            'unit_cost'   => 'decimal:2',
        ];
    }

    /** Le stock est-il sous le seuil d'alerte ? */
    public function isLowStock(): bool
    {
        return $this->quantity <= $this->min_quantity;
    }
}
