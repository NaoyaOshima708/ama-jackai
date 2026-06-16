<?php
require_once __DIR__ . '/config.php';

// LINEログイン情報だけを削除（他のセッション値には触れない）
unset($_SESSION['line_user']);

header('Location: ' . SITE_URL . '/');
exit;

