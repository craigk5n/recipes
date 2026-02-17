<?php

include "rec_includes.php";

use function Recipes\Auth\getAuthManager;

$auth = getAuthManager();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\Recipes\Security\Security::validateCsrfToken($_POST['csrf_token'] ?? '')) {
        die_miserable_death("Invalid CSRF token.");
    }

    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if ($auth->login($username, $password)) {
            header("Location: index.php");
            exit;
        } else {
            header("Location: login.php?error=invalid");
            exit;
        }
    } elseif ($action === 'unlock') {
        $pin = $_POST['pin'] ?? '';
        if ($auth->unlockWithPin($pin)) {
            header("Location: " . ($_POST['return_url'] ?? 'index.php'));
            exit;
        } else {
            header("Location: login.php?error=invalid_pin");
            exit;
        }
    }
}

if ($action === 'logout') {
    $auth->logout();
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
