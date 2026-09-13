<?php

namespace Tests\Feature;

use App\Enums\ClientType;
use App\Enums\ProformaStatus;
use App\Models\Client;
use App\Models\Proforma;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * SPÉCIFICATIONS FONCTIONNELLES A / B / C — TESTS
 * -----------------------------------------------------------------
 * A : formulaire de dépôt (recherche auto-complétée + création à la volée) ;
 * B : distinction Acteur / Client (statut dynamique, promotion auto) ;
 * C : gestion documentaire (proforma → envoi → acceptation → conversion).
 */
class CrmAndProformaTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $workshopAgent;
    private \App\Models\Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->cashier = User::create([
            'name' => 'Caisse Test', 'email' => 'caisse@test.local', 'password' => Hash::make('password'),
        ]);
        $this->cashier->assignRole('caissier');

        $this->workshopAgent = User::create([
            'name' => 'Atelier Test', 'email' => 'atelier@test.local', 'password' => Hash::make('password'),
        ]);
        $this->workshopAgent->assignRole('atelier');

        $this->service = \App\Models\Service::first();
    }

    /* =================================================================
     |  SPÉCIFICATIONS A — FORMULAIRE DE DÉPÔT (CAISSE POS)
     |  =============================================================== */

    /** A.1 — La recherche JSON renvoie le type + l'adresse (aide à la décision). */
    public function test_client_search_returns_type_and_address(): void
    {
        Client::create(['code' => 'CL-ADR01', 'name' => 'Awa Diop', 'phone' => '+221770000009',
            'address' => 'Fann Hock, villa 12']);

        $this->actingAs($this->cashier)
            ->getJson(route('clients.search', ['q' => 'Awa']))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'phone', 'address', 'type']]]);
    }

    /** A.1 — Création à la volée : le nouveau tiers est un ACTEUR. */
    public function test_quick_store_creates_acteur_on_the_fly(): void
    {
        $this->get(route('login')); // amorce la session (CSRF)

        $response = $this->actingAs($this->cashier)
            ->postJson(route('clients.quickStore'), [
                'name'  => 'Moussa Fall',
                'phone' => '+221770000010',
            ], ['X-CSRF-TOKEN' => csrf_token()]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'acteur')
            ->assertJsonPath('existing', false);

        $client = Client::where('phone', '+221770000010')->first();
        $this->assertNotNull($client);
        $this->assertSame(ClientType::Acteur, $client->type);
        $this->assertMatchesRegularExpression('/^CL-/', $client->code); // code généré
    }

    /** A.1 — Anti-doublon : un téléphone connu renvoie le tiers EXISTANT. */
    public function test_quick_store_returns_existing_client_on_duplicate_phone(): void
    {
        $existing = Client::create(['code' => 'CL-DUP01', 'name' => 'Fatou Sarr', 'phone' => '+221770000011']);

        $this->get(route('login'));
        $response = $this->actingAs($this->cashier)
            ->postJson(route('clients.quickStore'), [
                'name'  => 'Fatou SARR (faute de frappe)',
                'phone' => '+221770000011',
            ], ['X-CSRF-TOKEN' => csrf_token()]);

        $response->assertOk()
            ->assertJsonPath('existing', true)
            ->assertJsonPath('data.id', $existing->id);

        // Aucun doublon créé
        $this->assertSame(1, Client::where('phone', '+221770000011')->count());
    }

    /* =================================================================
     |  SPÉCIFICATIONS B — DISTINCTION ACTEUR / CLIENT
     |  =============================================================== */

    /** B — Promotion automatique : la validation d'un dépôt transforme l'Acteur en Client. */
    public function test_first_validated_order_promotes_acteur_to_client(): void
    {
        $acteur = Client::create(['code' => 'CL-ACT01', 'name' => 'Prospect Test', 'type' => ClientType::Acteur->value]);
        $this->assertSame(ClientType::Acteur, $acteur->type);

        app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $acteur->id,
            'items'     => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        $acteur->refresh();
        $this->assertSame(ClientType::Client, $acteur->type); // promotion automatique
    }

    /** B — Un Client reste un Client (idempotence de la promotion). */
    public function test_promotion_is_idempotent(): void
    {
        $client = Client::create(['code' => 'CL-CLI01', 'name' => 'Client Fidèle', 'type' => ClientType::Client->value]);

        $this->assertFalse($client->promoteToClientIfActeur());
        $this->assertSame(ClientType::Client, $client->fresh()->type);
    }

    /** B — Inscription directe depuis la liste : le tiers part en statut ACTEUR. */
    public function test_direct_registration_creates_acteur(): void
    {
        $this->get(route('login'));

        $response = $this->actingAs($this->cashier)
            ->post(route('clients.store'), [
                '_token' => csrf_token(),
                'name'   => 'Nouveau Prospect',
                'phone'  => '+221770000012',
            ]);

        $response->assertRedirect();
        $client = Client::where('phone', '+221770000012')->first();
        $this->assertNotNull($client);
        $this->assertSame(ClientType::Acteur, $client->type);
    }

    /** B — Le filtre CRM ?type=acteur isole les prospects. */
    public function test_client_list_filters_by_type(): void
    {
        Client::create(['code' => 'CL-ACT02', 'name' => 'Acteur Seul', 'type' => ClientType::Acteur->value]);

        $this->actingAs($this->cashier)->get(route('clients.index', ['type' => 'acteur']))
            ->assertOk()
            ->assertSee('Acteur Seul')
            ->assertDontSee('Client Test'); // le client du setUp est de type "client"
    }

    /* =================================================================
     |  SPÉCIFICATIONS C — GESTION DOCUMENTAIRE (PROFORMA)
     |  =============================================================== */

    /** C — Création : les prix VIENNENT DU CATALOGUE, jamais du navigateur. */
    public function test_proforma_prices_come_from_catalog_not_browser(): void
    {
        $client = Client::create(['code' => 'CL-PRF01', 'name' => 'Client Proforma']);

        $this->get(route('login'));
        $response = $this->actingAs($this->cashier)
            ->post(route('proformas.store'), [
                '_token'        => csrf_token(),
                'client_id'     => $client->id,
                'items'         => [
                    ['service_id' => $this->service->id, 'quantity' => 2],
                ],
                // Tentative de manipulation du prix depuis le navigateur
                'items'         => [
                    ['service_id' => $this->service->id, 'quantity' => 2, 'unit_price' => 1],
                ],
            ]);

        $response->assertRedirect();
        $proforma = Proforma::latest('id')->first();

        $this->assertNotNull($proforma);
        // 2 × prix CATALOGUE (pas 2 × 1 FCFA soumis par le navigateur)
        $this->assertEquals(2 * (float) $this->service->price, $proforma->total_amount);
        $this->assertSame(ProformaStatus::Brouillon, $proforma->status);
        $this->assertMatchesRegularExpression('/^P-\d{4}-\d{6}$/', $proforma->number);
    }

    /** C — Ligne libre : libellé obligatoire, prix validé côté serveur. */
    public function test_proforma_free_line_is_validated(): void
    {
        $client = Client::create(['code' => 'CL-PRF02', 'name' => 'Client Devis Libre']);

        $this->get(route('login'));
        $this->actingAs($this->cashier)
            ->post(route('proformas.store'), [
                '_token'    => csrf_token(),
                'client_id' => $client->id,
                'items'     => [
                    ['label' => 'Customisation sac cuir', 'quantity' => 1, 'unit_price' => 5000, 'pricing_unit' => 'piece'],
                ],
            ]);

        $proforma = Proforma::latest('id')->first();
        $this->assertEquals(5000, $proforma->total_amount);
        $this->assertNull($proforma->items->first()->service_id);
        $this->assertSame('Customisation sac cuir', $proforma->items->first()->label);
    }

    /** C — Workflow complet : brouillon → envoyé → accepté → converti en dépôt. */
    public function test_proforma_full_lifecycle_to_order(): void
    {
        $client = Client::create(['code' => 'CL-PRF03', 'name' => 'Client Conversion', 'type' => ClientType::Acteur->value]);

        // --- 1) CRÉATION du devis (2 × prestation catalogue) ---
        $proforma = app(\App\Services\ProformaService::class)->create([
            'client_id' => $client->id,
            'items'     => [
                ['service_id' => $this->service->id, 'quantity' => 2],
            ],
        ], $this->cashier->id);

        $this->assertSame(ProformaStatus::Brouillon, $proforma->status);

        // --- 2) ENVOI (sans e-mail sur la fiche : remise papier) ---
        $this->get(route('login'));
        $this->actingAs($this->cashier)
            ->post(route('proformas.send', $proforma), ['_token' => csrf_token()])
            ->assertRedirect();
        $proforma->refresh();
        $this->assertSame(ProformaStatus::Envoye, $proforma->status);

        // --- 3) ACCEPTATION par le tiers ---
        $this->post(route('proformas.status', $proforma), ['_token' => csrf_token(), 'status' => 'accepte'])
            ->assertRedirect();
        $proforma->refresh();
        $this->assertSame(ProformaStatus::Accepte, $proforma->status);

        // --- 4) CONVERSION en dépôt réel (avec acompte) ---
        $this->post(route('proformas.convert', $proforma), [
            '_token'         => csrf_token(),
            'deposit_amount' => 1000,
            'deposit_method' => 'cash',
        ])->assertRedirect();

        $proforma->refresh();
        $order = $proforma->convertedOrder;
        $this->assertNotNull($order);
        $this->assertSame(ProformaStatus::Accepte, $proforma->status);
        $this->assertSame('Suite au proforma ' . $proforma->number, $order->notes);

        // 2 × prestation → 2 articles suivis, acompte enregistré
        $this->assertCount(2, $order->items);
        $this->assertEquals(1000, $order->paid_amount);

        // SPÉCIFICATIONS B : la conversion a PROMU l'Acteur en Client
        $this->assertSame(ClientType::Client, $client->fresh()->type);
    }

    /** C — Garde-fous : pas de double conversion, ni de conversion hors workflow. */
    public function test_proforma_conversion_guards(): void
    {
        $client = Client::create(['code' => 'CL-PRF04', 'name' => 'Client Garde']);
        $proforma = app(\App\Services\ProformaService::class)->create([
            'client_id' => $client->id,
            'items'     => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        $this->get(route('login'));

        // Conversion d'un brouillon → refusée (il faut passer par accepté)
        $this->actingAs($this->cashier)
            ->post(route('proformas.convert', $proforma), ['_token' => csrf_token()])
            ->assertRedirect();
        $this->assertNull($proforma->fresh()->converted_order_id);
        $this->assertSame(ProformaStatus::Brouillon, $proforma->fresh()->status);

        // Conversion d'un proforma DÉJÀ converti → refusée
        $proforma->markAccepted();
        $order = app(\App\Services\ProformaService::class)->convertToOrder($proforma, 0, \App\Enums\PaymentMethod::Cash, $this->cashier->id);
        $this->actingAs($this->cashier)
            ->post(route('proformas.convert', $proforma), ['_token' => csrf_token()])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertSame($order->id, $proforma->fresh()->converted_order_id);
    }

    /** C — Un agent d'atelier n'accède pas aux documents commerciaux. */
    public function test_workshop_agent_cannot_access_proformas(): void
    {
        $this->actingAs($this->workshopAgent)
            ->get(route('proformas.index'))
            ->assertForbidden();

        $this->actingAs($this->workshopAgent)
            ->get(route('proformas.create'))
            ->assertForbidden();
    }

    /** C — Le tiers sans e-mail : l'envoi passe ENVOYÉ sans plantage (remise papier). */
    public function test_send_without_email_marks_sent(): void
    {
        $client = Client::create(['code' => 'CL-PRF05', 'name' => 'Client Papier']);
        $proforma = app(\App\Services\ProformaService::class)->create([
            'client_id' => $client->id,
            'items'     => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        app(\App\Services\ProformaService::class)->send($proforma, $this->cashier->id);

        $this->assertSame(ProformaStatus::Envoye, $proforma->fresh()->status);
    }

    /** C — Un devis envoyé dont la validité est dépassée s'affiche EXPIRÉ. */
    public function test_expired_proforma_displays_as_expired(): void
    {
        $client = Client::create(['code' => 'CL-PRF06', 'name' => 'Client Expiré']);
        $proforma = app(\App\Services\ProformaService::class)->create([
            'client_id' => $client->id,
            'items'     => [['service_id' => $this->service->id, 'quantity' => 1]],
            'valid_days' => 1,
        ], $this->cashier->id);

        app(\App\Services\ProformaService::class)->send($proforma, $this->cashier->id);

        // Simule l'expiration : la validité passe hier
        $proforma->update(['valid_until' => now()->subDay()->toDateString()]);

        $this->assertSame(ProformaStatus::Expire, $proforma->fresh()->display_status);
        // Le statut STOCKÉ reste "envoye" (dérivation à la lecture, pas de réécriture)
        $this->assertSame(ProformaStatus::Envoye, $proforma->fresh()->status);
    }
}
