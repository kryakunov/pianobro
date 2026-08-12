<?php

declare(strict_types=1);

namespace PianoTrainer;

final class AssetVersion
{
  public static function compute(): int
  {
    static $version = null;
    if ($version !== null) {
      return $version;
    }

    $publicDir = dirname(__DIR__) . '/public';
    $times = [
      @filemtime($publicDir . '/assets/css/style.css') ?: 0,
      @filemtime($publicDir . '/assets/favicon.svg') ?: 0,
    ];

    foreach (glob($publicDir . '/assets/js/*.js') ?: [] as $file) {
      $times[] = (int) filemtime($file);
    }

    $version = max($times);

    return $version;
  }
}
