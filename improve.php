<?php
// ═══════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  improve.php — Amélioration de texte
//  Reçoit : POST { text: "..." }
//  Retourne : JSON { response: "..." }
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$texte = trim($_POST['text'] ?? '');
if (empty($texte)) {
    echo json_encode(['error' => 'Texte vide.']);
    exit;
}

// ── Corrections courantes ──
$corrections = [
    // Typographie
    '/\s+/'                          => ' ',           // espaces multiples
    '/([.!?])\s*([A-ZÀ-Ü])/u'       => '$1 $2',       // espace après ponctuation
    '/(\w),(\w)/u'                   => '$1, $2',      // virgule sans espace

    // Fautes communes
    '/\bça\b/u'                      => 'cela',
    '/\bpk\b/i'                      => 'pourquoi',
    '/\bsv[p]?\b/i'                  => 's\'il vous plaît',
    '/\bpcq\b/i'                     => 'parce que',
    '/\bqd\b/i'                      => 'quand',
    '/\btjrs\b/i'                    => 'toujours',
    '/\btt\b/i'                      => 'tout',
    '/\bmsgi\b/i'                    => 'message',
    '/\bpr\b/i'                      => 'pour',
    
    // Majuscule début de phrase
];

$texteCorrige = $texte;
foreach ($corrections as $pattern => $remplacement) {
    $texteCorrige = preg_replace($pattern, $remplacement, $texteCorrige);
}

// Majuscule en début de texte
$texteCorrige = ucfirst(trim($texteCorrige));

// Ajouter un point final si absent
if (!preg_match('/[.!?]$/', $texteCorrige)) {
    $texteCorrige .= '.';
}

// ── Calculer les statistiques ──
$nbMotsAvant = str_word_count(strip_tags($texte));
$nbMotsApres = str_word_count(strip_tags($texteCorrige));
$changements = levenshtein($texte, $texteCorrige);

// ── Suggestions stylistiques ──
$suggestions = [];
if ($nbMotsAvant < 20) {
    $suggestions[] = "💡 Le texte est court. Développez avec des exemples concrets.";
}
if (strpos($texte, 'je') !== false || strpos($texte, 'Je') !== false) {
    $suggestions[] = "📝 Dans un texte formel, évitez la première personne du singulier.";
}
if (substr_count($texte, '.') < 2 && $nbMotsAvant > 30) {
    $suggestions[] = "✂️ Découpez en plusieurs phrases courtes pour améliorer la lisibilité.";
}

// ── Construire la réponse ──
$r  = "✨ **TEXTE AMÉLIORÉ**\n\n";
$r .= "**Original :**\n_$texte_\n\n";
$r .= "**Corrigé :**\n$texteCorrige\n\n";

if (!empty($suggestions)) {
    $r .= "**Suggestions supplémentaires :**\n";
    foreach ($suggestions as $s) {
        $r .= "• $s\n";
    }
    $r .= "\n";
}

$r .= "---\n_$nbMotsAvant mot(s) traité(s). Corrections appliquées : typographie, abréviations, ponctuation._";

echo json_encode(['response' => $r], JSON_UNESCAPED_UNICODE);
?>
