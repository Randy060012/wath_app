<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * ÉTAPE 3 — CASE DE TESTS DE BASE
 * -----------------------------------------------------------------
 * Correctif d'environnement Windows/Git Bash (MSYS2) :
 * le shell Git Bash exporte SESSION_PATH, QUEUE_CONNECTION, MAIL_MAILER...
 * et la couche MSYS convertit les valeurs "en chemin" (ex: SESSION_PATH=/
 * devient "C:/Program Files (x86)/Git/"). Ces valeurs polluent $_ENV et
 * $_SERVER ; comme dotenv refuse d'écraser une variable déjà présente,
 * la config Laravel hérite des valeurs du SHELL au lieu de phpunit.xml
 * (dont l'option force="true" ne met à jour QUE getenv(), pas $_ENV/$_SERVER).
 *
 * Solution : on normalise les superglobales AVANT le boot, pour que la
 * configuration de test s'applique réellement.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Variables imposées pour les tests (phpunit.xml ne suffit pas sous
     * Git Bash/Windows, voir la note de classe).
     */
    private const TEST_ENV = [
        'SESSION_PATH'     => '/',
        'SESSION_DRIVER'   => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'MAIL_MAILER'      => 'array',
        'DB_CONNECTION'    => 'sqlite',
        'DB_DATABASE'      => ':memory:',
        'CACHE_STORE'      => 'array',
    ];

    protected function setUp(): void
    {
        $this->normalizeTestEnvironment();

        parent::setUp();
    }

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();

        // Filet de sécurité : si la session a hérité du chemin corrompu par
        // MSYS, on la répare avant tout traitement de requête (cookies).
        if (str_contains((string) config('session.path'), 'Program Files')) {
            config(['session.path' => '/']);
        }

        return $app;
    }

    /**
     * Écrase les variables polluées par l'environnement Windows/Git Bash
     * dans les trois « registres » consultés par Laravel (dotenv lit
     * $_ENV + $_SERVER via ServerConstAdapter/EnvConstAdapter).
     */
    private function normalizeTestEnvironment(): void
    {
        foreach (self::TEST_ENV as $name => $value) {
            $polluted = isset($_ENV[$name]) && str_contains((string) $_ENV[$name], 'Program Files');

            // Variables imposées pour la reproductibilité des tests ;
            // SESSION_PATH : on répare seulement si elle est corrompue.
            $mustForce = $name !== 'SESSION_PATH' || $polluted;

            if (isset($_ENV[$name]) && $mustForce) {
                $_ENV[$name] = $value;
            }

            if (isset($_SERVER[$name]) && $mustForce) {
                $_SERVER[$name] = $value;
            }

            if (getenv($name) !== false && $mustForce) {
                putenv($name . '=' . $value);
            }
        }
    }
}
