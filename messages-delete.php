<?php
session_start();
require_once 'config/database.php';
require_once 'classes/PrivateMessaging.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$thread_id = isset($_GET['thread']) ? (int)$_GET['thread'] : 0;

if(!$thread_id) {
    header('Location: messages-inbox.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$pm = new PrivateMessaging($db);

$result = $pm->deleteThread($thread_id, $_SESSION['user_id']);

if($result) {
    header('Location: messages-inbox.php?deleted=1');
} else {
    header('Location: messages-inbox.php?error=delete_failed');
}
exit();
?>
