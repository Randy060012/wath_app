<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

/**
 * ÉTAPE 2 — CONTRÔLEUR InventoryController (stock de fournitures)
 * CRUD simple + alertes stock bas visible sur le dashboard admin.
 */
class InventoryController extends Controller
{
    /** Liste du stock avec mise en évidence des alertes. */
    public function index()
    {
        return view('inventory.index', [
            'items' => Inventory::query()->orderBy('name')->get(),
        ]);
    }

    /** Enregistre une nouvelle fourniture. */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:120'],
            'unit'         => ['required', 'string', 'max:20'],
            'quantity'     => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['required', 'numeric', 'min:0'],
            'unit_cost'    => ['required', 'numeric', 'min:0'],
        ], [
            'name.required' => 'Le nom de la fourniture est obligatoire.',
        ]);

        Inventory::create($request->only(['name', 'unit', 'quantity', 'min_quantity', 'unit_cost']));

        return back()->with('success', 'Fourniture ajoutée au stock.');
    }

    /**
     * Mouvement de stock (+ réapprovisionnement / - consommation).
     */
    public function adjust(Request $request, Inventory $inventory)
    {
        $request->validate([
            'delta' => ['required', 'numeric', 'not_in:0'],
        ], [
            'delta.not_in' => 'Le mouvement ne peut pas être nul.',
        ]);

        $newQty = $inventory->quantity + (float) $request->input('delta');

        if ($newQty < 0) {
            return back()->with('error', 'Stock insuffisant pour ce mouvement.');
        }

        $inventory->update(['quantity' => $newQty]);

        return back()->with('success', 'Stock mis à jour : ' . $inventory->name . ' = ' . $newQty . ' ' . $inventory->unit);
    }

    /** Suppression d'une fourniture. */
    public function destroy(Inventory $inventory)
    {
        $inventory->delete();

        return back()->with('success', 'Fourniture supprimée.');
    }
}
