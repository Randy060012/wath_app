<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;

/**
 * ÉTAPE 2 — CONTRÔLEUR ClientController (CRUD clients)
 * -----------------------------------------------------------------
 * Méthodes CRUD standard Laravel : index, create, store, edit, update,
 * destroy + show (fiche avec historique des dépôts).
 * Tous les contrôleurs restent MINCES : ils valident, délèguent aux
 * services métier, redirigent. Aucune logique d'argent ici.
 */
class ClientController extends Controller
{
    /**
     * Liste des clients — SANS pagination serveur : le DataTable côté
     * navigateur (datatable.js) gère recherche, tri et pagination.
     * SPÉCIFICATIONS B — un filtre ?type=acteur|client isole les
     * prospects (écran CRM) ; sans filtre, tout le monde.
     */
    public function index(Request $request)
    {
        $type = $request->query('type');

        $clients = Client::query()
            ->withCount('orders')
            ->when(in_array($type, ['acteur', 'client'], true),
                fn ($q) => $q->where('type', $type))
            ->orderBy('name')
            ->get();

        return view('clients.index', [
            'clients'    => $clients,
            'typeFilter' => in_array($type, ['acteur', 'client'], true) ? $type : null,
            'counts'     => [
                'all'    => Client::count(),
                'acteur' => Client::where('type', ClientType::Acteur->value)->count(),
                'client' => Client::where('type', ClientType::Client->value)->count(),
            ],
        ]);
    }

    /** Formulaire de création. */
    public function create()
    {
        return view('clients.form', ['client' => new Client()]);
    }

    /**
     * Enregistre le nouveau client.
     * SPÉCIFICATIONS B — inscription DIRECTE (sans dépôt) : le tiers
     * part en statut ACTEUR. La promotion en Client est automatique
     * dès la validation de son premier dépôt (OrderService).
     */
    public function store(StoreClientRequest $request)
    {
        // Création puis attribution du code lisible dérivé de l'ID
        $client = Client::create(
            $request->validated()
            + ['code' => 'TMP', 'type' => ClientType::Acteur->value]
        );
        $client->update(['code' => \App\Support\ClientCodeGenerator::for($client)]);

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Client ' . $client->code . ' créé avec succès.');
    }

    /**
     * Fiche client + historique des dépôts.
     * SPÉCIFICATIONS C — les devis (proformas) du tiers sont aussi
     * listés sur sa fiche, avec conversion en dépôt en un clic.
     */
    public function show(Client $client)
    {
        return view('clients.show', [
            'client'    => $client->loadCount('orders'),
            'orders'    => $client->orders()->withCount('items')->latest()->get(),
            'proformas' => $client->proformas()->with('items')->latest()->get(),
        ]);
    }

    /** Formulaire d'édition. */
    public function edit(Client $client)
    {
        return view('clients.form', compact('client'));
    }

    /** Mise à jour. */
    public function update(StoreClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.show', $client)
            ->with('success', 'Fiche client mise à jour.');
    }

    /** Suppression (possible seulement si aucun dépôt). */
    public function destroy(Client $client)
    {
        if ($client->orders()->exists()) {
            return back()->with('error', 'Impossible de supprimer : ce client a un historique de dépôts.');
        }

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client supprimé.');
    }
}
