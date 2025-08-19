<?php
require_once '../config/config.php';
require_once '../includes/auth.php';

$auth = new Auth($pdo);
$result = $auth->logout();

redirect('../index.php', $result['message'], 'success');
?>
