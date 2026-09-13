<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ÉTAPE 2 — MODÈLE Service (prestation du catalogue).
 * Relations :
 *  - BelongsTo Category (la prestation appartient à une famille)
 *  - HasMany OrderItem  (les lignes de commande qui y réfèrent)
 */
class Service extends Model
{
    protected $fillable = [
        'category_id', 'name', 'price', 'pricing_unit',
        'default_hours', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'     => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
