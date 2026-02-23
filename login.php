<?php
// ═══════════════════════════════════════════════════════
// KnowBot — Chatbot Éducatif Haïtien
// Copyright (C) 2026 [Non ou]
// Licensed under GNU GPL v3.0
// https://www.gnu.org/licenses/gpl-3.0.html
//  login.php — Authentification utilisateur
//  Reçoit : POST { email, password }
//  Retourne : JSON { response / error }
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();

$email    = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($email) || empty($password)) {
    echo json_encode(['error' => 'Email et mot de passe requis.']);
    exit;
}

// ── Connexion à la base de données ──
// CONFIGUREZ CES VALEURS selon votre hébergement
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
    // Si pas de BDD encore, simuler une réponse de démo
    if ($email === 'demo@knowbot.ht' && $password === 'demo1234') {
        $_SESSION['user'] = ['email' => $email, 'nom' => 'Utilisateur Démo'];
        echo json_encode(['response' => 'Connexion réussie ! Bienvenue sur KnowBot.']);
    } else {
        echo json_encode(['error' => 'Base de données non configurée. Utilisez demo@knowbot.ht / demo1234 pour tester.']);
    }
    exit;
}

// ── Vérifier les identifiants ──
$stmt = $pdo->prepare('SELECT id, nom, password_hash FROM utilisateurs WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($password, $user['password_hash'])) {
    echo json_encode(['error' => 'Email ou mot de passe incorrect.']);
    exit;
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['nom']     = $user['nom'];

echo json_encode(['response' => "Connexion réussie ! Bienvenue, **{$user['nom']}** 🎓"]);
?>
