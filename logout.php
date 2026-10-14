<?php
/**
 * LostTime - Direct Logout Script
 */
require_once __DIR__ . '/config/auth.php';

clearAuthSession();

header('Location: login.php?logged_out=1');
exit;

