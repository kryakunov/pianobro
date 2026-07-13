<?php

declare(strict_types=1);

namespace PianoTrainer;

final class Lesson
{
  /**
   * @param array<int, array{midi: int, duration: int}> $notes
   * @param array<int, array{duration: int, notes: array<int, array{midi: int, hand: string}>}> $events
   */
  public function __construct(
    public readonly string $id,
    public readonly string $title,
    public readonly string $composer,
    public readonly string $difficulty,
    public readonly string $category,
    public readonly int $tempo,
    public readonly array $notes,
    public readonly array $events,
    public readonly bool $twoHands,
  ) {}

  public function toArray(): array
  {
    $events = array_map(function (array $event): array {
      return [
        'duration' => (int) $event['duration'],
        'notes' => array_map(static function (array $note): array {
          return [
            'midi' => (int) $note['midi'],
            'hand' => (string) $note['hand'],
            'name' => NoteNames::fromMidi((int) $note['midi']),
          ];
        }, $event['notes']),
      ];
    }, $this->events);

    return [
      'id' => $this->id,
      'title' => $this->title,
      'composer' => $this->composer,
      'difficulty' => $this->difficulty,
      'category' => $this->category,
      'tempo' => $this->tempo,
      'twoHands' => $this->twoHands,
      'noteCount' => $this->countNotes(),
      'eventCount' => count($this->events),
      'events' => $events,
      'notes' => $this->legacyNotes(),
    ];
  }

  /** @return list<array{midi: int, duration: int, name: string}>} */
  private function legacyNotes(): array
  {
    $out = [];
    foreach ($this->events as $event) {
      foreach ($event['notes'] as $note) {
        $out[] = [
          'midi' => (int) $note['midi'],
          'duration' => (int) $event['duration'],
          'name' => NoteNames::fromMidi((int) $note['midi']),
        ];
      }
    }
    return $out;
  }

  private function countNotes(): int
  {
    $n = 0;
    foreach ($this->events as $event) {
      $n += count($event['notes']);
    }
    return $n;
  }
}
