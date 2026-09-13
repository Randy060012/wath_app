<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ÉTAPE 1+2 — RECHERCHE LIVE DE CLIENTS (endpoint JSON interne)
 * -----------------------------------------------------------------
 * Consommé par le JavaScript de la caisse (fetch + Alpine) pour
 * l'autocomplétion "chercher un client" en temps réel. Monolithe :
 * pas d'API REST versionnée, juste un endpoint web protégé par les
 * middlewares session + rôle, avec JSON en sortie.
 */
class ClientSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $clients = Client::query()
            ->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('code', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'code', 'name', 'phone', 'address', 'type']);

        // SPÉCIFICATIONS A.1 — le POS affiche le type (Acteur/Client) et
        // l'adresse pour confirmer l'identité avant de créer un doublon.
        return response()->json(['data' => $clients]);
    }

    /**
     * SPÉCIFICATIONS A.1 — CRÉATION À LA VOLÉE depuis la caisse (JSON).
     * Si le tiers n'existe pas, le caissier le crée sans quitter l'écran
     * de dépôt (nom obligatoire, téléphone recommandé). Le nouveau tiers
     * est enregistré comme ACTEUR : il deviendra Client automatiquement
     * à la validation de son premier dépôt.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
        ], [
            'name.required' => 'Le nom est obligatoire pour créer un tiers.',
        ]);

        // Anti-doublon : un téléphone existant renvoie le tiers existant.
        if (!empty($data['phone'])) {
            $existing = Client::where('phone', $data['phone'])->first();
            if ($existing) {
                return response()->json(['data' => $existing, 'existing' => true]);
            }
        }

        $client = Client::create($data + ['code' => 'TMP', 'type' => ClientType::Acteur->value]);
        $client->update(['code' => \App\Support\ClientCodeGenerator::for($client)]);

        return response()->json(['data' => $client, 'existing' => false], 201);
    }
}
