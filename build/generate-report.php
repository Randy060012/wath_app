<?php

/**
 * GÉNÉRATION DU RAPPORT HTML (partage)
 * -----------------------------------------------------------------
 * Convertit RAPPORT_PROJET.md en RAPPORT_PROJET.html autonome :
 *  - conversion Markdown → HTML via league/commonmark (déjà présent
 *    dans vendor/, GFM = tableaux, listes de tâches…) ;
 *  - habillage CSS inline (charte bleu « Pressing Pro ») ;
 *  - diagramme Mermaid rendu côté navigateur (mermaid.min.js
 *    EMBARQUÉ inline → fichier 100 % autonome, s'ouvre partout,
 *    même sans Internet) ;
 *  - blocs de code colorés + sommaire automatique.
 *
 * Usage :  php build/generate-report.php [--public]
 * Sortie : RAPPORT_PROJET.html à la racine du projet.
 *          Avec --public : copie aussi dans public/ pour le consulter
 *          via le serveur de dev (http://127.0.0.1:8000/RAPPORT_PROJET.html).
 *          ⚠ À SUPPRIMER de public/ avant tout déploiement en production
 *          (le rapport contient l'architecture et des comptes de démo).
 */

require __DIR__ . '/../vendor/autoload.php';

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

$markdownPath = __DIR__ . '/../RAPPORT_PROJET.md';
$htmlPath     = __DIR__ . '/../RAPPORT_PROJET.html';
$mermaidPath  = __DIR__ . '/mermaid.min.js';

if (! is_file($markdownPath)) {
    fwrite(STDERR, "✗ RAPPORT_PROJET.md introuvable.\n");
    exit(1);
}

$markdown = file_get_contents($markdownPath);

// ---------------------------------------------------------------------
// 1) CONVERSION MARKDOWN → HTML (GFM : tableaux, task lists, autolink)
// ---------------------------------------------------------------------
$environment = new Environment(['html_input' => 'allow']);
$environment->addExtension(new CommonMarkCoreExtension());   // noyau obligatoire
$environment->addExtension(new GithubFlavoredMarkdownExtension()); // tableaux, tâches…
$environment->addExtension(new AutolinkExtension());

$htmlBody = (new MarkdownConverter($environment))->convert($markdown);

// Le bloc mermaid est rendu en <pre><code> : on le transforme en <div>
// pour que mermaid.js le dessine (mermaid ne traite pas les <pre>).
$htmlBody = preg_replace(
    '/<pre><code class="language-mermaid">(.*?)<\/code><\/pre>/s',
    '<div class="mermaid">$1</div>',
    $htmlBody
);

// ---------------------------------------------------------------------
// 2) SOMMAIRE automatique : ancre sur chaque h2/h3
// ---------------------------------------------------------------------
$anchorize = function (string $text): string {
    $slug = mb_strtolower(trim(preg_replace('/[^a-z0-9]+/u', '-', $text), '-'));

    return $slug !== '' ? $slug : 'section';
};

$counter = 0;
$htmlBody = preg_replace_callback('/<h([23])>(.*?)<\/h\1>/s', function ($m) use (&$counter, $anchorize) {
    $counter++;
    $slug = $anchorize(strip_tags($m[2])) . ($counter > 1 ? '-' . $counter : '');

    return sprintf('<h%s id="%s">%s</h%s>', $m[1], $slug, $m[2], $m[1]);
}, $htmlBody);

// Liens du sommaire (h2 uniquement, annexes incluses)
preg_match_all('/<h2 id="([^"]+)">(.*?)<\/h2>/s', $htmlBody, $heads, PREG_SET_ORDER);
$toc = '';
foreach ($heads as $h) {
    $title = strip_tags($h[2]);
    $toc .= sprintf('<li><a href="#%s">%s</a></li>', $h[1], htmlspecialchars($title));
}
$toc = $toc !== '' ? '<nav class="toc"><h2>Sommaire</h2><ol>' . $toc . '</ol></nav>' : '';

// Place le sommaire en HAUT de page : juste après le premier <hr>
// (séparateur qui suit l'intitulé du document).
if ($toc !== '') {
    $pos = strpos($htmlBody, '<hr />');
    if ($pos !== false) {
        $htmlBody = substr_replace($htmlBody, $toc, $pos + strlen('<hr />'), 0);
    } else {
        $htmlBody = $toc . $htmlBody;
    }
}

// ---------------------------------------------------------------------
// 3) MERMAID embarqué (autonomie totale du fichier)
// ---------------------------------------------------------------------
if (is_file($mermaidPath)) {
    $mermaidJs = file_get_contents($mermaidPath);
    $mermaidInline = '<script>' . $mermaidJs . '</script>' . "\n"
        . '<script>document.addEventListener("DOMContentLoaded",function(){'
        . 'mermaid.initialize({startOnLoad:true,theme:"base",themeVariables:{'
        . 'primaryColor:"#e0f2fe",primaryBorderColor:"#0369a1",primaryTextColor:"#0c4a6e",'
        . 'lineColor:"#64748b",fontSize:"13px"},securityLevel:"loose",flowchart:{useMaxWidth:true}});});</script>';
} else {
    $mermaidInline = '<script src="https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.min.js"></script>' . "\n"
        . '<script>mermaid.initialize({startOnLoad:true});</script>'
        . '<p class="hint">⚠ mermaid.min.js absent de build/ : chargement via CDN (connexion requise).</p>';
}

// ---------------------------------------------------------------------
// 4) PAGE FINALE
// ---------------------------------------------------------------------
$generatedAt = date('d/m/Y H:i');

$html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Rapport Projet — Pressing Pro</title>
<style>
:root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --accent:#0369a1; --bg:#f0f9ff; }
* { box-sizing:border-box; }
body { margin:0; font-family:'Segoe UI',system-ui,-apple-system,sans-serif; color:var(--ink);
       background:var(--bg); line-height:1.65; font-size:15px; }
main { max-width:1000px; margin:0 auto; background:#fff; min-height:100vh; padding:48px 56px; }
h1 { font-size:1.9rem; border-bottom:3px solid var(--accent); padding-bottom:.4rem; color:var(--accent); }
h2 { font-size:1.4rem; margin-top:2.6rem; border-bottom:1px solid var(--line); padding-bottom:.3rem; }
h3 { font-size:1.1rem; margin-top:1.8rem; color:#0c4a6e; }
a { color:var(--accent); }
code { background:#f1f5f9; border:1px solid var(--line); border-radius:4px;
       padding:.1em .35em; font-family:Consolas,Menlo,monospace; font-size:.9em; }
pre { background:#0f172a; color:#e2e8f0; padding:1rem 1.2rem; border-radius:8px; overflow-x:auto; }
pre code { background:transparent; border:0; color:inherit; padding:0; }
blockquote { margin:1rem 0; padding:.75rem 1.1rem; border-left:4px solid var(--accent);
             background:#f8fafc; border-radius:0 8px 8px 0; color:#334155; }
blockquote strong { color:var(--accent); }
table { border-collapse:collapse; width:100%; margin:1rem 0; font-size:.92em; }
th,td { border:1px solid var(--line); padding:.5rem .7rem; text-align:left; vertical-align:top; }
th { background:#f1f5f9; font-size:.85em; text-transform:uppercase; letter-spacing:.04em; }
tr:nth-child(even) td { background:#f8fafc; }
hr { border:0; border-top:1px solid var(--line); margin:2.5rem 0; }
.toc { background:#f8fafc; border:1px solid var(--line); border-radius:10px; padding:1.2rem 1.6rem; margin:2rem 0; }
.toc h2 { border:0; margin:.2rem 0 .8rem; font-size:1.05rem; }
.toc ol { margin:0; padding-left:1.2rem; columns:2; }
.toc li { margin:.25rem 0; }
.mermaid { background:#fff; border:1px solid var(--line); border-radius:10px; padding:1.2rem;
           margin:1.4rem 0; overflow-x:auto; text-align:center; }
.hint { color:#b45309; font-size:.9em; }
.stamp { color:var(--muted); font-size:.85em; text-align:center; margin-top:3rem; }
@media print { body { background:#fff; } main { max-width:none; padding:0; } pre { background:#f1f5f9; color:#0f172a; } }
</style>
</head>
<body>
<main>
{$htmlBody}
<p class="stamp">Rapport généré le {$generatedAt} — fichier autonome (mermaid embarqué).</p>
</main>
{$mermaidInline}
</body>
</html>
HTML;

// Le sommaire est calculé après conversion mais injecté à la fin du <main>
// (présence garantie même si le Markdown évolue). Déplacer le nav APRÈS
// le contenu n'est pas idéal : on le remonte juste après le premier <hr>
// s'il existe, sinon il reste en fin de document.
file_put_contents($htmlPath, $html);

// Optionnel : copie pour consultation via le serveur de dev (public/)
if (in_array('--public', $argv ?? [], true)) {
    copy($htmlPath, __DIR__ . '/../public/RAPPORT_PROJET.html');
    echo "   Copie : public/RAPPORT_PROJET.html (à retirer avant déploiement).\n";
}

$size = number_format(filesize($htmlPath) / 1024, 0, ',', ' ');
echo "✅ RAPPORT_PROJET.html généré ({$size} Ko).\n";
echo "   Ouvrez-le dans un navigateur : le diagramme Mermaid est rendu automatiquement.\n";
