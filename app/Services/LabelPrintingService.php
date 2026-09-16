<?php

namespace App\Services;

use App\Models\Order;

/**
 * ÉTAPE 3 — IMPRESSION DES TICKETS & ÉTIQUETTES
 * -----------------------------------------------------------------
 * Stratégie "impression navigateur" (zéro pilote à installer) :
 *  1. Ce service génère un HTML AUTONOME (styles inclus, fond blanc)
 *     représentant exactement le ticket 80 mm ou la planche d'étiquettes ;
 *  2. Il est renvoyé dans une vue dédiée plein écran (print) ;
 *  3. JavaScript déclenche window.print() à l'ouverture et l'agent
 *     choisit son imprimante (thermique 58/80 mm, A4...).
 * Avantage : fonctionne sur Windows, Linux, Android — aucun binaire
 * côté serveur. Alternative professionnelle : ESC/POS via raw socket
 * (voir fichier LISEZMOI.md, section impression réseau).
 */
class LabelPrintingService
{
    public function __construct(private readonly BarcodeService $barcodes)
    {
    }

    /**
     * HTML du ticket de caisse (largeur 80 mm par défaut).
     */
    public function ticketHtml(Order $order): string
    {
        // MULTI-TENANT : l'en-tête du ticket porte l'AGENCE ÉMETTRICE
        // (nom, adresse, téléphone) — chaque business imprime son identité.
        $order->load(['client', 'items.service', 'payments.user', 'cashier', 'agency']);

        $itemsHtml = $order->items
            ->map(fn ($it) => sprintf(
                '<tr><td>%s%s</td><td class="r">%d×</td><td class="r">%s</td></tr>',
                e($it->service->name),
                $it->description ? '<br><small>' . e($it->description) . '</small>' : '',
                $it->quantity,
                number_format($it->line_total, 2, ',', ' '),
            ))
            ->implode('');

        $paymentsHtml = $order->payments
            ->map(fn ($p) => sprintf(
                '<tr><td>%s (%s)</td><td class="r">%s</td></tr>',
                $p->created_at->format('d/m H:i'),
                e($p->method),
                number_format((float) $p->amount, 2, ',', ' '),
            ))
            ->implode('');

        $currency = config('pressing.currency', 'FCFA');

        return view('prints.ticket', [
            'order'        => $order,
            'agency'       => $order->agency,
            'itemsHtml'    => $itemsHtml,
            'paymentsHtml' => $paymentsHtml,
            'barcodeSvg'   => $this->barcodes->barcodeSvg($order->ticket_no, 2, 46),
            'currency'     => $currency,
        ])->render();
    }

    /**
     * HTML de la planche d'étiquettes articles (une par vêtement),
     * chacune portant code-barres + QR + emplacement.
     */
    public function labelsHtml(Order $order): string
    {
        // MULTI-TENANT : chaque étiquette porte le nom de l'AGENCE
        // (une planche imprimée doit identifier le business émetteur).
        $order->load(['client', 'items.service', 'agency']);

        $labels = $order->items->map(function ($item) {
            return [
                'item'       => $item,
                'barcodeSvg' => $this->barcodes->barcodeSvg($item->barcode, 2, 38),
                'qrDataUri'  => $this->barcodes->qrCodeDataUri($item->barcode),
            ];
        });

        return view('prints.labels', [
            'order'  => $order,
            'agency' => $order->agency,
            'labels' => $labels,
        ])->render();
    }
}
