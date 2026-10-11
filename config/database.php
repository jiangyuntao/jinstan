<?php

declare(strict_types=1);

$file = dirname(__DIR__) . '/storage/config/database.php';

return is_file($file) ? require $file : [];
