<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ÉTAPE 3 — TESTS FONCTIONNELS du parcours métier complet :
 * création de dépôt (caisse) → suivi atelier (scans) → retrait (livraison).
 * Ces tests valident la logique métier, pas l'interface.
 */
class PressingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $workshopAgent;
    private Client $client;
    private \App\Models\Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Rôles + catalogue
        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class);

        // Utilisateurs de test (rôles Spatie)
        $this->cashier = User::create([
            'name' => 'Caisse Test', 'email' => 'caisse@test.local', 'password' => Hash::make('password'),
        ]);
        $this->cashier->assignRole('caissier');

        $this->workshopAgent = User::create([
            'name' => 'Atelier Test', 'email' => 'atelier@test.local', 'password' => Hash::make('password'),
        ]);
        $this->workshopAgent->assignRole('atelier');

        $this->client = Client::create([
            'code' => 'CL-TEST01', 'name' => 'Client Test', 'phone' => '+221770000001',
        ]);

        $this->service = \App\Models\Service::first();
    }

    /** Parcours nominal : dépôt → lavage → repassage → prêt → retrait. */
    public function test_full_workflow_from_deposit_to_delivery(): void
    {
        $this->actingAs($this->cashier);

        // 1) CRÉATION du dépôt via le service (comme OrderController@store)
        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 3, 'description' => 'Chemises bleues'],
            ],
            'deposit_amount' => 500,
            'deposit_method' => 'cash',
        ], $this->cashier->id);

        // Ticket unique au bon format
        $this->assertMatchesRegularExpression('/^T-\d{4}-\d{6}$/', $order->ticket_no);

        // 3 chemises → 3 articles → 3 codes-barres uniques
        $this->assertCount(3, $order->items);
        $this->assertCount(3, $order->items->pluck('barcode')->unique());

        // Acompte enregistré + solde correct
        $this->assertEquals(500, $order->paid_amount);
        $this->assertEquals($order->net_amount - 500, $order->balance_due);

        // Statut initial : tous "Reçu" et commande dérivée
        $this->assertEquals(OrderStatus::Recu, $order->status);

        // 2) SUIVI ATELIER : chaque article avance Reçu → En cours → Repassé → Prêt
        foreach ($order->items as $item) {
            $item->markStatus(OrderStatus::EnCours);
            $item->markStatus(OrderStatus::Repasse);
            $item->markStatus($item->status === OrderStatus::Repasse ? OrderStatus::Pret : OrderStatus::Pret);
        }

        $order->refresh();
        $this->assertEquals(OrderStatus::Pret, $order->status); // dérivé automatiquement

        // 3) NOTIFICATION : le passage PRÊT déclenche le listener (queue sync
        // en test). Un événement est diffusé à CHAQUE transition syncStatus
        // vers Prêt — les listeners qui envoient SMS/mail doivent être
        // idempotents (dédupliqués) en production si on veut 1 seul envoi.
        $this->assertGreaterThanOrEqual(1, $this->client->notifications()->count());

        // 4) RETRAIT : encaissement du solde + livraison (POST via HTTP
        // avec activation de session pour passer la vérification CSRF)
        $response = $this->actingAs($this->cashier)
            ->withSession([]) // démarre une session pour le token CSRF
            ->get(route('orders.index')); // amorce la session + token
        $response = $this->actingAs($this->cashier)->post(route('orders.settle', $order), [
            'amount' => $order->balance_due,
            'method' => 'cash',
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect();
        $order->refresh();
        $this->assertEquals(OrderStatus::Livre, $order->status);
        $this->assertEquals($order->net_amount, $order->paid_amount); // intégralement réglée
        $this->assertNotNull($order->delivered_at);
    }

    /** Le statut global dérive : une commande n'est "Prêt" que si TOUS ses articles le sont. */
    public function test_order_status_is_derived_from_items(): void
    {
        $this->actingAs($this->cashier);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [
                ['service_id' => $this->service->id, 'quantity' => 2],
            ],
        ], $this->cashier->id);

        $items = $order->items()->get();

        // Un article prêt, l'autre encore "Reçu" → la commande reste "Reçu"
        // (dérivée par le statut le MOINS avancé : elle ne peut pas être
        // plus avancée que son article le moins avancé).
        $items[0]->markStatus(OrderStatus::EnCours);
        $items[0]->markStatus(OrderStatus::Repasse);
        $items[0]->markStatus(OrderStatus::Pret);
        $order->refresh();

        $this->assertEquals(OrderStatus::Recu, $order->status);

        // Dès que le second est prêt → commande "Prêt" + notification
        $items[1]->markStatus(OrderStatus::EnCours);
        $items[1]->markStatus(OrderStatus::Repasse);
        $items[1]->markStatus(OrderStatus::Pret);
        $order->refresh();

        $this->assertEquals(OrderStatus::Pret, $order->status);
    }

    /** Machine à états : les transitions interdites sont rejetées. */
    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->actingAs($this->cashier);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        $item = $order->items->first();

        // Reçu → Prêt est interdit (il faut passer par En cours / Repassé)
        $this->expectException(\InvalidArgumentException::class);
        $item->markStatus(OrderStatus::Pret);
    }

    /** Rôles : un agent d'atelier ne peut pas créer de dépôt (middleware role). */
    public function test_workshop_agent_cannot_create_orders(): void
    {
        $response = $this->actingAs($this->workshopAgent)
            ->get(route('orders.create'));

        $response->assertForbidden();
    }

    /** Les listes (dépôts, clients) ne sont plus paginées côté serveur : DataTable JS. */
    public function test_order_and_client_lists_are_not_server_paginated(): void
    {
        $this->actingAs($this->cashier);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        // Toutes les lignes sont rendues (le DataTable JS gère la pagination)
        $this->get(route('orders.index'))
            ->assertOk()
            ->assertSee($order->ticket_no);

        $this->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Client Test');
    }

    /** Action express "Marquer prêt" : la machine à états est respectée. */
    public function test_mark_ready_endpoint_walks_the_state_machine(): void
    {
        $this->actingAs($this->cashier);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id, 'quantity' => 2]],
        ], $this->cashier->id);

        // Amorce la session (token CSRF) puis POST du menu contextuel
        $this->actingAs($this->cashier)->get(route('orders.index'));
        $response = $this->actingAs($this->cashier)->post(route('orders.markReady', $order), [
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect();

        $order->refresh();
        $order->load('items');

        // Chaque article a suivi le chemin complet Reçu → En cours → Repassé → Prêt
        $this->assertEquals(OrderStatus::Pret, $order->status);
        $this->assertTrue($order->items->every(fn ($i) => $i->status === OrderStatus::Pret));

        // Audit : chaque article a bien 4 entrées de journal (création + 3 transitions)
        $order->items->each(fn ($i) => $this->assertCount(4, $i->statusLogs()->get()));

        // Le passage PRÊT a déclenché la notification client
        $this->assertGreaterThanOrEqual(1, $this->client->notifications()->count());

        // IDEMPOTENCE : un 2e appel ne duplique ni transition ni notification
        $notifsBefore = $this->client->notifications()->count();
        $this->post(route('orders.markReady', $order), ['_token' => csrf_token()]);
        $this->assertEquals($notifsBefore, $this->client->notifications()->count());
    }

    /** "Marquer prêt" est refusé sur une commande déjà livrée. */
    public function test_mark_ready_rejects_delivered_order(): void
    {
        $this->actingAs($this->cashier);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        $this->actingAs($this->cashier)->get(route('orders.index'));

        // On livre la commande (transitions atelier puis retrait)
        $order->items->each(function ($i) {
            $i->markStatus(OrderStatus::EnCours);
            $i->markStatus(OrderStatus::Repasse);
            $i->markStatus(OrderStatus::Pret);
        });
        $this->post(route('orders.settle', $order), [
            '_token' => csrf_token(), 'amount' => $order->balance_due, 'method' => 'cash',
        ]);
        $order->refresh();
        $this->assertEquals(OrderStatus::Livre, $order->status);

        // Le POST est rejeté avec le flash d'erreur, statut inchangé
        $response = $this->post(route('orders.markReady', $order), ['_token' => csrf_token()]);
        $response->assertRedirect();
        $response->assertSessionHas('error');

        $order->refresh();
        $this->assertEquals(OrderStatus::Livre, $order->status);
    }

    /** L'agent d'atelier n'a pas accès à l'action express de caisse. */
    public function test_workshop_agent_cannot_mark_ready(): void
    {
        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $this->client->id,
            'items' => [['service_id' => $this->service->id, 'quantity' => 1]],
        ], $this->cashier->id);

        $this->actingAs($this->workshopAgent)
            ->get(route('orders.index'));

        $this->actingAs($this->workshopAgent)
            ->post(route('orders.markReady', $order), ['_token' => csrf_token()])
            ->assertForbidden();
    }

    /** Anti brute-force : 6 tentatives → message de blocage avec délai. */
    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        // Amorce la session (token CSRF) avant les POST
        $this->get(route('login'));

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), [
                'email' => 'admin@pressing.test', 'password' => 'mauvais-mot-de-passe',
                '_token' => csrf_token(),
            ]);
        }

        // La 6e tentative est bloquée AVANT même la vérification des identifiants
        $response = $this->post(route('login.attempt'), [
            'email' => 'admin@pressing.test', 'password' => 'password', // même le bon mot de passe
            '_token' => csrf_token(),
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Trop de tentatives',
            session('errors')->first('email'),
        );
    }

    /** La recherche live clients renvoie du JSON exploitable par le POS. */
    public function test_client_search_endpoint_returns_json(): void
    {
        $this->actingAs($this->cashier)
            ->getJson(route('clients.search', ['q' => 'Client']))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'name', 'phone']]]);
    }
}
