<?php

declare(strict_types=1);

namespace PianoTrainer;

final class NoteNames
{
  private const NAMES = [
    'До', 'До-диез', 'Ре', 'Ре-диез', 'Ми', 'Фа', 'Фа-диез', 'Соль', 'Соль-диез', 'Ля', 'Ля-диез', 'Си',
  ];

  public static function fromMidi(int $midi): string
  {
    return self::NAMES[$midi % 12];
  }

  public static function isBlackKey(int $midi): bool
  {
    $n = $midi % 12;

    return in_array($n, [1, 3, 6, 8, 10], true);
  }
}
