<?php
/**
 * Выход из системы
 */

require_once '../config.php';

$auth = new Auth();
$auth->logout();

redirect('/admin/login.php');