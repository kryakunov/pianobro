<?php

declare(strict_types=1);

namespace PianoTrainer;

final class AssetVersion
{
  private const JS_DIR = '/assets/js';

  public static function compute(): int
  {
    static $version = null;
    if ($version !== null) {
      return $version;
    }

    $times = [
      self::fileMtime('/assets/css/style.css'),
      self::fileMtime('/assets/favicon.svg'),
    ];

    foreach (self::jsFiles() as $file) {
      $times[] = (int) filemtime($file);
    }

    $version = max($times);

    return $version;
  }

  public static function versionedUrl(string $publicPath): string
  {
    $path = self::normalizePublicPath($publicPath);
    $mtime = self::fileMtime($path);

    return $mtime > 0 ? $path . '?v=' . $mtime : $path;
  }

  /** @return array{scopes: array<string, array<string, string>>} */
  public static function jsImportMap(): array
  {
    $scope = [];

    foreach (self::jsFiles() as $file) {
      $base = basename($file);
      $mtime = (int) filemtime($file);
      $scope['./' . $base] = self::JS_DIR . '/' . $base . '?v=' . $mtime;
    }

    return [
      'scopes' => [
        self::JS_DIR . '/' => $scope,
      ],
    ];
  }

  /** @return list<string> */
  private static function jsFiles(): array
  {
    $dir = dirname(__DIR__) . '/public' . self::JS_DIR;
    $files = glob($dir . '/*.js') ?: [];

    sort($files);

    return $files;
  }

  private static function normalizePublicPath(string $path): string
  {
    if ($path === '') {
      return '/';
    }

    return str_starts_with($path, '/') ? $path : '/' . $path;
  }

  private static function fileMtime(string $publicPath): int
  {
    $file = dirname(__DIR__) . '/public' . self::normalizePublicPath($publicPath);

    return is_file($file) ? (int) filemtime($file) : 0;
  }
}
