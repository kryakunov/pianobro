<?php

declare(strict_types=1);

namespace PianoTrainer;

final class AppUrl
{
  public static function base(): string
  {
    $configured = rtrim(Env::get('APP_URL'), '/');
    $detected = self::detectFromRequest();

    if ($configured === '') {
      return $detected;
    }

    if (self::isLocalUrl($configured) && !self::isLocalHost(self::requestHost())) {
      return $detected;
    }

    return $configured;
  }

  public static function canonical(string $path = '/'): string
  {
    $path = $path === '' ? '/' : $path;
    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }
    if ($path !== '/' && str_ends_with($path, '/')) {
      $path = rtrim($path, '/');
    }

    return self::base() . $path;
  }

  private static function detectFromRequest(): string
  {
    $scheme = self::isHttpsRequest() ? 'https' : 'http';

    return $scheme . '://' . self::requestHost();
  }

  private static function isHttpsRequest(): bool
  {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
      return true;
    }

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwardedProto === 'https') {
      return true;
    }

    $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));

    return $forwardedSsl === 'on';
  }

  private static function requestHost(): string
  {
    $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    if ($forwardedHost !== '') {
      return trim(explode(',', $forwardedHost)[0]);
    }

    return (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
  }

  private static function isLocalUrl(string $url): bool
  {
    $host = parse_url($url, PHP_URL_HOST);

    return self::isLocalHost(is_string($host) ? $host : '');
  }

  private static function isLocalHost(string $host): bool
  {
    $host = strtolower(explode(':', $host)[0]);

    return $host === 'localhost'
      || $host === '127.0.0.1'
      || $host === '::1'
      || str_ends_with($host, '.local');
  }
}
