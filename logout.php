<?php
require __DIR__.'/includes/bootstrap.php';
if (is_logged_in()) security_revoke_current_session($pdo,'Logout manual');
security_destroy_local_session();
header('Location: /login.php');
