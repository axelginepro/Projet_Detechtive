<?php
session_start();

// --- FORCER L'AFFICHAGE DES ERREURS PHP (MODE DEBUG) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$debug_log = "<strong>🛠️ TRACE DE DÉBOGAGE SYSTÈME (NET USE) :</strong><br><br>";

// --- 0. CHARGEMENT DE LA SÉCURITÉ ---
if (!file_exists('config.php')) { die("Erreur critique : config.php manquant."); }
require_once 'config.php';

/**
 * 1. CONFIG INFRASTRUCTURE
* flag={Bravo_tu_as_reussi_a_te_connecter_au_serveur_de_fichiers!}
* */
$file_server_name = defined('FS_IP') ? FS_IP : "192.168.10.19";
$share_name = defined('FS_SHARE_NAME') ? FS_SHARE_NAME : "Detechtive";
$root_path = "\\\\" . $file_server_name . "\\" . $share_name . "\\"; 
$msg_status = "";
$msg_type = ""; 
$fs_connected = false;

$debug_log .= "> Chemin UNC cible : <code>" . htmlspecialchars($root_path) . "</code><br>";

/**
 * 2. SÉCURITÉ SESSION
 */
if (!isset($_SESSION['agent_id'])) { header("Location: index.php"); exit(); }
$agent_id_session = $_SESSION['agent_id'];
$nom_agent = $_SESSION['agent_name'];

if (isset($_SESSION['flash_message'])) {
    $msg_status = $_SESSION['flash_message'];
    $msg_type = "success";
    unset($_SESSION['flash_message']); 
}

/**
 * 3. CONNEXION BDD (TENTATIVE SSL ROBUSTE)
 */ 
$db_online = false;
try {
    if (!extension_loaded('pdo_mysql')) { throw new Exception("Driver pdo_mysql manquant."); }
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
    
    // --- TENTATIVE 1 : CONNEXION SÉCURISÉE SSL ---
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => "C:/webapp/Detechtive_Jedha/ca-cert.pem",
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
    ];
    
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
    $db_online = true;

} catch (Exception $e) {
    // --- TENTATIVE 2 : MODE SECOURS (SANS SSL) ---
    try {
        $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8";
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db_online = true;
    } catch (Exception $e2) {
        $db_online = false;
        $msg_status = "⚠️ ERREUR CRITIQUE BDD : " . $e2->getMessage();
        $msg_type = "error";
    }
}

// --- VÉRIFICATION DU CHIFFREMENT (POUR DÉMO) ---
$ssl_status_msg = "⚠️ ATTENTION : CONNEXION NON CHIFFRÉE (En clair)";
$ssl_color = "#e74c3c"; // Rouge par défaut

if ($db_online) {
    try {
        $stmt = $pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'");
        $status = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($status && !empty($status['Value'])) {
            $ssl_status_msg = "🔒 CONNEXION CHIFFRÉE (Protocole : " . $status['Value'] . ")";
            $ssl_color = "#2ecc71"; // Vert
        }
    } catch (Exception $e) {
        $ssl_status_msg = "Erreur vérification SSL : " . $e->getMessage();
    }
}

// --- INFO CONTACT AGENT ---
$agent_contact = "Non renseigné"; 
if ($db_online) {
    try {
        $stmt_info = $pdo->prepare("SELECT contact FROM agents WHERE id = ?");
        $stmt_info->execute([$agent_id_session]);
        $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
        if ($info && !empty($info['contact'])) { $agent_contact = $info['contact']; }
    } catch (Exception $e) {}
}

/**
 * 4. LOGIQUE : AJOUTER MISSION
 */
if (isset($_POST['add_mission']) && $db_online) {
    $new_title = $_POST['title']; $new_code = $_POST['code'];
    $new_status = $_POST['status']; $new_desc = $_POST['description']; 
    try {
        $stmt_team = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
        $stmt_team->execute([$agent_id_session]);
        $agent_data = $stmt_team->fetch(PDO::FETCH_ASSOC);
        if ($agent_data) {
            $sql_insert = "INSERT INTO investigations (title, investigation_code, status, description, team_id) VALUES (?, ?, ?, ?, ?)";
            $pdo->prepare($sql_insert)->execute([$new_title, $new_code, $new_status, $new_desc, $agent_data['team_id']]);
            $_SESSION['flash_message'] = "✅ Mission '$new_code' créée !";
            header("Location: dashboard.php"); exit(); 
        }
    } catch (Exception $e) { 
        $msg_status = "❌ Erreur création : " . $e->getMessage();
        $msg_type = "error";
    }
}

/**
 * 5. RÉCUPÉRATION MISSIONS
 */
$missions = [];
if ($db_online) {
    $stmt = $pdo->prepare("SELECT i.* FROM investigations i JOIN agents a ON i.team_id = a.team_id WHERE a.id = ? ORDER BY i.creation_date DESC");
    $stmt->execute([$agent_id_session]);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 6. GESTION FICHIERS ET CONNEXION SERVEUR DE FICHIERS
 */
$dossiers_detectes = [];
$apercus = [];
$fs_error_details = "";
$debug_msg = "";

$my_team_path_relative = ""; 
if ($db_online) {
    $stmt = $pdo->prepare("SELECT team_id FROM agents WHERE id = ?");
    $stmt->execute([$agent_id_session]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) { $my_team_path_relative = "investigations\\Team_" . $res['team_id']; }
}

$user_fs = defined('FS_USER') ? FS_USER : "Administrator";
$pass_fs = defined('FS_PASS') ? FS_PASS : "";

// Connexion réseau (UNC)
@exec("net use * /delete /y 2>&1");
$share_root_cmd = "\\\\" . $file_server_name . "\\" . $share_name; 
$cmd_auth = 'net use "' . $share_root_cmd . '" /user:"' . $user_fs . '" "' . $pass_fs . '"';

$debug_log .= "> Commande SMB exécutée : <code>" . htmlspecialchars($cmd_auth) . "</code><br>";

// Exécution de la commande et capture de la sortie système
exec($cmd_auth . " 2>&1", $output, $return_var); 

$debug_log .= "> Code de retour Windows : <strong>" . $return_var . "</strong> <br>";
$debug_log .= "> Sortie console Windows : <strong>" . htmlspecialchars(implode(" | ", $output)) . "</strong><br>";

$current_view = ""; 

$debug_log .= "> Résultat de is_dir() : ";

if (is_dir($root_path)) {
    $debug_log .= "<strong>TRUE (Dossier accessible ✅)</strong><br>";
    $fs_connected = true;
    if ($my_team_path_relative && is_dir($root_path . $my_team_path_relative)) {
        $current_view = $my_team_path_relative;
        $dossiers_detectes[] = $my_team_path_relative;
    } else {
        $current_view = ""; 
        $contenu = @scandir($root_path);
        if ($contenu) {
            foreach ($contenu as $item) {
                if ($item != "." && $item != ".." && is_dir($root_path . $item)) { $dossiers_detectes[] = $item; }
            }
        }
    }
} else { 
    $debug_log .= "<strong>FALSE (PHP n'a pas accès au dossier ❌)</strong><br>";
    $fs_connected = false; 
}

/**
 * 7. UPLOAD SÉCURISÉ
 */
if (isset($_FILES['evidence']) && isset($_POST['target_folder']) && $fs_connected) {
    // Sécurité Anti-Path Traversal
    $folder_selected = str_replace(['..', '.', '/'], '', $_POST['target_folder']);
    $final_dir = $root_path . $folder_selected;

    // Sécurité Anti-RCE (Whitelist extensions)
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'txt', 'pdf', 'docx'];
    $file_name = $_FILES['evidence']['name'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    if (!in_array($file_ext, $allowed_extensions)) {
        $msg_status = "❌ Type de fichier interdit (Sécurité).";
        $msg_type = "error";
    } elseif ($_FILES['evidence']['size'] > 5242880) { // Max 5Mo
        $msg_status = "❌ Fichier trop volumineux (Max 5Mo).";
        $msg_type = "error";
    } else {
        // Nettoyage du nom de fichier
        $safe_filename = preg_replace("/[^a-zA-Z0-9.]/", "_", pathinfo($file_name, PATHINFO_FILENAME)) . "." . $file_ext;
        $dest = $final_dir . "\\" . $safe_filename;

        if (move_uploaded_file($_FILES["evidence"]["tmp_name"], $dest)) {
            $msg_status = "✅ Fichier sécurisé transféré vers : " . $folder_selected;
            $msg_type = "success";
        } else {
            $msg_status = "❌ Erreur d'écriture sur le serveur de fichiers.";
            $msg_type = "error";
        }
    }
}

// Galerie
$view_to_show = isset($_POST['target_folder']) ? str_replace(['..', '.', '/'], '', $_POST['target_folder']) : $current_view;
if ($fs_connected && $view_to_show && is_dir($root_path . $view_to_show)) {
    $files = scandir($root_path . $view_to_show);
    foreach ($files as $f) {
        $full_p = $root_path . $view_to_show . "\\" . $f;
        if ($f != "." && $f != ".." && !is_dir($full_p)) {
            $apercus[] = ['name' => $f, 'path' => $full_p, 'ext' => strtolower(pathinfo($f, PATHINFO_EXTENSION))];
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
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.85); backdrop-filter: blur(2px); }
        .modal-content { background-color: var(--card-bg); margin: 5% auto; padding: 25px; border: 1px solid var(--accent-color); width: 90%; max-width: 500px; box-shadow: 0 0 20px rgba(0,0,0,0.7); }
        .close { float: right; font-size: 28px; cursor: pointer; color: var(--text-color); }
        .fs-status-ok { padding:10px; border:1px solid #2ecc71; color:#2ecc71; background:rgba(46, 204, 113, 0.1); text-align:center; margin-bottom:15px; }
        .fs-status-ko { padding:10px; border:1px solid var(--error-color); color:var(--error-color); background:rgba(231, 76, 60, 0.1); text-align:center; margin-bottom:15px; }
        .preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; margin-top: 20px; }
        .preview-card { background: #222; border: 1px solid var(--border-color); padding: 5px; text-align: center; cursor: pointer; transition: 0.2s; position: relative; }
        .preview-card:hover { border-color: var(--accent-color); transform: translateY(-2px); }
        .preview-img { width: 100%; height: 100px; object-fit: cover; background: #000; display: block; margin-bottom: 5px; }
        .file-name { font-size: 0.7rem; color: #888; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lightbox { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); justify-content: center; align-items: center; }
        .lightbox img { max-width: 90%; max-height: 90%; border: 2px solid var(--accent-color); box-shadow: 0 0 30px var(--accent-color); }
        .text-modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); align-items: center; justify-content: center; }
        .text-modal-content { background: #000; color: #0f0; border: 2px solid #0f0; width: 80%; max-width: 800px; height: 70%; padding: 20px; overflow: auto; font-family: 'Courier New', monospace; white-space: pre-wrap; }
        .alert-success { background-color: rgba(46, 204, 113, 0.15); border: 1px solid #2ecc71; border-left: 5px solid #2ecc71; color: #2ecc71; padding: 15px; margin-bottom: 25px; }
        .debug-box { background-color: #f1c40f; color: #2c3e50; padding: 15px; margin-bottom: 20px; font-family: monospace; border-left: 5px solid #e67e22; word-wrap: break-word; }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="debug-box">
            <?php echo $debug_log; ?>
        </div>

        <?php if($msg_status): ?>
            <div class="<?php echo ($msg_type === 'error') ? 'alert-error' : 'alert-success'; ?>">
                <?php echo $msg_status; ?>
            </div>
        <?php endif; ?>

        <div class="header-flex">
            <div>
                <h1 style="margin: 0; border:none;">AGENT: <?php echo htmlspecialchars($nom_agent); ?></h1>
                <small style="color: #666; font-family: 'Courier New';">CONTACT: <?php echo htmlspecialchars($agent_contact); ?></small>
            </div>
            <div>
                <button id="openModalBtn" style="margin-right:10px;">[ + MISSION ]</button>
                <a href="index.php" style="color: var(--error-color); font-weight: bold; text-decoration: none;">[ DÉCO ]</a>
            </div>
        </div>

        <hr style="border-color: var(--border-color); margin-bottom: 30px;">

        <div id="missionModal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2 style="color: var(--accent-color); text-align: center; border:none;">NOUVELLE MISSION</h2>
                <form method="POST">
                    <label>TITRE</label>
                    <input type="text" name="title" required>
                    <div style="display:flex; gap:15px;">
                        <div style="flex:1;">
                            <label>CODE</label>
                            <input type="text" name="code" required>
                        </div>
                        <div style="flex:1;">
                            <label>STATUT</label>
                            <select name="status" style="width:100%; padding:12px; margin:10px 0; background:#222; border:1px solid var(--border-color); color:white;">
                                <option>En Cours</option><option>Urgent</option><option>Terminé</option>
                            </select>
                        </div>
                    </div>
                    <label>DESCRIPTION</label>
                    <textarea name="description" style="height:80px;"></textarea>
                    <button type="submit" name="add_mission" style="width:100%; margin-top:10px;">ENREGISTRER</button>
                </form>
            </div>
        </div>

        <section>
            <h2>RAPPORTS DE MISSION</h2>
            <?php if (empty($missions)): ?>
                <div class="mission-card" style="justify-content:center; color:#666;">AUCUNE DONNÉE DISPONIBLE.</div>
            <?php else: ?>
                <?php foreach($missions as $m): ?>
                <div class="mission-card">
                    <div style="flex:1;">
                        <strong style="font-size:1.1rem; color:white;"><?php echo htmlspecialchars($m['title']); ?></strong>
                        <div style="font-size:0.8rem; color:#666; margin-top:5px;">
                            ID: <?php echo htmlspecialchars($m['investigation_code']); ?> | DATE: <?php echo date("d/m/Y", strtotime($m['creation_date'])); ?>
                        </div>
                        <div style="margin-top:10px; font-size:0.9rem; color:#bbb;"><?php echo nl2br(htmlspecialchars($m['description'])); ?></div>
                    </div>
                    <div class="status-badge"><?php echo htmlspecialchars($m['status']); ?></div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <br><br>

        <section>
            <h2>COFFRE-FORT NUMÉRIQUE</h2>
            <?php if ($fs_connected): ?>
                <div class="fs-status-ok">CONNEXION ÉTABLIE : <?php echo htmlspecialchars($share_name); ?></div>
                <form method="POST" enctype="multipart/form-data" style="background:#111; padding:20px; border:1px solid var(--border-color);">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <select name="target_folder" onchange="this.form.submit()" required style="flex:1; padding:12px; background:#222; border:1px solid var(--border-color); color:white;">
                            <?php foreach($dossiers_detectes as $folder): ?>
                                <option value="<?php echo htmlspecialchars($folder); ?>" <?php echo ($view_to_show == $folder) ? 'selected' : ''; ?>>
                                    📂 DOSSIER : <?php echo htmlspecialchars($folder); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($view_to_show): ?>
                            <input type="file" name="evidence" style="flex:1; margin:0;">
                            <button type="submit" style="margin:0;">UPLOAD</button>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($view_to_show && !empty($apercus)): ?>
                    <div class="preview-grid">
                        <?php $counter = 0; foreach ($apercus as $file): $counter++;
                            $is_img = in_array($file['ext'], ['jpg', 'jpeg', 'png', 'gif']);
                            $is_txt = in_array($file['ext'], ['txt', 'log', 'md']);
                            $src = ""; $txt_content = "";
                            if ($is_img) { $c = @file_get_contents($file['path']); if ($c) $src = 'data:image/'.$file['ext'].';base64,'.base64_encode($c); }
                            elseif ($is_txt) { $txt_content = htmlspecialchars(@file_get_contents($file['path'])); }
                        ?>
                            <div class="preview-card" onclick="<?php echo ($is_img && $src) ? "openLightbox('$src')" : (($is_txt) ? "openTextModal('txt-content-$counter')" : ""); ?>">
                                <?php if ($is_img && $src): ?> <img src="<?php echo $src; ?>" class="preview-img">
                                <?php elseif ($is_txt): ?> <div style="height:100px; display:flex; align-items:center; justify-content:center; font-size:2rem; color:#0f0;">📝</div>
                                                           <div id="txt-content-<?php echo $counter; ?>" style="display:none;"><?php echo $txt_content; ?></div>
                                <?php else: ?> <div style="height:100px; display:flex; align-items:center; justify-content:center; font-size:2rem;">📄</div>
                                <?php endif; ?>
                                <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="fs-status-ko">CONNEXION ÉCHOUÉE AU SERVEUR DE FICHIERS</div>
            <?php endif; ?>
        </section>
    </div>

    <div id="lightbox" class="lightbox" onclick="this.style.display='none'"><img id="lightbox-img" src=""></div>
    <div id="textModal" class="text-modal">
        <div class="text-modal-content">
            <span onclick="document.getElementById('textModal').style.display='none'" style="float:right; cursor:pointer;">[ X ] FERMER</span>
            <h3>CONTENU DU FICHIER</h3>
            <div id="textModalBody"></div>
        </div>
    </div>

    <div style="background-color: <?php echo $ssl_color; ?>; color: black; padding: 10px; text-align: center; font-weight: bold; margin-top: 20px; border-radius: 5px;">
        <?php echo $ssl_status_msg; ?>
    </div>

    <footer>&copy; 2026 DETECHTIVE AGENCY - SECURE TERMINAL V2.1</footer>

    <script>
        var modal = document.getElementById("missionModal");
        document.getElementById("openModalBtn").onclick = function() { modal.style.display = "block"; }
        document.getElementsByClassName("close")[0].onclick = function() { modal.style.display = "none"; }
        function openLightbox(src) { document.getElementById('lightbox-img').src = src; document.getElementById('lightbox').style.display = 'flex'; }
        function openTextModal(elementId) { document.getElementById('textModalBody').innerHTML = document.getElementById(elementId).innerHTML; document.getElementById('textModal').style.display = 'flex'; }
    </script>
</body>
</html>