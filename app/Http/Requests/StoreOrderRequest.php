<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * ÉTAPE 3 — VALIDATION StoreOrderRequest (caisse : création de dépôt)
 * -----------------------------------------------------------------
 * La validation est TOUJOURS dans une FormRequest, jamais dans le
 * contrôleur : réutilisable, testable, messages centralisés en FR.
 * Autorisation : liée au rôle caissier via la route (middleware role).
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // l'autorisation est gérée par le middleware 'role'
    }

    /**
     * Règles : un dépôt = un client + au moins une ligne de prestation.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id'            => ['required', 'integer', 'exists:clients,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.service_id'   => ['required', 'integer', 'exists:services,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.description'  => ['nullable', 'string', 'max:200'],
            'discount_amount'      => ['nullable', 'numeric', 'min:0'],
            'is_express'           => ['nullable', 'boolean'],
            'promised_at'          => ['nullable', 'date', 'after:now'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'deposit_amount'       => ['nullable', 'numeric', 'min:0'],
            'deposit_method'       => ['nullable', 'in:cash,mobile_money,card'],
        ];
    }

    /**
     * Messages d'erreur en français (affichés tels quels en caisse).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_id.required'      => 'Le client est obligatoire.',
            'items.required'          => 'Ajoutez au moins une prestation au dépôt.',
            'items.min'               => 'Ajoutez au moins une prestation au dépôt.',
            'items.*.service_id.*'    => 'Prestation invalide.',
            'items.*.quantity.*'      => 'La quantité doit être entre 1 et 99.',
            'deposit_amount.min'      => 'L\'acompte ne peut pas être négatif.',
            'deposit_amount.lte'      => 'L\'acompte dépasse le montant dû.',
        ];
    }

    /**
     * Normalisation avant validation : "1 500,50" → 1500.50
     * (les caissiers saisissent souvent les montants à la française).
     */
    protected function prepareForValidation(): void
    {
        foreach (['discount_amount', 'deposit_amount'] as $field) {
            $value = $this->input($field);
            if (filled($value)) {
                $this->merge([
                    $field => str_replace([' ', ','], ['', '.'], (string) $value),
                ]);
            }
        }
    }
}
