<?php
// ═══════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  search.php — Recherche dans la base de connaissances
//  Reçoit : POST { query: "..." }
//  Retourne : JSON { response: "..." }
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$query = trim($_POST['query'] ?? '');

if (empty($query)) {
    echo json_encode(['error' => 'Requête vide.']);
    exit;
}

// ── Base de connaissances locale ──
// (Dans un vrai projet, ceci sera une base de données MySQL)
$connaissances = [
    [
        'titre' => 'Fractions — Bases',
        'contenu' => "Une fraction représente une partie d'un tout. Elle s'écrit numérateur/dénominateur. Pour additionner deux fractions, on cherche d'abord le dénominateur commun (PPCM).",
        'tags' => ['fraction', 'mathématique', 'calcul', 'addition']
    ],
    [
        'titre' => 'Loi de Newton — Inertie',
        'contenu' => "La 1ère loi de Newton (principe d'inertie) : un objet au repos reste au repos, un objet en mouvement continue à la même vitesse, sauf si une force extérieure agit sur lui.",
        'tags' => ['newton', 'physique', 'inertie', 'force', 'mouvement']
    ],
    [
        'titre' => 'Révolution Haïtienne',
        'contenu' => "La Révolution haïtienne (1791–1804) est la seule révolution d'esclaves réussie de l'histoire. Elle a mené à l'indépendance d'Haïti le 1er janvier 1804 sous Jean-Jacques Dessalines.",
        'tags' => ['haïti', 'révolution', 'indépendance', '1804', 'histoire', 'dessalines', 'toussaint']
    ],
    [
        'titre' => 'Départements d\'Haïti',
        'contenu' => "Haïti compte 10 départements : Ouest (Port-au-Prince), Nord (Cap-Haïtien), Sud (Les Cayes), Artibonite (Gonaïves), Centre (Hinche), Nord-Est (Fort-Liberté), Nord-Ouest (Port-de-Paix), Sud-Est (Jacmel), Nippes (Miragoâne), Grand'Anse (Jérémie).",
        'tags' => ['géographie', 'département', 'haïti', 'capitale', 'ville']
    ],
    [
        'titre' => 'Cellule eucaryote',
        'contenu' => "Une cellule eucaryote possède un noyau délimité par une membrane nucléaire. Elle contient de l'ADN dans le noyau, des mitochondries pour l'énergie, et un réticulum endoplasmique.",
        'tags' => ['biologie', 'cellule', 'noyau', 'adn', 'sciences']
    ],
    [
        'titre' => 'Théorème de Pythagore',
        'contenu' => "Dans un triangle rectangle, le carré de l'hypoténuse est égal à la somme des carrés des deux autres côtés : c² = a² + b².",
        'tags' => ['pythagore', 'triangle', 'géométrie', 'mathématique']
    ],
];

// ── Recherche par mots-clés ──
$queryLower = mb_strtolower($query, 'UTF-8');
$resultats = [];

foreach ($connaissances as $item) {
    $score = 0;
    foreach ($item['tags'] as $tag) {
        if (strpos($queryLower, $tag) !== false || strpos($tag, $queryLower) !== false) {
            $score += 2;
        }
    }
    // Chercher aussi dans le contenu
    if (strpos(mb_strtolower($item['contenu'], 'UTF-8'), $queryLower) !== false) {
        $score += 1;
    }
    if ($score > 0) {
        $resultats[] = ['score' => $score, 'item' => $item];
    }
}

// Trier par score décroissant
usort($resultats, fn($a, $b) => $b['score'] - $a['score']);
$resultats = array_slice($resultats, 0, 3); // Top 3

// ── Construire la réponse ──
if (empty($resultats)) {
    $reponse = "🔍 Aucun résultat trouvé pour **\"$query\"**.\n\nEssayez des termes comme : *fraction, Newton, Haïti, révolution, cellule, Pythagore...*";
} else {
    $reponse = "🔍 **" . count($resultats) . " résultat(s) pour « $query »**\n\n";
    foreach ($resultats as $i => $r) {
        $n = $i + 1;
        $reponse .= "**$n. {$r['item']['titre']}**\n";
        $reponse .= $r['item']['contenu'] . "\n\n";
    }
}

echo json_encode(['response' => $reponse], JSON_UNESCAPED_UNICODE);
?>
