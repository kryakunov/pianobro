<?php

declare(strict_types=1);

namespace PianoTrainer;

final class Router
{
  public function __construct(
    private readonly LessonRepository $lessons,
  ) {}

  public function dispatch(string $uri, string $method): void
  {
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    if ($path === '/api/lessons' && $method === 'GET') {
      $this->json(array_map(
        static function (Lesson $l): array {
          $a = $l->toArray();
          return [
            'id' => $a['id'],
            'title' => $a['title'],
            'composer' => $a['composer'],
            'difficulty' => $a['difficulty'],
            'noteCount' => $a['noteCount'],
            'eventCount' => $a['eventCount'],
            'twoHands' => $a['twoHands'],
          ];
        },
        $this->lessons->all(),
      ));
      return;
    }

    if (preg_match('#^/api/lessons/([a-z0-9\-]+)$#', $path, $m) && $method === 'GET') {
      $lesson = $this->lessons->find($m[1]);
      if ($lesson === null) {
        $this->json(['error' => 'Урок не найден'], 404);
        return;
      }
      $this->json($lesson->toArray());
      return;
    }

    if ($path === '/api/keyboard-range' && $method === 'GET') {
      $this->json([
        'startMidi' => 21,
        'endMidi' => 108,
        'startName' => NoteNames::fromMidi(21),
        'endName' => NoteNames::fromMidi(108),
      ]);
      return;
    }

    if ($path === '/robots.txt' && $method === 'GET') {
      $this->serveStatic('/robots.txt');
      return;
    }

    if ($path === '/favicon.ico' && $method === 'GET') {
      $this->serveStatic('/assets/favicon.svg');
      return;
    }

    if ($path !== '/' && !str_starts_with($path, '/assets/')) {
      http_response_code(404);
      echo '404 Not Found';
      return;
    }

    if (str_starts_with($path, '/assets/')) {
      $this->serveStatic($path);
      return;
    }

    $this->renderApp();
  }

  private function serveStatic(string $path): void
  {
    $file = dirname(__DIR__) . '/public' . $path;
    if (!is_file($file)) {
      http_response_code(404);
      echo '404';
      return;
    }

    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $types = [
      'css' => 'text/css; charset=utf-8',
      'js' => 'application/javascript; charset=utf-8',
      'svg' => 'image/svg+xml',
      'png' => 'image/png',
    ];

    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($file);
  }

  private function renderApp(): void
  {
    header('Content-Type: text/html; charset=utf-8');
    include dirname(__DIR__) . '/templates/app.php';
  }

  /** @param mixed $data */
  private function json(mixed $data, int $status = 200): void
  {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }
}
