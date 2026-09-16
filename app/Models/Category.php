<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ÉTAPE 2 — MODÈLE Category
 * Relation : Category HasMany Service (une famille contient plusieurs prestations).
 */
class Category extends Model
{
    use BelongsToAgency;    protected $fillable = ['agency_id', 'name', 'slug', 'icon', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Une catégorie regroupe plusieurs prestations (1-N). */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('name');
    }
}
