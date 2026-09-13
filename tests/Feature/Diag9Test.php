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

class Diag9Test extends TestCase
{
    use RefreshDatabase;

    public function test_diag_listener_call()
    {
        $this->seed(RoleSeeder::class);
        $this->seed(CatalogSeeder::class);

        $cashier = User::create(['name' => 'C', 'email' => 'c9@test.local', 'password' => Hash::make('password')]);
        $cashier->assignRole('caissier');
        $client = Client::create(['code' => 'CL-D9', 'name' => 'Client D9', 'email' => 'd9@test.local', 'phone' => '123']);

        $order = app(\App\Services\OrderService::class)->createOrder([
            'client_id' => $client->id,
            'items' => [['service_id' => \App\Models\Service::first()->id, 'quantity' => 1]],
        ], $cashier->id);

        // Exécute MANUELLEMENT chaque listener enregistré
        $lm = $this->app->make(\Illuminate\Events\Dispatcher::class);
        $listeners = $lm->getListeners(\App\Events\OrderMarkedReady::class);

        $event = new \App\Events\OrderMarkedReady($order);
        foreach ($listeners as $i => $l) {
            try {
                $l($event, [$event]);
                fwrite(STDERR, "\n>>> listener[$i] exécuté OK");
            } catch (\Throwable $e) {
                fwrite(STDERR, "\n>>> listener[$i] EXC: " . get_class($e) . ' ' . $e->getMessage());
            }
        }

        fwrite(STDERR, "\n>>> notifs: " . $client->notifications()->count() . "\n");
        $this->assertTrue(true);
    }
}
