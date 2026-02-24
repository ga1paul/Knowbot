# Knowbot
Knowbot est un prototype de chatbot dans le but d'un proposition de recherche. KnowBot est un chatbot éducatif haïtien fondé sur les chaînes de Markov. Il génère des réponses fluides en mathématiques, sciences et histoire. Développé en PHP et JavaScript. sachant que cela est un model d'IHM. 

# KnowBot — Guide d'installation

## Structure des fichiers

```
/votre-serveur-web/
│
├── knowchatbot.html   ← Interface principale (frontend)
│
├── chat.php           ← Moteur Chaîne de Markov
├── search.php         ← Recherche dans la base de connaissances
├── study.php          ← Fiches d'étude + quiz
├── improve.php        ← Amélioration de texte
├── attach.php         ← Upload de fichiers
├── login.php          ← Connexion utilisateur
├── signup.php         ← Inscription utilisateur
│
└── uploads/           ← Créé automatiquement par attach.php
```

---

## Comment ça marche ?

Le frontend (`knowchatbot.html`) envoie des requêtes **POST** via `fetch()` aux fichiers PHP.
Chaque fichier PHP retourne **toujours** un JSON de la forme :

```json
{ "response": "Texte de la réponse..." }
```
ou en cas d'erreur :
```json
{ "error": "Message d'erreur" }
```

---

## Prérequis

- PHP 7.4 ou supérieur
- Serveur web : Apache (XAMPP, WAMP, Laragon) ou Nginx
- MySQL (optionnel pour l'authentification)

---

## Installation rapide (XAMPP)

1. Copiez tous les fichiers dans `C:/xampp/htdocs/knowbot/`
2. Démarrez Apache dans XAMPP
3. Ouvrez `http://localhost/knowbot/knowchatbot.html`

---

## Configuration de la base de données (optionnel)

Pour activer la connexion/inscription, créez cette table MySQL :

```sql
CREATE DATABASE knowbot_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE knowbot_db;

CREATE TABLE utilisateurs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nom          VARCHAR(100) NOT NULL,
    email        VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    cree_le      DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

Ensuite modifiez les variables dans `login.php` et `signup.php` :
```php
$db_host = 'localhost';
$db_name = 'knowbot_db';
$db_user = 'votre_utilisateur';
$db_pass = 'votre_mot_de_passe';
```

---

## Comment ajouter des connaissances au chatbot ?

Dans `chat.php`, enrichissez le tableau `$corpus` :

```php
'nouvelle_matiere' => [
    'keywords' => ['mot1', 'mot2', 'mot3'],
    'textes' => [
        "Texte d'entraînement 1 pour la chaîne de Markov...",
        "Texte d'entraînement 2...",
    ]
],
```

Plus vous ajoutez de textes, plus la chaîne de Markov génère des réponses variées et cohérentes.

---

## Compte de démonstration (sans BDD)

Email : `demo@knowbot.ht`
Mot de passe : `demo1234`
