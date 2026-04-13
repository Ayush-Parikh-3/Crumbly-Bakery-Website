<?php
require_once '../includes/config.php';
logout_user();
header("Location: " . SITE_URL . "/index.php");
exit;
