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

  public static function hostMatchesRequest(string $urlOrHost): bool
  {
    $expected = self::normalizeHostKey(self::requestHost());
    if ($expected === '') {
      return false;
    }

    $actual = self::normalizeHostKey($urlOrHost);
    if ($actual === '') {
      return true;
    }

    if ($expected === $actual) {
      return true;
    }

    return self::hostnameOnly($expected) === self::hostnameOnly($actual);
  }

  private static function normalizeHostKey(string $hostOrUrl): string
  {
    $hostOrUrl = trim($hostOrUrl);
    if ($hostOrUrl === '') {
      return '';
    }

    if (str_contains($hostOrUrl, '://')) {
      $host = strtolower((string) (parse_url($hostOrUrl, PHP_URL_HOST) ?? ''));
      $port = parse_url($hostOrUrl, PHP_URL_PORT);
    } else {
      $hostOrUrl = strtolower($hostOrUrl);
      if (preg_match('#^\[([^\]]+)\](?::(\d+))?$#', $hostOrUrl, $matches) === 1) {
        $host = '[' . $matches[1] . ']';
        $port = isset($matches[2]) ? (int) $matches[2] : null;
      } elseif (preg_match('#^([^:]+)(?::(\d+))?$#', $hostOrUrl, $matches) === 1) {
        $host = $matches[1];
        $port = isset($matches[2]) ? (int) $matches[2] : null;
      } else {
        return '';
      }
    }

    if ($host === '') {
      return '';
    }

    if ($port !== null && $port !== 80 && $port !== 443) {
      return $host . ':' . $port;
    }

    return $host;
  }

  private static function hostnameOnly(string $key): string
  {
    if (str_starts_with($key, '[')) {
      return $key;
    }

    $pos = strrpos($key, ':');
    if ($pos === false) {
      return $key;
    }

    return substr($key, 0, $pos);
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
