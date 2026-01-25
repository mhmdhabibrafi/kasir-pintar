<?php

declare(strict_types=1);

require_once __DIR__ . '/../helpers/auth_helper.php';

logout_user();
header('Location: ' . base_url('login.php'));
exit;
