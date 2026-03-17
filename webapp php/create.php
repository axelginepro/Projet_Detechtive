<?php
// create_agent.php

// 1. On charge la config pour se connecter à la BDD
if (!file_exists('config.php')) { die("Erreur : config.php manquant."); }
require_once 'config.php';

$message = "";

// 2. Traitement du formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Récupération des données
    $username = trim($_POST['username']);
    $raw_pass = $_POST['password']; 
    $realname = trim($_POST['agent_name']);
    $contact  = trim($_POST['contact']);
    $team_id  = (int)$_POST['team_id'];

    if (!empty($username) && !empty($raw_pass)) {
        
        $conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
        if (!$conn) { die("Erreur SQL : " . mysqli_connect_error()); }

        // --- HASHER LE MOT DE PASSE ---
        $hashed_password = password_hash($raw_pass, PASSWORD_DEFAULT);

        // --- INSÉRER DANS LA TABLE AGENTS ---
        $sql = "INSERT INTO agents (username, password, agent_name, team_id, contact) VALUES (?, ?, ?, ?, ?)";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            // CORRECTION ICI : "sssis" (String, String, String, Integer, String)
            mysqli_stmt_bind_param($stmt, "sssis", $username, $hashed_password, $realname, $team_id, $contact);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "<div class='success'>✅ SUCCÈS : L'agent <strong>$username</strong> a été créé !</div>";
            } else {
                $message = "<div class='error'>❌ ERREUR SQL : " . mysqli_error($conn) . "</div>";
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "<div class='error'>❌ ERREUR PRÉPARATION : " . mysqli_error($conn) . "</div>";
        }
        mysqli_close($conn);

    } else {
        $message = "<div class='error'>⚠️ Veuillez remplir le pseudo et le mot de passe.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Création Agent (Admin)</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #1a1a1a; color: #eee; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: #2c2c2c; padding: 30px; border-radius: 8px; border: 1px solid #444; }
        h2 { text-align: center; color: #f1c40f; margin-top: 0; }
        input, select { width: 100%; padding: 10px; margin-bottom: 15px; background: #444; border: 1px solid #555; color: white; border-radius: 4px; box-sizing: border-box; }
        label { display: block; margin-bottom: 5px; font-weight: bold; font-size: 0.9em; }
        button { width: 100%; padding: 12px; background: #27ae60; color: white; border: none; font-weight: bold; cursor: pointer; border-radius: 4px; font-size: 1rem; }
        button:hover { background: #2ecc71; }
        .success { background: rgba(39, 174, 96, 0.2); border: 1px solid #27ae60; padding: 15px; margin-bottom: 20px; text-align: center; color: #2ecc71; }
        .error { background: rgba(192, 57, 43, 0.2); border: 1px solid #c0392b; padding: 15px; margin-bottom: 20px; text-align: center; color: #e74c3c; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #aaa; text-decoration: none; }
        .back-link:hover { color: white; }
    </style>
</head>
<body>

    <div class="container">
        <h2>🛠️ Créer un nouvel Agent</h2>
        
        <?php echo $message; ?>

        <form method="POST">
            <label>Identifiant (Username)</label>
            <input type="text" name="username" placeholder="ex: jbond" required>

            <label>Mot de passe</label>
            <input type="text" name="password" placeholder="ex: 007Secret" required>

            <label>Nom complet</label>
            <input type="text" name="agent_name" placeholder="ex: James Bond">

            <label>Contact (Email)</label>
            <input type="text" name="contact" placeholder="ex: 007@mi6.uk">

            <label>ID Équipe (Team ID)</label>
            <input type="number" name="team_id" value="1">

            <button type="submit">CRÉER L'AGENT</button>
        </form>

        <a href="index.php" class="back-link">← Retour au login</a>
    </div>

</body>
</html>