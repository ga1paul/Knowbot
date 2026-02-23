<?php
// ═══════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  attach.php — Gestion des fichiers uploadés
//  Reçoit : POST multipart { file: ... }
//  Retourne : JSON { response: "..." }
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'Erreur lors de l\'envoi du fichier.']);
    exit;
}

$file     = $_FILES['file'];
$nom      = basename($file['name']);
$taille   = $file['size'];
$type     = $file['type'];
$tmpPath  = $file['tmp_name'];

// ── Vérification de sécurité ──
$typesAutorises = [
    'application/pdf',
    'text/plain',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
];

if (!in_array($type, $typesAutorises)) {
    echo json_encode(['error' => "Type de fichier non autorisé : $type. Envoyez un PDF, TXT, DOC ou image."]);
    exit;
}

$tailleMo = round($taille / 1024 / 1024, 2);
if ($taille > 5 * 1024 * 1024) { // Max 5 Mo
    echo json_encode(['error' => "Fichier trop grand ({$tailleMo} Mo). Maximum autorisé : 5 Mo."]);
    exit;
}

// ── Dossier de stockage ──
$dossier = __DIR__ . '/uploads/';
if (!is_dir($dossier)) {
    mkdir($dossier, 0755, true);
}

$nomUnique = uniqid('doc_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nom);
$destination = $dossier . $nomUnique;

if (!move_uploaded_file($tmpPath, $destination)) {
    echo json_encode(['error' => 'Impossible de sauvegarder le fichier.']);
    exit;
}

// ── Extraire le contenu si c'est un fichier texte ──
$apercu = '';
if ($type === 'text/plain') {
    $contenu = file_get_contents($destination);
    $apercu  = mb_substr($contenu, 0, 300, 'UTF-8');
    if (mb_strlen($contenu, 'UTF-8') > 300) $apercu .= '...';
}

// ── Construire la réponse ──
$tailleTxt = $taille < 1024
    ? "{$taille} octets"
    : ($taille < 1024 * 1024
        ? round($taille / 1024, 1) . " Ko"
        : "{$tailleMo} Mo");

$r  = "📎 **Fichier reçu avec succès !**\n\n";
$r .= "**Nom :** $nom\n";
$r .= "**Type :** $type\n";
$r .= "**Taille :** $tailleTxt\n\n";

if (!empty($apercu)) {
    $r .= "**Aperçu du contenu :**\n_$apercu_\n\n";
    $r .= "Je peux analyser ce document. Posez-moi une question dessus !";
} else {
    $r .= "Le fichier a été sauvegardé. Pour les PDF et documents Word, l'extraction de texte sera bientôt disponible.";
}

echo json_encode(['response' => $r], JSON_UNESCAPED_UNICODE);
?>
