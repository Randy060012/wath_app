<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Services\AgencyService;
use App\Services\OrderService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * TESTS MULTI-TENANT + PAIEMENTS PARTIELS
 * -----------------------------------------------------------------
 *  1. Isolation : deux agences ne voient jamais leurs données mutuelles
 *     (clients, commandes, catalogue) ;
 *  2. Paiement partiel : un versement sans livraison, solde mis à jour,
 *     et interdiction de dépasser le solde dû ;
 *  3. Admin : création d'agence avec admin local + catalogue copié.
 */
class MultiTenantAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agencyA;
    private Agency $agencyB;
    private User $cashierA;
    private User $cashierB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class); // catalogue GLOBAL (modèle à dupliquer)

        // --- Agence A + B, avec leurs équipes ----------------------------
        $service = app(AgencyService::class);
        $this->agencyA = $service->create([
            'name' => 'Agence A',
            'admin' => ['name' => 'Admin A', 'email' => 'admin.a@test.local', 'password' => 'password'],
        ]);
        $this->agencyB = $service->create([
            'name' => 'Agence B',
            'copy_catalog' => false, // B démarre vide : vérifiera l'isolation du catalogue
        ]);

        $this->cashierA = User::create([
            'name' => 'Caisse A', 'email' => 'caisse.a@test.local',
            'password' => Hash::make('password'), 'agency_id' => $this->agencyA->id,
        ]);
        $this->cashierA->assignRole('caissier');

        $this->cashierB = User::create([
            'name' => 'Caisse B', 'email' => 'caisse.b@test.local',
            'password' => Hash::make('password'), 'agency_id' => $this->agencyB->id,
        ]);
        $this->cashierB->assignRole('caissier');
    }

    /** Crée un client + une commande dans une agence donnée (contexte session). */
    private function createOrderFor(User $cashier, Agency $agency, float $deposit = 0): Order
    {
        $this->actingAs($cashier);

        $client = Client::create([
            'code' => 'CL-' . $agency->code . '-' . uniqid(),
            'name' => 'Client ' . $agency->code,
            'phone' => '+22177000' . random_int(10000, 99999),
        ]);

        // Le catalogue de l'agence A est copié du global ; on prend la 1re prestation scopée.
        $service = \App\Models\Service::query()->first();

        return app(OrderService::class)->createOrder([
            'client_id' => $client->id,
            'items' => [['service_id' => $service->id, 'quantity' => 2]],
            'deposit_amount' => $deposit,
            'deposit_method' => 'cash',
        ], $cashier->id);
    }

    /* =================================================================
     | MULTI-TENANT — ISOLATION
     | ================================================================= */

    public function test_users_are_scoped_to_their_agency(): void
    {
        $this->actingAs($this->cashierA);
        $this->assertEquals($this->agencyA->id, \App\Support\AgencyContext::id());

        $this->actingAs($this->cashierB);
        $this->assertEquals($this->agencyB->id, \App\Support\AgencyContext::id());
    }

    public function test_clients_and_orders_are_invisible_between_agencies(): void
    {
        $orderA = $this->createOrderFor($this->cashierA, $this->agencyA, 500);

        // Contexte agence A : la commande est visible
        $this->actingAs($this->cashierA);
        $this->assertEquals(1, Order::query()->count());

        // Contexte agence B : AUCUNE donnée de A n'est visible
        $this->actingAs($this->cashierB);
        $this->assertEquals(0, Order::query()->count());
        $this->assertEquals(0, Client::query()->count());

        // Le caissier B ne peut pas ouvrir la fiche de la commande A
        $response = $this->actingAs($this->cashierB)
            ->get(route('orders.show', $orderA));
        $response->assertNotFound();
    }

    public function test_catalog_is_duplicated_and_scoped_per_agency(): void
    {
        // Agence A : catalogue copié du global (4 familles du CatalogSeeder)
        $this->actingAs($this->cashierA);
        $this->assertGreaterThan(0, \App\Models\Service::query()->count());

        // Agence B : aucun catalogue (copy_catalog => false)
        $this->actingAs($this->cashierB);
        $this->assertEquals(0, \App\Models\Service::query()->count());

        // Le super-admin peut initialiser le catalogue de B
        $superAdmin = User::create([
            'name' => 'Super', 'email' => 'super@test.local',
            'password' => Hash::make('password'), 'agency_id' => null,
        ]);
        $superAdmin->assignRole('admin');

        $this->actingAs($superAdmin)->get(route('admin.agencies.index')); // amorce la session
        $this->actingAs($superAdmin)
            ->post(route('admin.agencies.seedCatalog', $this->agencyB), ['_token' => csrf_token()])
            ->assertRedirect();

        $this->actingAs($this->cashierB);
        $this->assertGreaterThan(0, \App\Models\Service::query()->count());
    }

    public function test_agency_admin_cannot_access_group_screens(): void
    {
        // Admin LOCAL de l'agence A (rattaché) : il ne doit PAS voir les écrans groupe
        $localAdmin = User::create([
            'name' => 'Admin Local A', 'email' => 'admin.local.a@test.local',
            'password' => Hash::make('password'), 'agency_id' => $this->agencyA->id,
        ]);
        $localAdmin->assignRole('admin');

        $response = $this->actingAs($localAdmin)
            ->get(route('admin.agencies.index'));

        $response->assertRedirect(route('dashboard'));

        // Un simple caissier est bloqué encore plus tôt (middleware role)
        $this->actingAs($this->cashierA)
            ->get(route('admin.agencies.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_list_agencies_and_users(): void
    {
        $superAdmin = User::create([
            'name' => 'Super', 'email' => 'super2@test.local',
            'password' => Hash::make('password'), 'agency_id' => null,
        ]);
        $superAdmin->assignRole('admin');

        $this->actingAs($superAdmin)
            ->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('Agence A');

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Caisse A');
    }

    /* =================================================================
     | PAIEMENT PARTIEL (sans livraison)
     | ================================================================= */

    public function test_partial_payment_reduces_balance_without_delivery(): void
    {
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);
        $net = $order->net_amount;

        $this->actingAs($this->cashierA)->get(route('orders.index')); // amorce la session
        $this->actingAs($this->cashierA)
            ->post(route('orders.pay', $order), [
                '_token' => csrf_token(),
                'amount' => $net / 2,
                'method' => 'cash',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals($net / 2, $order->paid_amount);
        $this->assertEquals($net / 2, $order->balance_due);
        // PAS de livraison : statut inchangé (Reçu) et delivered_at null
        $this->assertNull($order->delivered_at);
        $this->assertEquals(\App\Enums\OrderStatus::Recu, $order->status);
    }

    public function test_multiple_partial_payments_until_settled(): void
    {
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);
        $net = $order->net_amount;

        $this->actingAs($this->cashierA)->get(route('orders.index'));
        $this->actingAs($this->cashierA)
            ->post(route('orders.pay', $order), ['_token' => csrf_token(), 'amount' => 100, 'method' => 'cash']);
        $this->actingAs($this->cashierA)
            ->post(route('orders.pay', $order), ['_token' => csrf_token(), 'amount' => $net, 'method' => 'mobile_money']);

        $order->refresh();
        // Le 2e versement a été plafonné au solde dû (jamais de trop-perçu)
        $this->assertEquals($net, $order->paid_amount);
        $this->assertEquals(0, $order->balance_due);
    }

    /* =================================================================
     | RAPPORTS COMPARATIFS + TICKET (identité agence)
     | ================================================================= */

    public function test_reports_show_agency_comparison_for_super_admin(): void
    {
        // Une commande encaissée dans l'agence A
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);
        $this->actingAs($this->cashierA)->get(route('orders.index'));
        $this->actingAs($this->cashierA)
            ->post(route('orders.pay', $order), ['_token' => csrf_token(), 'amount' => 1000, 'method' => 'cash']);

        $superAdmin = User::create([
            'name' => 'Super', 'email' => 'super-reports@test.local',
            'password' => Hash::make('password'), 'agency_id' => null,
        ]);
        $superAdmin->assignRole('admin');

        // Vue groupe (contexte session explicitement réinitialisé) :
        // le comparatif liste les deux agences.
        $this->actingAs($superAdmin)
            ->withSession(['agency_id' => null])
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Comparatif par agence')
            ->assertSee('Agence A')
            ->assertSee('Agence B');

        // Filtre sur une agence précise : son nom apparaît dans le titre du CA
        $this->withSession(['agency_id' => null])
            ->get(route('admin.reports.index', ['agency' => $this->agencyA->id]))
            ->assertOk()
            ->assertSee('Agence A');
    }

    public function test_agency_admin_has_no_comparison_and_no_filter(): void
    {
        // Un admin LOCAL voit les rapports de SON agence : pas de comparatif
        // multi-agences ni de sélecteur « toutes les agences ».
        $localAdmin = User::create([
            'name' => 'Admin Local Rapports', 'email' => 'admin.reports@test.local',
            'password' => Hash::make('password'), 'agency_id' => $this->agencyA->id,
        ]);
        $localAdmin->assignRole('admin');

        $this->actingAs($localAdmin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            // Pas de tableau comparatif ni de sélecteur pour un utilisateur d'agence
            ->assertDontSee('Comparatif par agence')
            ->assertDontSee('Toutes les agences')
            // Son agence est visible dans le titre du bloc CA
            ->assertSee('Agence A');
    }

    public function test_printed_ticket_carries_agency_header(): void
    {
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);

        $this->actingAs($this->cashierA)
            ->get(route('orders.print.ticket', $order))
            ->assertOk()
            ->assertSee($this->agencyA->name);
    }

    public function test_printed_labels_carry_agency_name(): void
    {
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);

        $this->actingAs($this->cashierA)
            ->get(route('orders.print.labels', $order))
            ->assertOk()
            ->assertSee($this->agencyA->name);
    }

    public function test_report_pdf_export_downloadable_and_scoped(): void
    {
        // Une vente encaissée en agence A
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);
        $this->actingAs($this->cashierA)->get(route('orders.index'));
        $this->actingAs($this->cashierA)
            ->post(route('orders.pay', $order), ['_token' => csrf_token(), 'amount' => 750, 'method' => 'cash']);

        // --- Export par l'admin LOCAL de l'agence A : PDF téléchargé ---
        $localAdmin = User::create([
            'name' => 'Admin PDF', 'email' => 'admin.pdf@test.local',
            'password' => Hash::make('password'), 'agency_id' => $this->agencyA->id,
        ]);
        $localAdmin->assignRole('admin');

        $response = $this->actingAs($localAdmin)->get(route('admin.reports.pdf'));

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            $response->headers->get('Content-Type') ?? ''
        );
        // Vrai PDF : signature %PDF- en tête de fichier
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());

        // --- Un caissier n'a PAS accès aux rapports (rôle) ---
        $this->actingAs($this->cashierA)
            ->get(route('admin.reports.pdf'))
            ->assertForbidden();
    }

    public function test_partial_payment_rejected_on_settled_order(): void
    {
        $order = $this->createOrderFor($this->cashierA, $this->agencyA, 0);
        $net = $order->net_amount;

        // Réglée intégralement via l'acompte à la création
        $order->payments()->create([
            'user_id' => $this->cashierA->id,
            'amount' => $net,
            'method' => 'cash',
        ]);
        $order->refresh();
        $this->assertEquals(0, $order->balance_due);

        $this->actingAs($this->cashierA)->get(route('orders.index'));
        $response = $this->actingAs($this->cashierA)
            ->post(route('orders.pay', $order), ['_token' => csrf_token(), 'amount' => 50, 'method' => 'cash']);

        $response->assertRedirect();
        $response->assertSessionHas('error'); // « déjà intégralement réglée »
        $this->assertEquals($net, $order->refresh()->paid_amount);
    }
}
