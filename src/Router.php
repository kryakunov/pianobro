<?php

declare(strict_types=1);

namespace PianoTrainer;

final class Router
{
  public function __construct(
    private readonly LessonRepository $lessons,
    private readonly MidiSearch $midiSearch,
    private readonly AuthService $auth,
    private readonly StatsRepository $stats,
    private readonly OAuthService $oauth,
    private readonly RoadmapService $roadmap,
  ) {}

  public function dispatch(string $uri, string $method): void
  {
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';

    if ($path === '/api/auth/me' && $method === 'GET') {
      $user = $this->auth->currentUser();
      $this->json(['user' => $user]);
      return;
    }

    if ($path === '/api/auth/register' && $method === 'POST') {
      try {
        $body = $this->readJsonBody();
        $user = $this->auth->register(
          (string) ($body['name'] ?? ''),
          (string) ($body['email'] ?? ''),
          (string) ($body['password'] ?? ''),
          (string) ($body['passwordConfirm'] ?? ''),
          (string) ($body['website'] ?? ''),
        );
        $this->json(['user' => $user]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if ($path === '/api/auth/login' && $method === 'POST') {
      try {
        $body = $this->readJsonBody();
        $user = $this->auth->login(
          (string) ($body['email'] ?? ''),
          (string) ($body['password'] ?? ''),
        );
        $this->json(['user' => $user]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 401);
      }
      return;
    }

    if ($path === '/api/auth/logout' && $method === 'POST') {
      $this->auth->logout();
      $this->json(['ok' => true]);
      return;
    }

    if ($path === '/api/auth/oauth/providers' && $method === 'GET') {
      $this->json(['providers' => $this->oauth->availableProviders()]);
      return;
    }

    if (preg_match('#^/api/auth/oauth/([a-z]+)$#', $path, $m) && $method === 'GET') {
      try {
        $this->oauth->start($m[1]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if (preg_match('#^/api/auth/oauth/([a-z]+)/callback$#', $path, $m) && $method === 'GET') {
      $this->oauth->handleCallback($m[1]);
      return;
    }

    if ($path === '/api/roadmap' && $method === 'GET') {
      try {
        $user = $this->auth->currentUser();
        $this->json($this->roadmap->getRoadmap($user['id'] ?? null));
      } catch (\Throwable $e) {
        $this->json(['error' => 'Не удалось загрузить путь обучения'], 500);
      }
      return;
    }

    if ($path === '/api/stats/notes' && $method === 'GET') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }
      $this->json($this->stats->getNoteStats($user['id']));
      return;
    }

    if ($path === '/api/stats/guest-merge' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      try {
        $body = $this->readJsonBody();
        $notes = is_array($body['notes'] ?? null) ? $body['notes'] : [];
        $merged = $this->stats->mergeGuestNoteStats($user['id'], $notes);
        $this->json(['ok' => true, 'merged' => $merged]);
      } catch (\Throwable $e) {
        $this->json(['error' => 'Не удалось перенести прогресс'], 500);
      }
      return;
    }

    if ($path === '/api/stats/session' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      try {
        $body = $this->readJsonBody();
        $mode = (string) ($body['mode'] ?? '');
        $correct = (int) ($body['correct'] ?? 0);
        $wrong = (int) ($body['wrong'] ?? 0);
        $accuracy = (int) ($body['accuracy'] ?? 0);
        $total = (int) ($body['total'] ?? 0);

        if ($mode === 'notes') {
          $attempts = is_array($body['attempts'] ?? null) ? $body['attempts'] : [];
          $settings = is_array($body['settings'] ?? null) ? $body['settings'] : null;
          $this->stats->saveNoteSession(
            $user['id'],
            $correct,
            $wrong,
            $accuracy,
            $total,
            $settings,
            $attempts,
          );
        } elseif ($mode === 'melody') {
          $lessonId = isset($body['lessonId']) ? (string) $body['lessonId'] : null;
          $this->stats->saveMelodySession($user['id'], $correct, $wrong, $accuracy, $total, $lessonId);
        } else {
          $this->json(['error' => 'Неизвестный режим'], 400);
          return;
        }

        $this->json(['ok' => true]);
      } catch (\Throwable $e) {
        $this->json(['error' => 'Не удалось сохранить статистику'], 500);
      }
      return;
    }

    if ($path === '/api/lessons' && $method === 'GET') {
      $query = trim((string) ($_GET['q'] ?? ''));
      $items = $query !== '' ? $this->lessons->search($query) : $this->lessons->all();
      $this->json(array_map(
        static function (Lesson $l): array {
          $a = $l->toArray();
          return [
            'id' => $a['id'],
            'title' => $a['title'],
            'composer' => $a['composer'],
            'difficulty' => $a['difficulty'],
            'category' => $a['category'],
            'noteCount' => $a['noteCount'],
            'eventCount' => $a['eventCount'],
            'twoHands' => $a['twoHands'],
          ];
        },
        $items,
      ));
      return;
    }

    if ($path === '/api/midi/search' && $method === 'GET') {
      $query = trim((string) ($_GET['q'] ?? ''));
      if ($query === '') {
        $this->json(['results' => []]);
        return;
      }

      $this->json([
        'query' => $query,
        'results' => $this->midiSearch->search($query),
      ]);
      return;
    }

    if (preg_match('#^/api/midi/(\d+)$#', $path, $m) && $method === 'GET') {
      $midi = $this->midiSearch->fetchMidi((int) $m[1]);
      if ($midi === null) {
        $this->json(['error' => 'MIDI не найден'], 404);
        return;
      }

      header('Content-Type: audio/midi');
      header('Content-Length: ' . strlen($midi));
      echo $midi;
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
      $this->serveStatic('/assets/favicon.svg', 'image/svg+xml');
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

  /** @return array<string, mixed> */
  private function readJsonBody(): array
  {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
      return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
      throw new \InvalidArgumentException('Некорректный JSON');
    }

    return $data;
  }

  private function serveStatic(string $path, ?string $contentType = null): void
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
      'ico' => 'image/x-icon',
    ];

    header('Content-Type: ' . ($contentType ?? $types[$ext] ?? 'application/octet-stream'));
    if ($ext === 'js' || $ext === 'css') {
      header('Cache-Control: no-cache, must-revalidate');
    }
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
