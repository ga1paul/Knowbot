<?php
// ═══════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  signup.php — Inscription d'un utilisateur
//  Reçoit : POST { nom, email, password }
//  Retourne : JSON { response / error }
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();

$nom      = trim($_POST['nom'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

// ── Validation ──
if (empty($nom) || empty($email) || empty($password)) {
    echo json_encode(['error' => 'Tous les champs sont requis.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Adresse email invalide.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['error' => 'Le mot de passe doit contenir au moins 6 caractères.']);
    exit;
}

// ── Connexion BDD ──
$db_host = 'localhost';
$db_name = 'knowbot_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['response' => "Inscription simulée pour **$nom** ✅\n\nCréez la base de données MySQL pour activer la persistance. (Voir README)"]);
    exit;
}

// ── Vérifier si l'email existe déjà ──
$stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['error' => 'Cet email est déjà utilisé.']);
    exit;
}

// ── Créer le compte ──
$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare('INSERT INTO utilisateurs (nom, email, password_hash, cree_le) VALUES (?, ?, ?, NOW())');
$stmt->execute([$nom, $email, $hash]);

$_SESSION['user_id'] = $pdo->lastInsertId();
$_SESSION['nom']     = $nom;

echo json_encode(['response' => "Compte créé avec succès ! Bienvenue **$nom** 🎓\n\nVous êtes maintenant connecté à KnowBot."]);
?>
