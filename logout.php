<?php
require __DIR__ . '/inc/bootstrap.php';

if (is_post()) {
    csrf_check();
    unset($_SESSION['user_id']);
    session_regen();
}
redirect('');
