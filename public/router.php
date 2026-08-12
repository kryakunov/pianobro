<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
  $ext = pathinfo($file, PATHINFO_EXTENSION);
  if ($ext === 'js' || $ext === 'css') {
    require __DIR__ . '/index.php';
    return true;
  }

  return false;
}

require __DIR__ . '/index.php';

return true;
