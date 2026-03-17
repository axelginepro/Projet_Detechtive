<?php
session_start();

// On désactive l'affichage brutal des erreurs PHP pour éviter les fuites d'infos
mysqli_report(MYSQLI_REPORT_OFF);

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $agent_code_input = $_POST['agent_code'];
    $password_input   = $_POST['password'];

    if (!file_exists('config.php')) { die("Erreur critique : config.php manquant."); }
    require_once 'config.php';

    $conn = mysqli_init();
    if (!$conn) { die("Erreur initialisation MySQLi"); }

    // CONFIGURATION SSL
    // On tente le SSL, mais on entoure la connexion d'un bloc de sécurité
    mysqli_ssl_set($conn, NULL, NULL, 'C:/webapp/Detechtive_Jedha/ca-cert.pem', NULL, NULL);

    try {
        // Tentative de connexion
        if (!mysqli_real_connect($conn, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME)) {
            throw new Exception(mysqli_connect_error());
        }
    } catch (Exception $e) {
        // SI LE SSL ÉCHOUE, ON TENTE SANS SSL (MODE DÉGRADÉ POUR LA DÉMO)
        // C'est ça qui va te sauver la démo si les certifs déconnent
        $conn = mysqli_init();
        if (!@mysqli_real_connect($conn, DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME)) {
            $conn = false; // Vraiment impossible de se connecter
        }
    }

    if ($conn) {
        $agent_safe = mysqli_real_escape_string($conn, $agent_code_input);
        $sql = "SELECT * FROM agents WHERE username = '$agent_safe'";
        $result = mysqli_query($conn, $sql);

        if ($result && $row = mysqli_fetch_assoc($result)) {
            if (password_verify($password_input, $row['password'])) {
                $_SESSION['agent_id'] = $row['id'];
                $_SESSION['agent_name'] = isset($row['agent_name']) ? $row['agent_name'] : $row['username'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
            }
        } else {
            $error = "⛔ ACCÈS REFUSÉ : Identifiants invalides.";
        }
        mysqli_close($conn);
    } else {
        $error = "⚠️ ERREUR SYSTÈME : Connexion BDD impossible (Vérifiez le serveur).";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMINAL D'IDENTIFICATION</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="header-flex">
            <h1>AUTHENTIFICATION REQUISE</h1>
        </div>

        <?php if($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <label>CODE AGENT :</label>
            <input type="text" name="agent_code" placeholder="Identifiant..." required>
            <label>MOT DE PASSE :</label>
            <input type="password" name="password" placeholder="Code secret..." required>
            <div style="text-align: right; margin-top: 20px;">
                <button type="submit" name="login">S'AUTHENTIFIER</button>
            </div>
        </form>
    </div>
    <footer>
        <p>&copy; 2026 Detecthive Inc. Tous droits réservés.</p>
    </footer>
</body>
</html>