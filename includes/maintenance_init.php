<?php
// Include this at the top of pages that should check for maintenance mode
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/maintenance_check.php';

$database = new Database();
$db = $database->getConnection();

if(checkMaintenanceMode($db)) {
    header('Location: /maintenance.php');
    exit();
}
?>