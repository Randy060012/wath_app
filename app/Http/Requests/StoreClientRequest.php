<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ÉTAPE 2/3 — VALIDATION StoreClientRequest (fiche client).
 * Utilisée à la fois par store() et update() (règle unique tolérante).
 */
class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $clientId = $this->route('client')?->id ?? $this->route('client');

        return [
            'name'  => ['required', 'string', 'max:120'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                // Unicité du téléphone (recherche caisse) sauf soi-même
                Rule::unique('clients', 'phone')->ignore($clientId),
            ],
            'email'   => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:300'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'  => 'Le nom du client est obligatoire.',
            'name.max'       => 'Le nom ne peut pas dépasser 120 caractères.',
            'phone.unique'   => 'Ce numéro existe déjà pour un autre client.',
            'email.email'    => 'Adresse e-mail invalide.',
        ];
    }
}
