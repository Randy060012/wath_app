<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Diag7Test extends TestCase
{
    use RefreshDatabase;

    public function test_diag_real_flow()
    {
        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class);

        $cashier = User::create(['name' => 'C', 'email' => 'c7@test.local', 'password' => Hash::make('password')]);
        $cashier->assignRole('caissier');
        $client = Client::create(['code' => 'CL-D7', 'name' => 'Client D7']);

        // Espion SUR le vrai dispatcher (pas de fake)
        $fired = 0;
        Event::listen(\App\Events\OrderMarkedReady::class, function () use (&$fired) {
            $fired++;
            fwrite(STDERR, "\n>>> EVENT REÇU #{$fired}");
        });

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $client->id,
            'items' => [['service_id' => \App\Models\Service::first()->id, 'quantity' => 3]],
        ], $cashier->id);

        foreach ($order->items as $item) {
            $item->markStatus(OrderStatus::EnCours);
            $item->markStatus(OrderStatus::Repasse);
            $item->markStatus(OrderStatus::Pret);
        }

        fwrite(STDERR, "\n>>> événements tirés: {$fired}");
        fwrite(STDERR, "\n>>> notifs en DB: " . $client->notifications()->count());
        fwrite(STDERR, "\n>>> statut final: " . $order->fresh()->status->value . "\n");
        $this->assertTrue(true);
    }
}
