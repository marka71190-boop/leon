<?php
require dirname(__DIR__) . '/inc/bootstrap.php';

if (is_post()) {
    csrf_check();
    unset($_SESSION['admin_id']);
    session_regen();
}
redirect('admin/login.php');
