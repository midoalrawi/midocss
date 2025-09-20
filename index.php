<?php
// Router بسيط بدون .htaccess
if (isset($_GET['activate']) || isset($_GET['check_key']) || isset($_GET['deactivate'])) {
    require __DIR__ . '/api.php';
    exit;
}
header('Location: login.php');
exit;
