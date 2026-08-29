<?php

declare(strict_types=1);

namespace PianoTrainer;

final class Router
{
  public function __construct(
    private readonly LessonRepository $lessons,
    private readonly BlogRepository $blog,
    private readonly MidiSearch $midiSearch,
    private readonly AuthService $auth,
    private readonly StatsRepository $stats,
    private readonly OAuthService $oauth,
    private readonly RoadmapService $roadmap,
    private readonly AdminService $admin,
    private readonly TeacherService $teacher,
    private readonly RoleService $roles,
    private readonly AnalyticsService $analytics,
    private readonly SubscriptionService $subscriptions,
    private readonly PaymentService $payments,
  ) {}

  public function dispatch(string $uri, string $method): void
  {
    $path = parse_url($uri, PHP_URL_PATH) ?: '/';
    $path = rtrim($path, '/') ?: '/';

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
          (string) ($body['inviteToken'] ?? ''),
          (bool) ($body['isTeacher'] ?? false),
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
          (string) ($body['inviteToken'] ?? ''),
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

    if ($path === '/api/roadmap/capstone' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      try {
        $body = $this->readJsonBody();
        $this->roadmap->markCapstoneComplete($user['id'], (string) ($body['stageId'] ?? ''));
        $this->json(['ok' => true]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      } catch (\Throwable $e) {
        $this->json(['error' => 'Не удалось сохранить прогресс'], 500);
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
      $this->renderRobotsTxt();
      return;
    }

    if ($path === '/sitemap.xml' && $method === 'GET') {
      $this->renderSitemap();
      return;
    }

    if ($path === '/admin' && $method === 'GET') {
      $this->renderAdmin();
      return;
    }

    if ($path === '/api/analytics/events' && $method === 'POST') {
      try {
        $body = $this->readJsonBody();
        $sessionId = (string) ($body['sessionId'] ?? '');
        $events = is_array($body['events'] ?? null) ? $body['events'] : [];
        $user = $this->auth->currentUser();
        $userId = $user !== null ? (int) $user['id'] : null;
        $inserted = $this->analytics->recordEvents($userId, $sessionId, $events);
        $this->json(['ok' => true, 'inserted' => $inserted]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if (preg_match('#^/api/admin/users/(\d+)/teacher$#', $path, $m) && $method === 'POST') {
      $admin = $this->requireAdmin();
      if ($admin === null) {
        return;
      }

      $body = $this->readJsonBody();
      $enabled = (bool) ($body['teacher'] ?? false);

      try {
        $result = $this->admin->setUserTeacherRole((int) $m[1], $enabled);
        $this->json($result);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if ($path === '/api/teacher/dashboard' && $method === 'GET') {
      $user = $this->requireTeacher();
      if ($user === null) {
        return;
      }
      $this->json($this->teacher->getDashboard($user['id']));
      return;
    }

    if ($path === '/api/teacher/invite' && $method === 'POST') {
      $user = $this->requireTeacher();
      if ($user === null) {
        return;
      }
      try {
        $body = $this->readJsonBody();
        $result = $this->teacher->inviteStudent($user['id'], (string) ($body['email'] ?? ''));
        $this->json($result);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      } catch (\RuntimeException $e) {
        $this->json(['error' => $e->getMessage()], 500);
      }
      return;
    }

    if (preg_match('#^/api/teacher/students/(\d+)/stats$#', $path, $m) && $method === 'GET') {
      $user = $this->requireTeacher();
      if ($user === null) {
        return;
      }
      try {
        $this->json($this->teacher->getStudentStats($user['id'], (int) $m[1]));
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 404);
      }
      return;
    }

    if (preg_match('#^/api/teacher/students/(\d+)/assignments$#', $path, $m) && $method === 'POST') {
      $user = $this->requireTeacher();
      if ($user === null) {
        return;
      }
      try {
        $body = $this->readJsonBody();
        $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
        $dueAt = isset($body['dueAt']) && $body['dueAt'] !== '' ? (string) $body['dueAt'] : null;
        $assignment = $this->teacher->createStudentAssignment(
          $user['id'],
          (int) $m[1],
          (string) ($body['title'] ?? ''),
          (string) ($body['type'] ?? ''),
          $payload,
          $dueAt,
        );
        $this->json(['assignment' => $assignment]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if (preg_match('#^/api/teacher/assignments/(\d+)/comment$#', $path, $m) && $method === 'POST') {
      $user = $this->requireTeacher();
      if ($user === null) {
        return;
      }
      try {
        $body = $this->readJsonBody();
        $this->teacher->addAssignmentComment($user['id'], (int) $m[1], (string) ($body['comment'] ?? ''));
        $this->json(['ok' => true]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if (preg_match('#^/api/teacher/assignments/(\d+)$#', $path, $m) && $method === 'DELETE') {
      $user = $this->requireTeacher();
      if ($user === null) {
        return;
      }
      try {
        $this->teacher->deleteAssignment($user['id'], (int) $m[1]);
        $this->json(['ok' => true]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 404);
      }
      return;
    }

    if (preg_match('#^/api/teacher/invite/([a-f0-9]+)$#', $path, $m) && $method === 'GET') {
      $preview = $this->teacher->getInvitationPreview($m[1]);
      if ($preview === null) {
        $this->json(['error' => 'Приглашение не найдено или уже использовано'], 404);
        return;
      }
      $this->json($preview);
      return;
    }

    if ($path === '/api/invite/accept' && $method === 'POST') {
      $user = $this->requireAuthUser();
      if ($user === null) {
        return;
      }
      try {
        $body = $this->readJsonBody();
        $token = (string) ($body['token'] ?? '');
        if ($token !== '') {
          $this->teacher->acceptInvitationToken($user['id'], $token);
        }
        $this->teacher->acceptPendingInvitationsForEmail($user['id'], (string) $user['email']);
        $this->json(['ok' => true]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if ($path === '/api/homework' && $method === 'GET') {
      $user = $this->requireStudent();
      if ($user === null) {
        return;
      }
      $this->json(['items' => $this->teacher->listHomeworkForStudent($user['id'])]);
      return;
    }

    if (preg_match('#^/api/homework/(\d+)/complete$#', $path, $m) && $method === 'POST') {
      $user = $this->requireStudent();
      if ($user === null) {
        return;
      }
      try {
        $body = $this->readJsonBody();
        $result = is_array($body['result'] ?? null) ? $body['result'] : [];
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
        $this->teacher->completeAssignment($user['id'], (int) $m[1], $result, $errors);
        $this->json(['ok' => true]);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if ($path === '/api/billing/status' && $method === 'GET') {
      $user = $this->auth->currentUser();
      $this->json([
        'pricing' => PricingConfig::toPublicArray(),
        'mockMode' => $this->payments->isMockMode(),
        'subscription' => $user !== null
          ? $this->subscriptions->getForUser($user['id'])
          : [
            'status' => 'free',
            'isPremium' => false,
            'dailyLimit' => PricingConfig::GUEST_DAILY_SESSIONS,
            'dailyNotesLimit' => PricingConfig::GUEST_DAILY_NOTES,
          ],
        'userId' => $user['id'] ?? null,
      ]);
      return;
    }

    if ($path === '/api/billing/check-session' && $method === 'POST') {
      $body = $this->readJsonBody();
      $type = (string) ($body['type'] ?? 'training');
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json([
          'allowed' => $type === 'diagnostic',
          'reason' => $type === 'diagnostic' ? null : 'auth_required',
          'isPremium' => false,
          'guest' => true,
        ]);
        return;
      }

      $this->json($this->subscriptions->canStartTraining($user['id'], $type));
      return;
    }

    if ($path === '/api/billing/use-session' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      $body = $this->readJsonBody();
      $type = (string) ($body['type'] ?? 'training');
      $check = $this->subscriptions->canStartTraining($user['id'], $type);
      if (!$check['allowed']) {
        $this->json(['error' => 'limit_reached', 'check' => $check], 403);
        return;
      }

      $this->subscriptions->recordTrainingSession($user['id'], $type);
      $this->json([
        'ok' => true,
        'subscription' => $this->subscriptions->getForUser($user['id']),
      ]);
      return;
    }

    if ($path === '/api/billing/check-notes' && $method === 'POST') {
      $body = $this->readJsonBody();
      $count = max(1, (int) ($body['count'] ?? 1));
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json([
          'allowed' => true,
          'guest' => true,
          'isPremium' => false,
        ]);
        return;
      }

      $this->json($this->subscriptions->canPlayNotes($user['id'], $count));
      return;
    }

    if ($path === '/api/billing/use-notes' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      $body = $this->readJsonBody();
      $count = max(1, (int) ($body['count'] ?? 1));
      $check = $this->subscriptions->canPlayNotes($user['id'], $count);
      if (!$check['allowed']) {
        $this->json(['error' => 'limit_reached', 'check' => $check], 403);
        return;
      }

      $this->subscriptions->recordNoteAttempts($user['id'], $count);
      $this->json([
        'ok' => true,
        'subscription' => $this->subscriptions->getForUser($user['id']),
      ]);
      return;
    }

    if ($path === '/api/billing/checkout' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      try {
        $body = $this->readJsonBody();
        $planId = (string) ($body['planId'] ?? '');
        $returnUrl = trim((string) ($body['returnUrl'] ?? ''));
        if ($returnUrl === '') {
          $returnUrl = Env::get('YOOKASSA_RETURN_URL', AppUrl::canonical('/payment?payment=return'));
        }

        $checkout = $this->payments->createCheckout($user['id'], $planId, $returnUrl);
        $this->json($checkout);
      } catch (\InvalidArgumentException $e) {
        $this->json(['error' => $e->getMessage()], 400);
      } catch (\Throwable $e) {
        $this->json([
          'error' => 'Не удалось создать платёж',
          'detail' => $e->getMessage(),
        ], 500);
      }
      return;
    }

    if ($path === '/api/billing/mock-complete' && $method === 'POST') {
      if (!$this->payments->isMockMode()) {
        $this->json(['error' => 'Недоступно'], 403);
        return;
      }

      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      try {
        $body = $this->readJsonBody();
        $paymentId = (int) ($body['paymentId'] ?? 0);
        $this->payments->completeMockPayment($paymentId, $user['id']);
        $this->json([
          'ok' => true,
          'subscription' => $this->subscriptions->getForUser($user['id']),
        ]);
      } catch (\Throwable $e) {
        $this->json(['error' => $e->getMessage()], 400);
      }
      return;
    }

    if (preg_match('#^/api/billing/payment/(\d+)$#', $path, $m) && $method === 'GET') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      $payment = $this->payments->getPayment((int) $m[1], $user['id']);
      if ($payment === null) {
        $this->json(['error' => 'Платёж не найден'], 404);
        return;
      }

      $this->json(['payment' => $payment, 'subscription' => $this->subscriptions->getForUser($user['id'])]);
      return;
    }

    if ($path === '/api/billing/diagnostic' && $method === 'POST') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      try {
        $body = $this->readJsonBody();
        $id = $this->subscriptions->saveDiagnostic($user['id'], $body);
        $this->json(['ok' => true, 'id' => $id]);
      } catch (\Throwable $e) {
        $this->json(['error' => 'Не удалось сохранить диагностику'], 500);
      }
      return;
    }

    if ($path === '/api/billing/diagnostic/latest' && $method === 'GET') {
      $user = $this->auth->currentUser();
      if ($user === null) {
        $this->json(['error' => 'Требуется вход'], 401);
        return;
      }

      $latest = $this->subscriptions->latestDiagnostic($user['id']);
      $this->json(['result' => $latest]);
      return;
    }

    if ($path === '/api/billing/webhook/yookassa' && $method === 'POST') {
      try {
        $body = $this->readJsonBody();
        $this->payments->handleWebhook($body);
        $this->json(['ok' => true]);
      } catch (\Throwable $e) {
        $this->json(['error' => 'Webhook error'], 400);
      }
      return;
    }

    if ($path === '/favicon.ico' && $method === 'GET') {
      $this->serveStatic('/assets/favicon.svg', 'image/svg+xml');
      return;
    }

    if (str_starts_with($path, '/assets/')) {
      $this->serveStatic($path);
      return;
    }

    if ($path === '/pricing' && $method === 'GET') {
      header('Location: ' . AppUrl::canonical('/payment'), true, 301);
      return;
    }

    $page = PageRegistry::match($path, $this->lessons, $this->blog);
    if ($page !== null) {
      $this->renderApp($page);
      return;
    }

    http_response_code(404);
    echo '404 Not Found';
  }

  private function baseUrl(): string
  {
    return AppUrl::base();
  }

  private function renderRobotsTxt(): void
  {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\n\nSitemap: {$this->baseUrl()}/sitemap.xml\n";
  }

  private function renderSitemap(): void
  {
    header('Content-Type: application/xml; charset=utf-8');
    $base = $this->baseUrl();
    $paths = PageRegistry::sitemapPaths($this->lessons, $this->blog);

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($paths as $path) {
      $loc = htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8');
      echo "  <url><loc>{$loc}</loc></url>\n";
    }
    echo "</urlset>\n";
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
    $path = parse_url($path, PHP_URL_PATH) ?: $path;
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

    if ($ext === 'js' || $ext === 'css') {
      $mtime = (int) filemtime($file);
      $requestedVersion = isset($_GET['v']) ? (int) $_GET['v'] : null;
      if ($requestedVersion !== $mtime) {
        header('Location: ' . $path . '?v=' . $mtime, true, 302);
        return;
      }

      header('Cache-Control: public, max-age=31536000, immutable');
      header('ETag: "' . $mtime . '"');
    }

    header('Content-Type: ' . ($contentType ?? $types[$ext] ?? 'application/octet-stream'));
    readfile($file);
  }

  private function renderApp(array $page): void
  {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $user = $this->auth->currentUser();
    $isTeacher = $user !== null && $this->roles->hasRole((int) $user['id'], RoleService::ROLE_TEACHER);
    $isStudent = $this->roles->isStudent($user);
    $pricing = PricingConfig::toPublicArray();
    $billingBoot = [
      'mockMode' => $this->payments->isMockMode(),
    ];

    include dirname(__DIR__) . '/templates/app.php';
  }

  private function renderAdmin(): void
  {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $user = $this->auth->currentUser();
    $isAdmin = $this->admin->isAdmin($user);
    $users = $isAdmin ? $this->admin->listUsersWithRoadmap() : [];
    $onlineStats = $isAdmin ? $this->admin->getDailyOnlineCounts() : ['today' => 0, 'yesterday' => 0];
    $analyticsStats = $isAdmin ? $this->analytics->getDashboard(30) : null;
    $adminConfigured = AdminService::adminEmails() !== [];
    $assetVersion = AssetVersion::compute();

    include dirname(__DIR__) . '/templates/admin.php';
  }

  /** @return array{id:int,email:string,name:string,roles:list<string>}|null */
  private function requireAdmin(): ?array
  {
    $user = $this->auth->currentUser();
    if ($user === null) {
      $this->json(['error' => 'Требуется вход'], 401);
      return null;
    }
    if (!$this->admin->isAdmin($user)) {
      $this->json(['error' => 'Нет доступа'], 403);
      return null;
    }

    return $user;
  }

  /** @return array{id:int,email:string,name:string,roles:list<string>}|null */
  private function requireTeacher(): ?array
  {
    $user = $this->auth->currentUser();
    if ($user === null) {
      $this->json(['error' => 'Требуется вход'], 401);
      return null;
    }
    if (!$this->roles->isTeacher($user)) {
      $this->json(['error' => 'Нет доступа'], 403);
      return null;
    }

    return $user;
  }

  /** @return array{id:int,email:string,name:string,roles:list<string>}|null */
  private function requireStudent(): ?array
  {
    $user = $this->auth->currentUser();
    if ($user === null) {
      $this->json(['error' => 'Требуется вход'], 401);
      return null;
    }
    if (!$this->roles->isStudent($user)) {
      $this->json(['error' => 'Раздел доступен только ученикам'], 403);
      return null;
    }

    return $user;
  }

  /** @return array{id:int,email:string,name:string,roles:list<string>}|null */
  private function requireAuthUser(): ?array
  {
    $user = $this->auth->currentUser();
    if ($user === null) {
      $this->json(['error' => 'Требуется вход'], 401);
      return null;
    }

    return $user;
  }

  /** @param mixed $data */
  private function json(mixed $data, int $status = 200): void
  {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }
}
