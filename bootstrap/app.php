<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ÉTAPE 2 — RÔLES & PERMISSIONS (Spatie Laravel-Permission) :
        // alias 'role' utilisable sur les groupes de routes
        // ->middleware('role:caissier') etc.
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            // Écrans GROUPE : super-admin (plateforme) OU propriétaire
            // self-service (le client gestionnaire de son groupe d'agences).
            'group-manager' => \App\Http\Middleware\EnsureGroupManager::class,
        ]);

        // Redirection des visiteurs non authentifiés vers la page de login
        $middleware->redirectGuestsTo('/login');

        // MULTI-TENANT : verrouille le contexte d'agence (session) sur
        // tout le groupe web authentifié + partage $currentAgency aux vues.
        $middleware->web(append: [
            \App\Http\Middleware\EnsureAgencyContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
