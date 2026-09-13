<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPÉCIFICATIONS C — LIGNE DE PROFORMA.
 * Chaque ligne fige le libellé et le prix au moment de l'édition
 * (une modification du catalogue n'altère pas les devis émis).
 * `service_id` est facultatif : ligne libre possible (prestation
 * ponctuelle non cataloguée, remise spécifique...).
 */
class ProformaItem extends Model
{
    protected $fillable = [
        'proforma_id', 'service_id', 'label', 'pricing_unit',
        'quantity', 'unit_price', 'line_total',
    ];

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
