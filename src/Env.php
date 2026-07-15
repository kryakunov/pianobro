<?php

declare(strict_types=1);

namespace PianoTrainer;

final class Env
{
  private static bool $loaded = false;

  public static function load(string $path): void
  {
    if (self::$loaded || !is_file($path)) {
      return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
      return;
    }

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || str_starts_with($line, '#')) {
        continue;
      }

      $eq = strpos($line, '=');
      if ($eq === false) {
        continue;
      }

      $key = trim(substr($line, 0, $eq));
      $value = trim(substr($line, $eq + 1));
      if (
        (str_starts_with($value, '"') && str_ends_with($value, '"'))
        || (str_starts_with($value, "'") && str_ends_with($value, "'"))
      ) {
        $value = substr($value, 1, -1);
      }

      $_ENV[$key] = $value;
      putenv($key . '=' . $value);
    }

    self::$loaded = true;
  }

  public static function get(string $key, string $default = ''): string
  {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === '') {
      return $default;
    }

    return (string) $value;
  }
}
