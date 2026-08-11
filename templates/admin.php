<?php

declare(strict_types=1);

/** @var array{id:int,email:string,name:string}|null $user */
/** @var bool $isAdmin */
/** @var bool $adminConfigured */
/** @var list<array<string, mixed>> $users */
/** @var array{today:int,yesterday:int} $onlineStats */
/** @var array{periodDays:int,since:string,topClicks:list<array{target:string,clicks:int}>,topPagesByTime:list<array{path:string,label:string,visits:int,totalSeconds:int,avgSeconds:int}>,topPagesByViews:list<array{path:string,label:string,views:int}>,topSearchQueries:list<array{source:string,sourceLabel:string,query:string,visits:int}>}|null $analyticsStats */

$formatDate = static function (?string $value): string {
  if ($value === null || $value === '') {
    return '—';
  }

  try {
    $date = new DateTimeImmutable($value);
  } catch (\Exception) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }

  return $date->format('d.m.Y H:i');
};

$formatDuration = static function (int $seconds): string {
  if ($seconds < 60) {
    return $seconds . ' сек';
  }

  $minutes = intdiv($seconds, 60);
  if ($minutes < 60) {
    return $minutes . ' мин';
  }

  $hours = intdiv($minutes, 60);
  $restMinutes = $minutes % 60;

  return $restMinutes > 0 ? $hours . ' ч ' . $restMinutes . ' мин' : $hours . ' ч';
};

$stageStatusLabel = static function (array $stage): string {
  if ($stage['completed']) {
    return 'Готово';
  }
  if ($stage['capstoneReady']) {
    return 'Мелодия';
  }
  if (!$stage['unlocked']) {
    return 'Закрыт';
  }
  if ((int) $stage['progress'] > 0) {
    return (int) $stage['progress'] . '%';
  }

  return 'Новый';
};
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Админ-панель | Piano Bro</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="admin-page">
  <div class="admin">
    <header class="admin__header">
      <div>
        <h1 class="admin__title">Админ-панель</h1>
        <p class="admin__subtitle">Пользователи, прогресс и статистика действий</p>
      </div>
      <div class="admin__header-actions">
        <a href="/" class="btn btn--secondary btn--sm">На сайт</a>
        <?php if ($user !== null): ?>
        <span class="admin__user"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
    </header>

    <?php if (!$adminConfigured): ?>
    <section class="admin-card admin-card--warn">
      <h2 class="admin-card__title">Доступ не настроен</h2>
      <p>Добавьте в <code>.env</code> список email администраторов:</p>
      <pre class="admin-code">ADMIN_EMAILS=you@example.com</pre>
    </section>
    <?php elseif ($user === null): ?>
    <section class="admin-card">
      <h2 class="admin-card__title">Нужен вход</h2>
      <p>Войдите в аккаунт администратора на главной странице, затем вернитесь сюда.</p>
      <a href="/" class="btn btn--primary">Перейти на сайт</a>
    </section>
    <?php elseif (!$isAdmin): ?>
    <section class="admin-card admin-card--warn">
      <h2 class="admin-card__title">Нет доступа</h2>
      <p>Аккаунт <strong><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong> не входит в список администраторов.</p>
    </section>
    <?php else: ?>
    <section class="admin-summary">
      <div class="admin-stat">
        <span class="admin-stat__value"><?= (int) $onlineStats['today'] ?></span>
        <span class="admin-stat__label">Было онлайн сегодня</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__value"><?= (int) $onlineStats['yesterday'] ?></span>
        <span class="admin-stat__label">Было онлайн вчера</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__value"><?= count($users) ?></span>
        <span class="admin-stat__label">Пользователей</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__value"><?= count(array_filter($users, static fn(array $item): bool => $item['lastLoginAt'] !== null)) ?></span>
        <span class="admin-stat__label">С активностью</span>
      </div>
    </section>

    <?php if ($analyticsStats !== null): ?>
    <section class="admin-analytics">
      <div class="admin-analytics__header">
        <h2 class="admin-analytics__title">Статистика действий</h2>
        <p class="admin-analytics__subtitle">За последние <?= (int) $analyticsStats['periodDays'] ?> дней · с <?= $formatDate($analyticsStats['since']) ?></p>
      </div>

      <div class="admin-analytics__grid">
        <div class="admin-card admin-analytics__card">
          <h3 class="admin-card__title">Самые частые нажатия</h3>
          <?php if ($analyticsStats['topClicks'] === []): ?>
          <p class="admin-analytics__empty">Пока нет данных о нажатиях.</p>
          <?php else: ?>
          <div class="admin-analytics-table-wrap">
            <table class="admin-analytics-table">
              <thead>
                <tr>
                  <th>Кнопка / элемент</th>
                  <th>Нажатий</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($analyticsStats['topClicks'] as $row): ?>
                <tr>
                  <td><code><?= htmlspecialchars((string) $row['target'], ENT_QUOTES, 'UTF-8') ?></code></td>
                  <td class="admin-analytics-table__num"><?= (int) $row['clicks'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <div class="admin-card admin-analytics__card">
          <h3 class="admin-card__title">Время на страницах</h3>
          <?php if ($analyticsStats['topPagesByTime'] === []): ?>
          <p class="admin-analytics__empty">Пока нет данных о времени на страницах.</p>
          <?php else: ?>
          <div class="admin-analytics-table-wrap">
            <table class="admin-analytics-table">
              <thead>
                <tr>
                  <th>Страница</th>
                  <th>Визиты</th>
                  <th>Всего</th>
                  <th>В среднем</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($analyticsStats['topPagesByTime'] as $row): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="admin-analytics-table__path"><?= htmlspecialchars((string) $row['path'], ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td class="admin-analytics-table__num"><?= (int) $row['visits'] ?></td>
                  <td class="admin-analytics-table__num"><?= htmlspecialchars($formatDuration((int) $row['totalSeconds']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-analytics-table__num"><?= htmlspecialchars($formatDuration((int) $row['avgSeconds']), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <div class="admin-card admin-analytics__card">
          <h3 class="admin-card__title">Поисковые запросы</h3>
          <?php if ($analyticsStats['topSearchQueries'] === []): ?>
          <p class="admin-analytics__empty">Пока нет данных о поисковых переходах.</p>
          <?php else: ?>
          <div class="admin-analytics-table-wrap">
            <table class="admin-analytics-table">
              <thead>
                <tr>
                  <th>Запрос</th>
                  <th>Источник</th>
                  <th>Визиты</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($analyticsStats['topSearchQueries'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string) $row['query'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars((string) $row['sourceLabel'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-analytics-table__num"><?= (int) $row['visits'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
          <p class="admin-analytics__note">Google часто не передаёт текст запроса из соображений приватности. Яндекс и UTM-метки обычно доступны.</p>
        </div>

        <div class="admin-card admin-analytics__card admin-analytics__card--wide">
          <h3 class="admin-card__title">Просмотры страниц</h3>
          <?php if ($analyticsStats['topPagesByViews'] === []): ?>
          <p class="admin-analytics__empty">Пока нет данных о просмотрах.</p>
          <?php else: ?>
          <div class="admin-analytics-table-wrap">
            <table class="admin-analytics-table">
              <thead>
                <tr>
                  <th>Страница</th>
                  <th>Просмотры</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($analyticsStats['topPagesByViews'] as $row): ?>
                <tr>
                  <td>
                    <strong><?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="admin-analytics-table__path"><?= htmlspecialchars((string) $row['path'], ENT_QUOTES, 'UTF-8') ?></span>
                  </td>
                  <td class="admin-analytics-table__num"><?= (int) $row['views'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($users === []): ?>
    <section class="admin-card">
      <p>Пока нет зарегистрированных пользователей.</p>
    </section>
    <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Пользователь</th>
            <th>Роли</th>
            <th>Регистрация</th>
            <th>Последний заход</th>
            <th>Путь новичка</th>
            <th>Уровни</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $item): ?>
          <?php
            $roadmap = $item['roadmap'];
            $rank = $roadmap['rank'];
            $currentStage = null;
            foreach ($roadmap['stages'] as $stage) {
              if ($stage['id'] === $roadmap['currentStageId']) {
                $currentStage = $stage;
                break;
              }
            }
          ?>
          <tr>
            <td class="admin-table__user">
              <strong><?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span class="admin-table__email"><?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="admin-table__meta">ID <?= (int) $item['id'] ?> · <?= (int) $item['sessionsCount'] ?> сессий · <?= (int) $item['practicedNotesCount'] ?> нот в статистике</span>
            </td>
            <td class="admin-table__roles" data-admin-roles-cell="<?= (int) $item['id'] ?>">
              <?php if (!empty($item['isTeacher'])): ?>
              <span class="admin-role-badge admin-role-badge--teacher">Педагог</span>
              <button type="button" class="btn btn--secondary btn--sm" data-admin-teacher-toggle data-user-id="<?= (int) $item['id'] ?>" data-teacher="0">Снять роль</button>
              <?php else: ?>
              <button type="button" class="btn btn--primary btn--sm" data-admin-teacher-toggle data-user-id="<?= (int) $item['id'] ?>" data-teacher="1">Назначить педагогом</button>
              <?php endif; ?>
              <?php if (in_array('student', $item['roles'] ?? [], true)): ?>
              <span class="admin-role-badge admin-role-badge--student">Ученик</span>
              <?php endif; ?>
            </td>
            <td><?= $formatDate($item['createdAt']) ?></td>
            <td><?= $formatDate($item['lastLoginAt']) ?></td>
            <td class="admin-table__roadmap">
              <span class="admin-rank"><?= htmlspecialchars((string) ($rank['emoji'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string) ($rank['title'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="admin-table__meta"><?= (int) $roadmap['totalXp'] ?> XP · <?= (int) $roadmap['completedCount'] ?>/<?= (int) $roadmap['totalStages'] ?> уровней по нотам</span>
              <?php if ($currentStage !== null): ?>
              <span class="admin-table__meta">Текущий: <?= htmlspecialchars((string) $currentStage['title'], ENT_QUOTES, 'UTF-8') ?> (<?= (int) $currentStage['progress'] ?>%)</span>
              <?php elseif ((int) $roadmap['completedCount'] === (int) $roadmap['totalStages']): ?>
              <span class="admin-table__meta">Все уровни по нотам пройдены</span>
              <?php else: ?>
              <span class="admin-table__meta">Ещё не начинал путь</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="admin-stages">
                <?php foreach ($roadmap['stages'] as $stage): ?>
                <?php
                  $class = 'admin-stage';
                  if ($stage['completed']) {
                    $class .= ' admin-stage--done';
                  } elseif ($stage['capstoneReady']) {
                    $class .= ' admin-stage--capstone';
                  } elseif (!$stage['unlocked']) {
                    $class .= ' admin-stage--locked';
                  } elseif ((int) $stage['progress'] > 0) {
                    $class .= ' admin-stage--progress';
                  }
                ?>
                <div class="<?= $class ?>" title="<?= htmlspecialchars((string) $stage['title'], ENT_QUOTES, 'UTF-8') ?>">
                  <span class="admin-stage__badge"><?= htmlspecialchars((string) $stage['badge'], ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="admin-stage__label"><?= htmlspecialchars($stageStatusLabel($stage), ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="admin-stage__notes"><?= (int) $stage['masteredNotes'] ?>/<?= (int) $stage['poolSize'] ?></span>
                </div>
                <?php endforeach; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="admin-footnote">
      «Готово» — все ноты уровня выучены (уровни без мелодии). «Мелодия» — ноты выучены, ждёт capstone (на сервере мелодии пока не сохраняются).
    </p>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (($isAdmin ?? false) && ($adminConfigured ?? false)): ?>
  <script type="module" src="/assets/js/admin.js"></script>
  <?php endif; ?>
</body>
</html>
