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

    $raw = file_get_contents($path);
    if ($raw === false) {
      return;
    }

    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
      $raw = substr($raw, 3);
    }

    $lines = preg_split('/\R/', $raw) ?: [];
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

      self::store($key, self::normalizeValue($value));
    }

    self::$loaded = true;
  }

  public static function get(string $key, string $default = ''): string
  {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === '') {
      return $default;
    }

    return self::normalizeValue((string) $value);
  }

  private static function store(string $key, string $value): void
  {
    $_ENV[$key] = $value;

    // putenv() may corrupt non-ASCII values in some PHP builds.
    if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
      putenv($key . '=' . $value);
    }
  }

  private static function normalizeValue(string $value): string
  {
    if ($value === '' || !function_exists('mb_check_encoding')) {
      return $value;
    }

    if (preg_match('/[\p{Cyrillic}]/u', $value) === 1) {
      return $value;
    }

    if (preg_match('/^[\x20-\x7E]*$/', $value) === 1) {
      return $value;
    }

    $fromMojibake = self::fixUtf8Mojibake($value);
    if ($fromMojibake !== $value) {
      return $fromMojibake;
    }

    if (!mb_check_encoding($value, 'UTF-8')) {
      $fromCp1251 = mb_convert_encoding($value, 'UTF-8', 'Windows-1251');
      if (
        mb_check_encoding($fromCp1251, 'UTF-8')
        && preg_match('/[\p{Cyrillic}]/u', $fromCp1251) === 1
      ) {
        return $fromCp1251;
      }
    }

    return $value;
  }

  /** UTF-8 Cyrillic saved as Latin-1 and stored back as UTF-8 (Ð¡Ð°Ð¼…). */
  private static function fixUtf8Mojibake(string $value): string
  {
    $bytes = '';

    foreach (preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $char) {
      $code = mb_ord($char, 'UTF-8');
      if ($code === false || $code > 255) {
        return $value;
      }
      $bytes .= chr($code);
    }

    if (
      $bytes === ''
      || !mb_check_encoding($bytes, 'UTF-8')
      || preg_match('/[\p{Cyrillic}]/u', $bytes) !== 1
    ) {
      return $value;
    }

    return $bytes;
  }
}
