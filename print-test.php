<?php
declare(strict_types=1);
require __DIR__ . '/includes/auth.php';
require_auth();
redirect('print-settings.php');
