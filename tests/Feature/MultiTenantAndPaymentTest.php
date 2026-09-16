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

    /* =================================================================
     | INSCRIPTION SELF-SERVICE (compte + première agence)
     | ================================================================= */

    public function test_self_registration_creates_owner_and_agency(): void
    {
        // Le catalogue global existe (setUp) ; aucune agence au départ
        $agenciesBefore = Agency::count();

        $this->get(route('register.show')); // amorce la session (CSRF)

        $response = $this->post(route('register.store'), [
            'name'                  => 'Nouveau Propriétaire',
            'email'                 => 'nouveau@test.local',
            'password'              => 'secret12',
            'password_confirmation' => 'secret12',
            'agency_name'           => 'Pressing du Progrès',
            'agency_phone'          => '+221 77 000 00 00',
            'terms'                 => '1',
            '_token'                => csrf_token(),
        ]);

        $response->assertRedirect(route('dashboard'));

        // L'agence existe, avec le catalogue copié
        $agency = Agency::query()->where('name', 'Pressing du Progrès')->first();
        $this->assertNotNull($agency);
        $this->assertEquals($agenciesBefore + 1, Agency::count());
        $this->assertGreaterThan(0, $agency->services()->count());

        // Le compte : admin LOCAL rattaché à SON agence (jamais super-admin)
        $user = User::query()->where('email', 'nouveau@test.local')->first();
        $this->assertNotNull($user);
        $this->assertEquals($agency->id, $user->agency_id);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->isSuperAdmin());

        // L'utilisateur est CONNECTÉ après l'inscription
        $this->assertTrue(auth()->check());
    }

    public function test_registration_rejects_duplicate_email_and_agency_name(): void
    {
        // Doublon d'e-mail utilisateur
        $this->get(route('register.show')); // amorce la session (CSRF)
        $response = $this->from(route('register.show'))->post(route('register.store'), [
            'name'                  => 'X',
            'email'                 => 'caisse.a@test.local', // déjà pris (setUp)
            'password'              => 'secret12',
            'password_confirmation' => 'secret12',
            'agency_name'           => 'Agence Totalement Nouvelle',
            'terms'                 => '1',
            '_token'                => csrf_token(),
        ]);
        $response->assertRedirect(route('register.show'));
        $response->assertSessionHasErrors('email');

        // Doublon de nom d'agence
        $response = $this->from(route('register.show'))->post(route('register.store'), [
            'name'                  => 'X',
            'email'                 => 'autre@test.local',
            'password'              => 'secret12',
            'password_confirmation' => 'secret12',
            'agency_name'           => 'Agence A', // existe déjà (setUp)
            'terms'                 => '1',
            '_token'                => csrf_token(),
        ]);
        $response->assertSessionHasErrors('agency_name');

        // Rien n'a été créé (atomicité)
        $this->assertEquals(0, User::query()->where('email', 'autre@test.local')->count());
        $this->assertNull(Agency::query()->where('name', 'Agence Totalement Nouvelle')->first());
    }

    public function test_registration_requires_terms_and_strong_enough_password(): void
    {
        $this->get(route('register.show')); // amorce la session (CSRF)

        $response = $this->post(route('register.store'), [
            'name'                  => 'Y',
            'email'                 => 'y@test.local',
            'password'              => 'abc123', // lettre+chiffre OK
            'password_confirmation' => 'abc123',
            'agency_name'           => 'Agence Y',
            // 'terms' absent => refus
            '_token'                => csrf_token(),
        ]);
        $response->assertSessionHasErrors('terms');

        $response = $this->post(route('register.store'), [
            'name'                  => 'Y',
            'email'                 => 'y2@test.local',
            'password'              => '123456', // pas de lettre => refus
            'password_confirmation' => '123456',
            'agency_name'           => 'Agence Y2',
            'terms'                 => '1',
            '_token'                => csrf_token(),
        ]);
        $response->assertSessionHasErrors('password');
    }

    /* ================================================================
     | SELF-SERVICE — LE PROPRIÉTAIRE GÈRE SON GROUPE D'AGENCES
     | ================================================================ */

    public function test_registered_owner_can_add_agencies_and_manage_their_users(): void
    {
        // Inscription → le client devient PROPRIÉTAIRE de son groupe
        $this->get(route('register.show'));
        $this->post(route('register.store'), [
            'name'                  => 'Propriétaire Alpha',
            'email'                 => 'alpha@test.local',
            'password'              => 'secret12',
            'password_confirmation' => 'secret12',
            'agency_name'           => 'Alpha Pressing',
            'terms'                 => '1',
            '_token'                => csrf_token(),
        ])->assertRedirect(route('dashboard'));

        $owner   = User::query()->where('email', 'alpha@test.local')->first();
        $agency1 = Agency::query()->where('name', 'Alpha Pressing')->first();

        $this->assertTrue($owner->managesGroup());
        $this->assertEquals($owner->id, $agency1->refresh()->owner_id);

        // Écran Agences accessible, limité à SES agences (pas « Agence A » du setUp)
        $this->actingAs($owner)
            ->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('Alpha Pressing')
            ->assertDontSee('Agence A');

        // Il ajoute une DEUXIÈME agence — il en devient automatiquement propriétaire
        $this->post(route('admin.agencies.store'), [
            '_token'       => csrf_token(),
            'name'         => 'Alpha Succursale',
            'copy_catalog' => '1',
        ])->assertRedirect();

        $agency2 = Agency::query()->where('name', 'Alpha Succursale')->first();
        $this->assertNotNull($agency2);
        $this->assertEquals($owner->id, $agency2->owner_id);
        $this->assertGreaterThan(0, $agency2->services()->withAgency()->count()); // catalogue copié

        // Il bascule son contexte de travail vers sa 2e agence
        $this->post(route('admin.agency.switch'), [
            '_token'    => csrf_token(),
            'agency_id' => $agency2->id,
        ])->assertRedirect();
        $this->assertEquals($agency2->id, \App\Support\AgencyContext::id());

        // Écran Utilisateurs : il crée un caissier dans sa 2e agence
        $this->get(route('admin.users.index'))->assertOk();
        $this->post(route('admin.users.store'), [
            '_token'    => csrf_token(),
            'name'      => 'Caissier Alpha 2',
            'email'     => 'alpha2.caisse@test.local',
            'password'  => 'password',
            'role'      => 'caissier',
            'agency_id' => $agency2->id,
        ])->assertRedirect();

        $this->assertTrue(
            User::query()->where('email', 'alpha2.caisse@test.local')->where('agency_id', $agency2->id)->exists()
        );
    }

    public function test_owner_cannot_reach_other_groups_agencies_or_users(): void
    {
        // Propriétaire A (par inscription)
        $this->get(route('register.show'));
        $this->post(route('register.store'), [
            'name'                  => 'Owner A',
            'email'                 => 'owner.a@test.local',
            'password'              => 'secret12',
            'password_confirmation' => 'secret12',
            'agency_name'           => 'Owner A Pressing',
            'terms'                 => '1',
            '_token'                => csrf_token(),
        ]);
        $ownerA = User::query()->where('email', 'owner.a@test.local')->first();

        // Propriétaire B (arrangé directement : autre groupe)
        $service = app(AgencyService::class);
        $agencyB = $service->create(['name' => 'Beta Pressing', 'copy_catalog' => false]);
        $ownerB  = User::create([
            'name' => 'Owner B', 'email' => 'owner.b@test.local',
            'password' => Hash::make('password'), 'agency_id' => $agencyB->id,
        ]);
        $ownerB->assignRole('admin');
        $agencyB->update(['owner_id' => $ownerB->id]);

        $this->actingAs($ownerA);

        // La liste n'affiche PAS les agences de B
        $this->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertDontSee('Beta Pressing');

        // Agence de B : actions interdites (403)
        $this->post(route('admin.agencies.toggle', $agencyB), ['_token' => csrf_token()])
            ->assertForbidden();
        $this->post(route('admin.agencies.seedCatalog', $agencyB), ['_token' => csrf_token()])
            ->assertForbidden();

        // Bascule de contexte vers l'agence de B : refusée
        $this->post(route('admin.agency.switch'), [
            '_token'    => csrf_token(),
            'agency_id' => $agencyB->id,
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertNotEquals($agencyB->id, \App\Support\AgencyContext::id());

        // Utilisateurs : il ne peut pas rattacher quelqu'un à l'agence de B…
        $this->post(route('admin.users.store'), [
            '_token'    => csrf_token(),
            'name'      => 'X',
            'email'     => 'x.intrus@test.local',
            'password'  => 'password',
            'role'      => 'caissier',
            'agency_id' => $agencyB->id,
        ])->assertRedirect()->assertSessionHas('error');
        $this->assertNull(User::query()->where('email', 'x.intrus@test.local')->first());

        // …ni modifier, ni désactiver l'utilisateur de B
        $this->patch(route('admin.users.update', $ownerB), [
            '_token' => csrf_token(), 'role' => 'caissier', 'agency_id' => $agencyB->id,
        ])->assertForbidden();
        $this->post(route('admin.users.toggle', $ownerB), ['_token' => csrf_token()])
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
