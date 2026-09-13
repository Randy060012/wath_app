<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * ÉTAPE 2 — CONTRÔLEUR ServiceController (catalogue des prestations)
 * Réservé à l'administrateur (middleware role:admin dans routes/web.php).
 */
class ServiceController extends Controller
{
    /**
     * Catalogue complet — prestations aplaties pour le DataTable
     * (une ligne = une prestation, avec sa catégorie pour le filtre).
     */
    public function index()
    {
        return view('services.index', [
            'categories' => Category::orderBy('sort_order')->get(),
            'services'   => Service::with('category')->orderBy('name')->get(),
        ]);
    }

    /** Enregistre une nouvelle prestation (appelé depuis la vue catalogue). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'   => ['required', 'integer', 'exists:categories,id'],
            'name'          => ['required', 'string', 'max:120'],
            'price'         => ['required', 'numeric', 'min:0'],
            'pricing_unit'  => ['required', 'in:piece,kg'],
            'default_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'description'   => ['nullable', 'string', 'max:500'],
        ], [
            'name.required'  => 'Le nom de la prestation est obligatoire.',
            'price.required' => 'Le prix est obligatoire.',
        ]);

        Service::create($data + ['default_hours' => $data['default_hours'] ?? 48]);

        return back()->with('success', 'Prestation ajoutée au catalogue.');
    }

    /** Mise à jour d'une prestation (prix, activation...). */
    public function update(Request $request, Service $service)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'price'         => ['required', 'numeric', 'min:0'],
            'pricing_unit'  => ['required', 'in:piece,kg'],
            'default_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'is_active'     => ['nullable', 'boolean'],
            'description'   => ['nullable', 'string', 'max:500'],
        ]);

        $service->update([
            ...$data,
            'default_hours' => $data['default_hours'] ?? $service->default_hours,
            'is_active'     => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Prestation mise à jour.');
    }

    /** Désactivation (jamais de suppression : historique conservé). */
    public function destroy(Service $service)
    {
        $service->update(['is_active' => false]);

        return back()->with('success', 'Prestation désactivée (l\'historique est conservé).');
    }
}
