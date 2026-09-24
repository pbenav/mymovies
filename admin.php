<?php
require_once 'includes/auth.php';
require_admin();
header('Location: admin/users.php');
exit;
