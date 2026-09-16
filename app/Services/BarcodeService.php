<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * ÉTAPE 3 — SERVICE ÉTIQUETTES : codes-barres & QR codes
 * -----------------------------------------------------------------
 * - next()        : alloue le prochain code unique (BC-2026-000001) en
 *                   tenant compte de l'année. L'unicité est garantie par
 *                   un compteur atomique en base (table jobs-like via
 *                   cache lock) + contrainte UNIQUE en filet de sécurité.
 * - barcodeSvg()  : rend le code en SVG Code128 (vectoriel = netteté
 *                   parfaite à l'impression thermique, aucun fichier
 *                   temporaire, intégrable directement dans la Blade).
 * - qrCodeDataUri(): QR (utilisé sur l'étiquette pour lecture smartphone
 *                   au retrait : contient directement le barcode).
 *
 * Implémentation du compteur : table `barcode_sequences` (une ligne par
 * année) mise à jour par UPSERT atomique — portable SQLite / MySQL.
 */
class BarcodeService
{
    /** Préfixe commun : BC-2026-000001 */
    public function next(): string
    {
        // INSERT ... ON CONFLICT / ON DUPLICATE KEY : UPSERT atomique,
        // pas besoin de verrou applicatif, sûr sous forte activité.
        $year = now()->format('Y');

        $number = match (DB::getDriverName()) {
            'mysql', 'mariadb' => $this->nextMysql($year),
            default            => $this->nextSqlite($year), // sqlite & pgsql
        };

        return sprintf('%s-%s-%06d', config('pressing.barcode_prefix', 'BC'), $year, $number);
    }

    /** SQLite/PostgreSQL : UPSERT + RETURNING (SQLite >= 3.35). */
    private function nextSqlite(string $year): int
    {
        $seq = DB::selectOne(
            'INSERT INTO barcode_sequences (year, last_number, created_at, updated_at)
             VALUES (?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
             ON CONFLICT(year) DO UPDATE SET
                last_number = last_number + 1,
                updated_at = CURRENT_TIMESTAMP
             RETURNING last_number',
            [$year]
        );

        return (int) $seq->last_number;
    }

    /** MySQL/MariaDB : astuce LAST_INSERT_ID(expr) qui renvoie la valeur posée. */
    private function nextMysql(string $year): int
    {
        DB::statement(
            'INSERT INTO barcode_sequences (year, last_number, created_at, updated_at)
             VALUES (?, LAST_INSERT_ID(1), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                last_number = LAST_INSERT_ID(last_number + 1),
                updated_at = NOW()',
            [$year]
        );

        return (int) DB::selectOne('SELECT LAST_INSERT_ID() AS n')->n;
    }

    /**
     * Rend un code-barres Code128 en SVG (string intégrable dans Blade
     * via {!! $svg !!} — sûr, généré localement, aucune donnée externe).
     */
    public function barcodeSvg(string $barcode, int $widthFactor = 2, int $height = 60): string
    {
        $generator = new BarcodeGeneratorSVG();

        return $generator->getBarcode($barcode, BarcodeGeneratorSVG::TYPE_CODE_128, $widthFactor, $height);
    }

    /**
     * QR code en data-URI (img src direct), contenu = code de l'article.
     * Sortie SVG vectorielle (netteté parfaite à l'impression, aucune
     * dépendance GD) encapsulée en base64 par la librairie elle-même
     * (outputBase64 = true, défaut) → data-URI « image/svg+xml ».
     */
    public function qrCodeDataUri(string $barcode): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64'    => true,
            'eccLevel'        => EccLevel::L,
            'scale'           => 4,
        ]);

        return (new QRCode($options))->render($barcode);
    }
}
