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
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    if ($extension === 'js' || $extension === 'css') {
      return $path . '?v=' . self::compute();
    }

    $mtime = self::fileMtime($path);

    return $mtime > 0 ? $path . '?v=' . $mtime : $path;
  }

  /**
   * Safari на iPhone не применяет import map к модулю, адрес которого уже содержит ?v=.
   * Тогда соседние файлы грузятся без версии и остаются в кэше после выкладки.
   * Версию дописываем прямо в import, чтобы браузер запрашивал новый адрес.
   */
  public static function rewriteJsImports(string $code, int $version): string
  {
    $rewritten = preg_replace_callback(
      '/(?P<prefix>\b(?:from|import)\s*\(?\s*[\'"])(?P<path>(?:\.|\.\.)\/[^\'"]+?\.js)(?P<suffix>[\'"])/',
      static function (array $match) use ($version): string {
        if (str_contains($match['path'], '?')) {
          return $match[0];
        }

        return $match['prefix'] . $match['path'] . '?v=' . $version . $match['suffix'];
      },
      $code
    );

    return is_string($rewritten) ? $rewritten : $code;
  }

  /** @return array{imports: array<string, string>, scopes: array<string, array<string, string>>} */
  public static function jsImportMap(): array
  {
    $version = self::compute();
    $imports = [];
    $scope = [];

    foreach (self::jsFiles() as $file) {
      $base = basename($file);
      $versioned = self::JS_DIR . '/' . $base . '?v=' . $version;
      $imports[self::JS_DIR . '/' . $base] = $versioned;
      $imports['./' . $base] = $versioned;
      $scope['./' . $base] = $versioned;
      $scope[self::JS_DIR . '/' . $base] = $versioned;
    }

    return [
      'imports' => $imports,
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
