<?php

declare(strict_types=1);

namespace PianoTrainer;

final class NotePool
{
  private const OCTAVE_RANGES = [
    'great' => ['min' => 36, 'max' => 47],
    'small' => ['min' => 48, 'max' => 59],
    'first' => ['min' => 60, 'max' => 71],
    'second' => ['min' => 72, 'max' => 83],
  ];

  /** @param array<string, mixed> $settings */
  public static function fromSettings(array $settings, ?string $poolMode = null): array
  {
    $ranges = self::selectedRanges($settings);
    if ($ranges === []) {
      return [];
    }

    $min = min(array_column($ranges, 'min'));
    $max = max(array_column($ranges, 'max'));
    $includeChromatics = !empty($settings['alteration']['sharp'])
      || !empty($settings['alteration']['flat']);
    $mode = $poolMode ?? ($includeChromatics ? 'all' : 'natural');

    $pool = [];
    for ($midi = $min; $midi <= $max; $midi++) {
      $inRange = false;
      foreach ($ranges as $range) {
        if ($midi >= $range['min'] && $midi <= $range['max']) {
          $inRange = true;
          break;
        }
      }
      if (!$inRange) {
        continue;
      }

      $black = NoteNames::isBlackKey($midi);
      if ($mode === 'natural' && $black) {
        continue;
      }
      if ($mode === 'chromatic' && !$black) {
        continue;
      }

      $pool[] = $midi;
    }

    return $pool;
  }

  /** @param array<string, mixed> $settings */
  private static function selectedRanges(array $settings): array
  {
    $ranges = [];
    $treble = $settings['treble'] ?? [];
    $bass = $settings['bass'] ?? [];

    if (!empty($treble['enabled'])) {
      if (!empty($treble['first'])) {
        $ranges[] = self::OCTAVE_RANGES['first'];
      }
      if (!empty($treble['second'])) {
        $ranges[] = self::OCTAVE_RANGES['second'];
      }
    }

    if (!empty($bass['enabled'])) {
      if (!empty($bass['small'])) {
        $ranges[] = self::OCTAVE_RANGES['small'];
      }
      if (!empty($bass['great'])) {
        $ranges[] = self::OCTAVE_RANGES['great'];
      }
    }

    return $ranges;
  }
}
