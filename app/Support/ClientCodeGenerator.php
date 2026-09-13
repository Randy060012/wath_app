<?php

namespace App\Support;

use App\Models\Client;

/**
 * ÉTAPE 2 — GÉNÉRATEUR DE CODE CLIENT (CL-000001, CL-000002...)
 * Dérivé de l'ID auto-incrémenté → unicité garantie, zéro contention.
 * Appelé APRÈS insertion (même mécanique que les tickets de caisse).
 */
class ClientCodeGenerator
{
    public static function for(Client $client): string
    {
        return sprintf('CL-%06d', $client->id);
    }
}
