<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Diag8Test extends TestCase
{
    use RefreshDatabase;

    public function test_diag_listener_direct()
    {
        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class);

        $cashier = User::create(['name' => 'C', 'email' => 'c8@test.local', 'password' => Hash::make('password')]);
        $cashier->assignRole('caissier');
        $client = Client::create(['code' => 'CL-D8', 'name' => 'Client D8', 'email' => 'd8@test.local', 'phone' => '123']);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $client->id,
            'items' => [['service_id' => \App\Models\Service::first()->id, 'quantity' => 2]],
        ], $cashier->id);

        foreach ($order->items as $item) {
            $item->markStatus(OrderStatus::EnCours);
            $item->markStatus(OrderStatus::Repasse);
            $item->markStatus(OrderStatus::Pret);
        }

        fwrite(STDERR, "\n>>> notifs: " . $client->notifications()->count());

        // Le listener est-il vraiment enregistré DANS l'app de test ?
        $lm = $this->app->make(\Illuminate\Events\Dispatcher::class);
        $listeners = $lm->getListeners(\App\Events\OrderMarkedReady::class);
        fwrite(STDERR, "\n>>> listeners enregistrés: " . count($listeners));

        foreach ($listeners as $i => $l) {
            fwrite(STDERR, "\n>>> listener[$i]: " . (is_string($l) ? $l : (is_object($l) ? get_class($l) : gettype($l))));
        }
        fwrite(STDERR, "\n");
        $this->assertTrue(true);
    }
}
