<?php

declare(strict_types=1);

namespace PianoTrainer;

use PDO;

final class AnalyticsService
{
  private const MAX_PATH_LEN = 500;
  private const MAX_TARGET_LEN = 200;

  /** @var array<string, string> */
  private const SEARCH_SOURCE_LABELS = [
    'google' => 'Google',
    'yandex' => 'Яндекс',
    'bing' => 'Bing',
    'duckduckgo' => 'DuckDuckGo',
    'mail' => 'Mail.ru',
    'yahoo' => 'Yahoo',
    'rambler' => 'Rambler',
    'utm' => 'UTM (реклама)',
  ];

  /** @var array<string, string> */
  private const PAGE_LABELS = [
    '/' => 'Главная',
    '/put-novichka' => 'Путь новичка',
    '/noty' => 'Тренажёр нот',
    '/melodii' => 'Каталог мелодий',
    '/statistika' => 'Статистика',
    '/domashka' => 'Домашка',
    '/teacher' => 'Кабинет преподавателя',
    '/admin' => 'Админ-панель',
    '/trenirovka/noty' => 'Тренировка нот',
  ];

  public function __construct(private readonly PDO $db) {}

  /** @param list<array<string, mixed>> $events */
  public function recordEvents(?int $userId, string $sessionId, array $events): int
  {
    $sessionId = trim($sessionId);
    if ($sessionId === '' || mb_strlen($sessionId) > 64) {
      throw new \InvalidArgumentException('Некорректная сессия');
    }

    if ($events === []) {
      return 0;
    }

    $stmt = $this->db->prepare(
      'INSERT INTO analytics_events (user_id, session_id, event_type, page_path, target, duration_ms, created_at)
       VALUES (:user_id, :session_id, :event_type, :page_path, :target, :duration_ms, :created_at)',
    );

    $inserted = 0;
    $this->db->beginTransaction();

    try {
      foreach ($events as $event) {
        if (!is_array($event)) {
          continue;
        }

        $type = (string) ($event['type'] ?? '');
        if (!in_array($type, ['page_time', 'search_referral'], true)) {
          continue;
        }

        $pagePath = $this->normalizePath((string) ($event['path'] ?? ''));
        if ($pagePath === '') {
          continue;
        }

        $target = null;
        $durationMs = null;

        if ($type === 'search_referral') {
          $target = $this->normalizeTarget((string) ($event['target'] ?? ''));
          if ($target === null) {
            continue;
          }
          if ($type === 'search_referral' && !$this->isValidSearchReferralTarget($target)) {
            continue;
          }
        }

        if ($type === 'page_time') {
          $durationMs = max(0, min(86_400_000, (int) ($event['durationMs'] ?? 0)));
          if ($durationMs < 1000) {
            continue;
          }
        }

        $createdAt = $this->normalizeTimestamp($event['at'] ?? null);

        $stmt->execute([
          'user_id' => $userId,
          'session_id' => $sessionId,
          'event_type' => $type,
          'page_path' => $pagePath,
          'target' => $target,
          'duration_ms' => $durationMs,
          'created_at' => $createdAt,
        ]);
        $inserted++;
      }

      $this->db->commit();
    } catch (\Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return $inserted;
  }

  /** @return array<string, mixed> */
  public function getDashboard(int $days = 30): array
  {
    $days = max(1, min(365, $days));
    $since = (new \DateTimeImmutable('today'))
      ->modify('-' . ($days - 1) . ' days')
      ->format('Y-m-d H:i:s');

    return [
      'periodDays' => $days,
      'since' => $since,
      'topPagesByTime' => $this->topPagesByTime($since),
      'topSearchQueries' => $this->topSearchQueries($since),
    ];
  }

  public static function searchSourceLabel(string $source): string
  {
    return self::SEARCH_SOURCE_LABELS[$source] ?? ucfirst($source);
  }

  public static function pageLabel(string $path): string
  {
    if (isset(self::PAGE_LABELS[$path])) {
      return self::PAGE_LABELS[$path];
    }

    if (preg_match('#^/melodii/[^/]+$#', $path) === 1) {
      return 'Страница мелодии';
    }

    if (preg_match('#^/trenirovka/melodiya/[^/]+$#', $path) === 1) {
      return 'Тренировка мелодии';
    }

    return $path;
  }

  /** @return list<array<string, mixed>> */
  private function topPagesByTime(string $since): array
  {
    $stmt = $this->db->prepare(
      'SELECT
         page_path,
         COUNT(*) AS visits,
         SUM(duration_ms) AS total_ms,
         CAST(AVG(duration_ms) AS INTEGER) AS avg_ms
       FROM analytics_events
       WHERE event_type = \'page_time\' AND created_at >= :since
       GROUP BY page_path
       ORDER BY total_ms DESC, visits DESC
       LIMIT 20',
    );
    $stmt->execute(['since' => $since]);

    return array_map(function (array $row): array {
      $totalMs = (int) ($row['total_ms'] ?? 0);
      $avgMs = (int) ($row['avg_ms'] ?? 0);

      return [
        'path' => (string) $row['page_path'],
        'label' => self::pageLabel((string) $row['page_path']),
        'visits' => (int) $row['visits'],
        'totalSeconds' => (int) round($totalMs / 1000),
        'avgSeconds' => (int) round($avgMs / 1000),
      ];
    }, $stmt->fetchAll());
  }

  /** @return list<array<string, mixed>> */
  private function topSearchQueries(string $since): array
  {
    $stmt = $this->db->prepare(
      'SELECT target, COUNT(*) AS visits
       FROM analytics_events
       WHERE event_type = \'search_referral\' AND created_at >= :since
       GROUP BY target
       ORDER BY visits DESC, target ASC
       LIMIT 20',
    );
    $stmt->execute(['since' => $since]);

    return array_map(function (array $row): array {
      $parsed = self::parseSearchReferralTarget((string) $row['target']);

      return [
        'source' => $parsed['source'],
        'sourceLabel' => self::searchSourceLabel($parsed['source']),
        'query' => $parsed['query'],
        'visits' => (int) $row['visits'],
      ];
    }, $stmt->fetchAll());
  }

  /** @return array{source:string,query:string} */
  public static function parseSearchReferralTarget(string $target): array
  {
    $colonPos = strpos($target, ':');
    if ($colonPos === false) {
      return ['source' => 'unknown', 'query' => $target];
    }

    return [
      'source' => substr($target, 0, $colonPos),
      'query' => substr($target, $colonPos + 1),
    ];
  }

  private function isValidSearchReferralTarget(string $target): bool
  {
    $parsed = self::parseSearchReferralTarget($target);

    return $parsed['source'] !== '' && $parsed['query'] !== '';
  }

  private function normalizePath(string $path): string
  {
    $path = trim($path);
    if ($path === '') {
      return '';
    }

    if (!str_starts_with($path, '/')) {
      $path = '/' . $path;
    }

    $path = preg_replace('#/+#', '/', $path) ?? $path;
    if (strlen($path) > self::MAX_PATH_LEN) {
      $path = substr($path, 0, self::MAX_PATH_LEN);
    }

    return $path;
  }

  private function normalizeTarget(string $target): ?string
  {
    $target = trim(preg_replace('/\s+/u', ' ', $target) ?? $target);
    if ($target === '') {
      return null;
    }

    if (mb_strlen($target) > self::MAX_TARGET_LEN) {
      $target = mb_substr($target, 0, self::MAX_TARGET_LEN);
    }

    return $target;
  }

  private function normalizeTimestamp(mixed $value): string
  {
    if (is_string($value) && $value !== '') {
      try {
        return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
      } catch (\Exception) {
        // fall through
      }
    }

    return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
  }
}
