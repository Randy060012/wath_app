<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\ProformaStatus;
use App\Models\Client;
use App\Models\Proforma;
use App\Models\Service;
use App\Services\ProformaService;
use Illuminate\Http\Request;

/**
 * SPÉCIFICATIONS C — CONTRÔLEUR ProformaController (devis)
 * -----------------------------------------------------------------
 * index  : liste des proformas (DataTable) ;
 * create : éditeur (sélection prestations + ligne libre + remise) ;
 * store  : enregistrement (ProformaService, transaction) ;
 * show   : fiche du document + actions (envoyer, accepter, convertir) ;
 * print  : version A4 autonome → impression / PDF navigateur ;
 * send   : e-mail au tiers (ProformaMail) + statut ENVOYÉ ;
 * status : accepté / refusé ;
 * convert: transforme le devis accepté en dépôt réel (OrderService).
 * Réservé aux rôles caissier + admin (routes/web.php).
 */
class ProformaController extends Controller
{
    public function __construct(private readonly ProformaService $proformas)
    {
    }

    /** Liste des proformas. */
    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');

        $proformas = Proforma::query()
            ->with(['client', 'author'])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->get();

        return view('proformas.index', [
            'proformas' => $proformas,
            'statuses'  => ProformaStatus::cases(),
            'status'    => $status,
        ]);
    }

    /** Éditeur de devis. */
    public function create(Request $request)
    {
        // Pré-sélection d'un tiers (?client=ID depuis la fiche / la liste)
        $selected = $request->filled('client')
            ? Client::find($request->integer('client'))
            : null;

        return view('proformas.create', [
            'services'  => Service::where('is_active', true)->with('category:id,name')->orderBy('name')->get(),
            'clients'   => Client::orderBy('name')->get(['id', 'code', 'name', 'phone', 'type']),
            'selected'  => $selected,
            'currency'  => config('pressing.currency', 'FCFA'),
        ]);
    }

    /** Enregistre le devis (transaction + prix catalogue figés). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'       => ['required', 'integer', 'exists:clients,id'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'items.*.label'   => ['nullable', 'string', 'max:160'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.pricing_unit' => ['nullable', 'in:piece,kg,forfait'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'valid_days'      => ['nullable', 'integer', 'min:1', 'max:180'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ], [
            'items.required' => 'Ajoutez au moins une prestation au devis.',
            'client_id.required' => 'Sélectionnez un destinataire.',
        ]);

        $proforma = $this->proformas->create($data, $request->user()->id);

        return redirect()
            ->route('proformas.show', $proforma)
            ->with('success', 'Proforma ' . $proforma->number . ' créé.');
    }

    /** Fiche du document. */
    public function show(Proforma $proforma)
    {
        $proforma->load(['client', 'items', 'author', 'convertedOrder']);

        return view('proformas.show', ['proforma' => $proforma]);
    }

    /** Version imprimable A4 (HTML autonome → PDF navigateur). */
    public function print(Proforma $proforma)
    {
        $proforma->load(['client', 'items', 'author']);

        return response(
            view('prints.proforma', ['proforma' => $proforma])->render()
        )->header('Content-Type', 'text/html');
    }

    /** Envoi par e-mail (ou remise papier si pas d'e-mail). */
    public function send(Request $request, Proforma $proforma)
    {
        $emailed = $this->proformas->send($proforma, $request->user()->id);

        return back()->with(
            'success',
            $emailed
                ? 'Proforma ' . $proforma->number . ' envoyé à ' . $proforma->client->email . '.'
                : 'Proforma ' . $proforma->number . ' marqué envoyé (pas d\'e-mail sur la fiche : remise papier).'
        );
    }

    /** Acceptation / refus par le tiers. */
    public function status(Request $request, Proforma $proforma)
    {
        $data = $request->validate([
            'status' => ['required', 'in:accepte,refuse'],
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $this->proformas->setStatus(
            $proforma,
            ProformaStatus::from($data['status']),
            $data['reason'] ?? null,
        );

        return back()->with('success', 'Proforma ' . $proforma->number . ' : ' . ProformaStatus::from($data['status'])->label() . '.');
    }

    /** Conversion en dépôt réel (devis accepté → commande). */
    public function convert(Request $request, Proforma $proforma)
    {
        $data = $request->validate([
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_method' => ['nullable', 'in:cash,mobile_money,card'],
        ]);

        try {
            $order = $this->proformas->convertToOrder(
                $proforma,
                (float) ($data['deposit_amount'] ?? 0),
                PaymentMethod::from($data['deposit_method'] ?? 'cash'),
                $request->user()->id,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Proforma ' . $proforma->number . ' converti en dépôt ' . $order->ticket_no . '.');
    }
}
