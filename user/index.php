<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
	redirect('/user/dashboard.php');
} else {
	redirect('/user/login.php');
}
?>
