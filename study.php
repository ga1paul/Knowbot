<?php
// ═══════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  study.php — Mode Étude : fiche + quiz
//  Reçoit : POST { topic: "..." }
//  Retourne : JSON { response: "..." }
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$topic = trim($_POST['topic'] ?? '');
if (empty($topic)) {
    echo json_encode(['error' => 'Sujet vide.']);
    exit;
}

// ── Fiches d'étude par sujet ──
$fiches = [
    'fraction' => [
        'titre' => 'Les Fractions',
        'points_cles' => [
            'Une fraction = numérateur ÷ dénominateur',
            'Pour additionner → trouver le PPCM des dénominateurs',
            'Pour multiplier → multiplier numérateurs entre eux, puis dénominateurs',
            'Pour diviser → multiplier par l\'inverse',
        ],
        'quiz' => [
            ['q' => 'Que représente le dénominateur d\'une fraction ?', 'r' => 'Le nombre total de parties égales.'],
            ['q' => 'Comment additionne-t-on 1/2 + 1/3 ?', 'r' => 'On cherche le PPCM(2,3)=6, donc 3/6 + 2/6 = 5/6'],
        ]
    ],
    'newton' => [
        'titre' => 'Les 3 Lois de Newton',
        'points_cles' => [
            '1ère loi (Inertie) : un corps reste au repos ou en mouvement uniforme sans force',
            '2ème loi : F = m × a (Force = masse × accélération)',
            '3ème loi (Action-Réaction) : toute action entraîne une réaction égale et opposée',
        ],
        'quiz' => [
            ['q' => 'Que dit la 2ème loi de Newton ?', 'r' => 'F = m × a : la force est égale à la masse multipliée par l\'accélération.'],
            ['q' => 'Pourquoi un ballon rebondit-il ?', 'r' => '3ème loi : le sol exerce une force de réaction égale et opposée sur le ballon.'],
        ]
    ],
    'haiti' => [
        'titre' => 'La Révolution Haïtienne',
        'points_cles' => [
            '1791 : début de la révolte (Bois Caïman)',
            'Toussaint Louverture : chef militaire principal',
            'Novembre 1803 : Bataille de Vertières',
            '1er janvier 1804 : Proclamation de l\'Indépendance par Dessalines',
        ],
        'quiz' => [
            ['q' => 'Qui a proclamé l\'indépendance d\'Haïti ?', 'r' => 'Jean-Jacques Dessalines, le 1er janvier 1804.'],
            ['q' => 'Quelle est la particularité de la Révolution haïtienne ?', 'r' => 'C\'est la seule révolution d\'esclaves réussie de l\'histoire.'],
        ]
    ],
];

// ── Détecter le sujet ──
$topicLower = mb_strtolower($topic, 'UTF-8');
$ficheChoisie = null;

foreach ($fiches as $cle => $fiche) {
    if (strpos($topicLower, $cle) !== false) {
        $ficheChoisie = $fiche;
        break;
    }
}

// ── Générer la réponse ──
if ($ficheChoisie) {
    $r  = "📖 **FICHE D'ÉTUDE — {$ficheChoisie['titre']}**\n\n";
    $r .= "**Points clés à retenir :**\n";
    foreach ($ficheChoisie['points_cles'] as $i => $point) {
        $n = $i + 1;
        $r .= "$n. $point\n";
    }
    $r .= "\n**Mini-quiz de vérification :**\n\n";
    foreach ($ficheChoisie['quiz'] as $i => $qa) {
        $n = $i + 1;
        $r .= "❓ _Question $n :_ {$qa['q']}\n";
        $r .= "✅ _Réponse :_ {$qa['r']}\n\n";
    }
    $r .= "---\n_Bonne révision ! Demandez un autre sujet pour continuer._";
} else {
    $r  = "📖 **Mode Étude activé pour : « $topic »**\n\n";
    $r .= "Je prépare une fiche de révision sur ce sujet.\n\n";
    $r .= "**Conseils de méthode :**\n";
    $r .= "1. Lisez le cours une première fois sans prendre de notes\n";
    $r .= "2. Résumez les idées principales en vos propres mots\n";
    $r .= "3. Faites des fiches courtes (5 points max par fiche)\n";
    $r .= "4. Testez-vous avec des questions\n\n";
    $r .= "_Sujets disponibles : fraction, Newton, Haïti — d'autres seront ajoutés bientôt._";
}

echo json_encode(['response' => $r], JSON_UNESCAPED_UNICODE);
?>
