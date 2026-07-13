<?php

declare(strict_types=1);

namespace PianoTrainer;

final class LessonRepository
{
  public function __construct(
    private readonly string $lessonsDir,
  ) {}

  /** @return list<Lesson> */
  public function all(): array
  {
    $lessons = [];
    $files = glob($this->lessonsDir . '/*.json') ?: [];

    foreach ($files as $file) {
      $lesson = $this->loadFromFile($file);
      if ($lesson !== null) {
        $lessons[] = $lesson;
      }
    }

    usort($lessons, static function (Lesson $a, Lesson $b): int {
      $order = ['beginner' => 0, 'intermediate' => 1, 'advanced' => 2];
      $diff = ($order[$a->difficulty] ?? 9) <=> ($order[$b->difficulty] ?? 9);
      if ($diff !== 0) {
        return $diff;
      }

      $categories = ['popular' => 0, 'international' => 1];
      $cat = ($categories[$a->category] ?? 9) <=> ($categories[$b->category] ?? 9);
      if ($cat !== 0) {
        return $cat;
      }

      return strcmp($a->title, $b->title);
    });

    return $lessons;
  }

  public function find(string $id): ?Lesson
  {
    $path = $this->lessonsDir . '/' . $id . '.json';
    if (!is_file($path)) {
      return null;
    }

    return $this->loadFromFile($path);
  }

  /** @return list<Lesson> */
  public function search(string $query): array
  {
    $needle = mb_strtolower(trim($query));
    if ($needle === '') {
      return [];
    }

    return array_values(array_filter(
      $this->all(),
      static function (Lesson $lesson) use ($needle): bool {
        $title = mb_strtolower($lesson->title);
        $composer = mb_strtolower($lesson->composer);

        return str_contains($title, $needle) || str_contains($composer, $needle);
      },
    ));
  }

  private function loadFromFile(string $path): ?Lesson
  {
    $raw = file_get_contents($path);
    if ($raw === false) {
      return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      return null;
    }

    return new Lesson(
      id: (string) ($data['id'] ?? pathinfo($path, PATHINFO_FILENAME)),
      title: (string) ($data['title'] ?? 'Без названия'),
      composer: (string) ($data['composer'] ?? ''),
      difficulty: (string) ($data['difficulty'] ?? 'beginner'),
      category: (string) ($data['category'] ?? 'international'),
      tempo: (int) ($data['tempo'] ?? 120),
      notes: $data['notes'] ?? [],
      events: self::buildEvents($data),
      twoHands: (bool) ($data['twoHands'] ?? self::detectTwoHands($data)),
    );
  }

  /** @param array<string, mixed> $data */
  private static function buildEvents(array $data): array
  {
    if (isset($data['events']) && is_array($data['events'])) {
      $events = [];
      foreach ($data['events'] as $event) {
        if (!is_array($event) || !isset($event['notes'])) {
          continue;
        }
        $notes = [];
        foreach ($event['notes'] as $note) {
          if (!is_array($note) || !isset($note['midi'])) {
            continue;
          }
          $notes[] = [
            'midi' => (int) $note['midi'],
            'hand' => (string) ($note['hand'] ?? 'right'),
          ];
        }
        if ($notes !== []) {
          $events[] = [
            'duration' => (int) ($event['duration'] ?? 400),
            'notes' => $notes,
          ];
        }
      }
      return $events;
    }

    $events = [];
    foreach ($data['notes'] ?? [] as $note) {
      if (!is_array($note) || !isset($note['midi'])) {
        continue;
      }
      $events[] = [
        'duration' => (int) ($note['duration'] ?? 400),
        'notes' => [[
          'midi' => (int) $note['midi'],
          'hand' => 'right',
        ]],
      ];
    }
    return $events;
  }

  /** @param array<string, mixed> $data */
  private static function detectTwoHands(array $data): bool
  {
    if (isset($data['events'])) {
      foreach ($data['events'] as $event) {
        foreach ($event['notes'] ?? [] as $note) {
          if (($note['hand'] ?? '') === 'left') {
            return true;
          }
        }
      }
    }
    return false;
  }
}
