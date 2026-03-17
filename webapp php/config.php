<?php
$lib_path = __DIR__ . '/../common.php';

if (file_exists($lib_path)) {
    require_once $lib_path;
} else {
    die("Erreur 500 : Dépendance système manquante.");
}

// --- CONFIGURATION DE L'APPLICATION ---
define('DB_SERVER', '192.168.10.18');
define('DB_USERNAME', 'db_connect'); 
define('DB_NAME', 'detechtive_db');

// --- CONFIGURATION FILE SERVER ---
define('FS_IP', '192.168.10.19'); 
define('FS_SHARE_NAME', 'Detechtive'); 
define('FS_USER', 'DETECHTIVE\\Administrator'); 
?>