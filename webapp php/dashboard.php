<?php
session_start();

// --- 0. CHARGEMENT DE LA SÉCURITÉ ---
if (!file_exists('config.php')) { 
    die("Erreur critique : config.php manquant."); 
}
require_once 'config.php';

/**
 * 1. CONFIG INFRASTRUCTURE
 * flag={Bravo_tu_as_reussi_a_te_connecter_au_serveur_de_fichiers!}
 */
$file_server_name = defined('FS_IP') ? FS_IP : "192.168.10.20"; [cite: 30, 148]
$share_name = defined('FS_SHARE_NAME') ? FS_SHARE_NAME : "Detechtive";
$root_path = "\\\\" . $file_server_name . "\\" . $share_name . "\\"; 

$msg_status = "";
$msg_type = ""; 
$fs_connected = false;
$current_view = ""; // FIX : Initialisation pour éviter l'erreur "Undefined variable"

/**
 * 2. SÉCURITÉ SESSION
 */
if (!isset($_SESSION['agent_id'])) { 
    header("Location: index.php"); 
    exit(); 
}
$agent_id_session = $_SESSION['agent_id'];
$nom_agent = $_SESSION['agent_name'];

if (isset($_SESSION['flash_message'])) {
    $msg_status = $_SESSION['flash_message'];
    $msg_type = "success";
    unset($_SESSION['flash_message']); 
}

/**
 * 3. CONNEXION BDD (TENTATIVE SSL)
 */ 
$db_online = false;
try {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => "C:/webapp/Detechtive_Jedha/ca-cert.pem",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ];
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    $db_online = true;
} catch (Exception $e) {
    try {
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db_online = true;
    } catch (Exception $e2) {
        $db_online = false;
        $msg_status = "⚠️ ERREUR BDD : " . $e2->getMessage();
        $msg_type = "error";
    }
}

// Vérification du chiffrement pour l'affichage
$ssl_status_msg = "⚠️ CONNEXION NON CHIFFRÉE";
$ssl_color = "#e74c3c";
if ($db_online) {
    $stmt = $pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
    $status = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($status && !empty($status['Value'])) {
        $ssl_status_msg = "🔒 CONNEXION CHIFFRÉE (Protocole : " . $status['Value'] . ")";
        $ssl_color = "#2ecc71";
    }
}

/**
 * 4. GESTION DES MISSIONS (BDD)
 */
if (isset($_POST['add_mission']) && $db_online) {
    try {
        $sql = "INSERT INTO investigations (title, investigation_code, status, description, team_id) 
                SELECT ?, ?, ?, ?, team_id FROM agents WHERE id = ?";
        $pdo->prepare($sql)->execute([$_POST['title'], $_POST['code'], $_POST['status'], $_POST['description'], $agent_id_session]);
        $_SESSION['flash_message'] = "✅ Mission créée !";
        header("Location: dashboard.php"); exit();
    } catch (Exception $e) { $msg_status = "❌ Erreur : " . $e->getMessage(); $msg_type = "error"; }
}

$missions = [];
if ($db_online) {
    $stmt = $pdo->prepare("SELECT i.* FROM investigations i JOIN agents a ON i.team_id = a.team_id WHERE a.id = ? ORDER BY i.creation_date DESC");
    $stmt->execute([$agent_id_session]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 5. CONNEXION AU SERVEUR DE FICHIERS (SMB)
 */
$dossiers_detectes = [];
$apercus = [];
$user_fs = defined('FS_USER') ? FS_USER : "Administrator";
$pass_fs = defined('FS_PASS') ? FS_PASS : "";

// Tentative de montage du partage réseau
@exec("net use * /delete /y > nul 2>&1");
$share_root_cmd = "\\\\" . $file_server_name . "\\" . $share_name;
$cmd_auth = 'net use "' . $share_root_cmd . '" /user:"' . $user_fs . '" "' . $pass_fs . '"';
exec($cmd_auth . " 2>&1", $output, $return_var);

if (is_dir($root_path)) {
    $fs_connected = true;
    // Détection automatique du dossier d'équipe
    $team_folder = "";
    if ($db_online) {
        $stmt = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
        $stmt->execute([$agent_id_session]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) { $team_folder = "investigations\\Team_" . $res['team_id']; }
    }

    if ($team_folder && is_dir($root_path . $team_folder)) {
        $current_view = $team_folder;
        $dossiers_detectes[] = $team_folder;
    } else {
        $contenu = @scandir($root_path);
        if ($contenu) {
            foreach ($contenu as $item) {
                if ($item != "." && $item != ".." && is_dir($root_path . $item)) { $dossiers_detectes[] = $item; }
            }
        }
    }
}

/**
 * 6. UPLOAD ET APERÇU
 */
if (isset($_FILES['evidence']) && isset($_POST['target_folder']) && $fs_connected) {
    $folder = str_replace(['..', '.', '/'], '', $_POST['target_folder']);
    $file_ext = strtolower(pathinfo($_FILES['evidence']['name'], PATHINFO_EXTENSION));
    if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'docx'])) {
        $dest = $root_path . $folder . "\\" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES['evidence']['name']);
        if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $dest)) {
            $msg_status = "✅ Fichier transféré."; $msg_type = "success";
        }
    }
}

$view_to_show = isset($_POST['target_folder']) ? str_replace(['..', '.', '/'], '', $_POST['target_folder']) : $current_view;
if ($fs_connected && $view_to_show && is_dir($root_path . $view_to_show)) {
    $files = scandir($root_path . $view_to_show);
    foreach ($files as $f) {
        if ($f != "." && $f != ".." && !is_dir($root_path . $view_to_show . "\\" . $f)) {
            $apercus[] = ['name' => $f, 'path' => $root_path . $view_to_show . "\\" . $f, 'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION))];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Detechtive Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .fs-status-ok { padding:10px; border:1px solid #2ecc71; color:#2ecc71; background:rgba(46, 204, 113, 0.1); text-align:center; margin-bottom:15px; }
        .fs-status-ko { padding:10px; border:1px solid #e74c3c; color:#e74c3c; background:rgba(231, 76, 60, 0.1); text-align:center; margin-bottom:15px; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; }
        .preview-card { background: #222; border: 1px solid #444; padding: 10px; text-align: center; }
        .alert-success { border-left: 5px solid #2ecc71; background: #1a3324; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <?php if($msg_status): ?>
            <div class="<?php echo ($msg_type === 'error') ? 'alert-error' : 'alert-success'; ?>"><?php echo $msg_status; ?></div>
        <?php endif; ?>

        <div class="header-flex">
            <h1>AGENT: <?php echo htmlspecialchars($nom_agent); ?></h1>
            <a href="index.php" style="color:#e74c3c;">[ DÉCONNEXION ]</a>
        </div>

        <section>
            <h2>COFFRE-FORT NUMÉRIQUE</h2>
            <?php if ($fs_connected): ?>
                <div class="fs-status-ok">✔ CONNEXION ÉTABLIE AU SERVEUR DE FICHIERS</div>
                <form method="POST" enctype="multipart/form-data">
                    <select name="target_folder" onchange="this.form.submit()">
                        <?php foreach($dossiers_detectes as $folder): ?>
                            <option value="<?php echo $folder; ?>" <?php echo ($view_to_show == $folder) ? 'selected' : ''; ?>>📂 <?php echo $folder; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="evidence">
                    <button type="submit">UPLOAD</button>
                </form>
                <div class="preview-grid">
                    <?php foreach ($apercus as $file): ?>
                        <div class="preview-card">
                            <div style="font-size: 2rem;">📄</div>
                            <div style="font-size: 0.7rem;"><?php echo htmlspecialchars($file['name']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="fs-status-ko">✖ CONNEXION ÉCHOUÉE AU SERVEUR DE FICHIERS (Vérifiez le port 445 et les droits SMB)</div>
            <?php endif; ?>
        </section>

        <div style="background: <?php echo $ssl_color; ?>; color: black; padding: 10px; text-align: center; font-weight: bold; margin-top: 30px;">
            <?php echo $ssl_status_msg; ?>
        </div>
    </div>
    <footer style="text-align:center; margin-top:20px; color:#666;">&copy; 2026 DETECHTIVE AGENCY - SECURE TERMINAL V2.1</footer>
</body>
</html>