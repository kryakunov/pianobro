<?php

declare(strict_types=1);

/** @var array{id:int,email:string,name:string}|null $user */
/** @var bool $isAdmin */
/** @var bool $adminConfigured */
/** @var list<array<string, mixed>> $users */
/** @var int $assetVersion */
/** @var array{today:int,yesterday:int} $onlineStats */
/** @var array{totalBuyers:int,today:int,yesterday:int} $purchaseStats */
/** @var list<array<string, mixed>> $plans */
/** @var string $adminCsrf */

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
  <?php if (($isAdmin ?? false) && ($adminCsrf ?? '') !== ''): ?>
  <meta name="admin-csrf" content="<?= htmlspecialchars($adminCsrf, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(\PianoTrainer\AssetVersion::versionedUrl('/assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="admin-page">
  <div class="admin">
    <header class="admin__header">
      <div>
        <h1 class="admin__title">Админ-панель</h1>
        <p class="admin__subtitle">Пользователи, прогресс и оплаты</p>
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
      <div class="admin-stat">
        <span class="admin-stat__value"><?= (int) $purchaseStats['totalBuyers'] ?></span>
        <span class="admin-stat__label">Купили тариф</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__value"><?= (int) $purchaseStats['today'] ?></span>
        <span class="admin-stat__label">Оплат сегодня</span>
      </div>
      <div class="admin-stat">
        <span class="admin-stat__value"><?= (int) $purchaseStats['yesterday'] ?></span>
        <span class="admin-stat__label">Оплат вчера</span>
      </div>
    </section>

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
            <th>Тариф</th>
            <th>Регистрация</th>
            <th>Последний заход</th>
            <th>Путь новичка</th>
            <th>Уровни</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $item): ?>
          <?php
            $roadmap = $item['roadmap'];
            $rank = $roadmap['rank'];
            $sub = $item['subscription'];
            $currentStage = null;
            foreach ($roadmap['stages'] as $stage) {
              if ($stage['id'] === $roadmap['currentStageId']) {
                $currentStage = $stage;
                break;
              }
            }
          ?>
          <tr
            class="<?= !empty($sub['hasPurchased']) ? 'admin-table__row--buyer' : '' ?>"
            data-admin-user-row
            data-user-id="<?= (int) $item['id'] ?>"
            data-user-name="<?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?>"
            data-user-email="<?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?>"
            data-is-teacher="<?= !empty($item['isTeacher']) ? '1' : '0' ?>"
            data-is-student="<?= in_array('student', $item['roles'] ?? [], true) ? '1' : '0' ?>"
            data-sub-is-premium="<?= !empty($sub['isPremium']) ? '1' : '0' ?>"
            data-sub-has-purchased="<?= !empty($sub['hasPurchased']) ? '1' : '0' ?>"
            data-sub-plan-id="<?= htmlspecialchars((string) ($sub['planId'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            data-sub-plan-name="<?= htmlspecialchars((string) $sub['planName'], ENT_QUOTES, 'UTF-8') ?>"
            data-sub-expires-at="<?= htmlspecialchars((string) ($sub['expiresAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
            data-sub-payments-count="<?= (int) ($sub['paymentsCount'] ?? 0) ?>"
          >
            <td class="admin-table__user">
              <strong><?= htmlspecialchars((string) $item['name'], ENT_QUOTES, 'UTF-8') ?></strong>
              <span class="admin-table__email"><?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="admin-table__meta">ID <?= (int) $item['id'] ?> · <?= (int) $item['sessionsCount'] ?> сессий · <?= (int) $item['practicedNotesCount'] ?> нот в статистике</span>
            </td>
            <td class="admin-table__roles" data-admin-roles-cell="<?= (int) $item['id'] ?>">
              <?php if (!empty($item['isTeacher'])): ?>
              <span class="admin-role-badge admin-role-badge--teacher">Педагог</span>
              <?php endif; ?>
              <?php if (in_array('student', $item['roles'] ?? [], true)): ?>
              <span class="admin-role-badge admin-role-badge--student">Ученик</span>
              <?php endif; ?>
              <?php if (empty($item['isTeacher']) && !in_array('student', $item['roles'] ?? [], true)): ?>
              <span class="admin-table__meta admin-table__meta--muted">—</span>
              <?php endif; ?>
            </td>
            <td class="admin-table__subscription" data-admin-subscription-cell="<?= (int) $item['id'] ?>">
              <?php if (!empty($sub['isPremium'])): ?>
              <span class="admin-role-badge admin-role-badge--premium">Premium</span>
              <span class="admin-table__meta"><?= htmlspecialchars((string) $sub['planName'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($sub['expiresAt'] !== null): ?>
              <span class="admin-table__meta">до <?= $formatDate($sub['expiresAt']) ?></span>
              <?php endif; ?>
              <?php elseif (!empty($sub['hasPurchased'])): ?>
              <span class="admin-role-badge admin-role-badge--buyer">Покупал</span>
              <span class="admin-table__meta"><?= htmlspecialchars((string) $sub['planName'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($sub['expiresAt'] !== null): ?>
              <span class="admin-table__meta admin-table__meta--muted">истёк <?= $formatDate($sub['expiresAt']) ?></span>
              <?php endif; ?>
              <?php if ((int) $sub['paymentsCount'] > 1): ?>
              <span class="admin-table__meta admin-table__meta--muted"><?= (int) $sub['paymentsCount'] ?> оплат</span>
              <?php endif; ?>
              <?php else: ?>
              <span class="admin-table__meta admin-table__meta--muted">Бесплатный</span>
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
            <td class="admin-table__manage">
              <button
                type="button"
                class="btn btn--secondary btn--sm admin-table__manage-btn"
                data-admin-open-manage
                data-user-id="<?= (int) $item['id'] ?>"
              >Управление</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="admin-footnote">
      «Готово» — уровень полностью пройден (ноты и закрепляющая мелодия). «Мелодия» — ноты выучены, ждёт capstone. Ранг и XP считаются так же, как в «Пути новичка».
    </p>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (($isAdmin ?? false) && ($adminConfigured ?? false)): ?>
    <div class="admin-modal" id="admin-user-modal" hidden aria-hidden="true">
      <div class="admin-modal__backdrop" data-admin-modal-close></div>
      <div class="admin-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="admin-modal-title">
        <header class="admin-modal__header">
          <div>
            <h2 class="admin-modal__title" id="admin-modal-title">Управление пользователем</h2>
            <p class="admin-modal__subtitle" id="admin-modal-user-label"></p>
          </div>
          <button type="button" class="admin-modal__close" data-admin-modal-close aria-label="Закрыть">&times;</button>
        </header>

        <div class="admin-modal__body">
          <section class="admin-modal__section">
            <h3 class="admin-modal__section-title">Роли</h3>
            <div class="admin-modal__status" id="admin-modal-roles-status"></div>
            <button type="button" class="btn btn--secondary btn--sm" id="admin-modal-teacher-btn" hidden></button>
          </section>

          <section class="admin-modal__section">
            <h3 class="admin-modal__section-title">Тариф</h3>
            <div class="admin-modal__status" id="admin-modal-subscription-status"></div>
            <label class="admin-modal__field">
              <span class="admin-modal__field-label">Назначить тариф</span>
              <select class="admin-modal__select" id="admin-modal-plan-select">
                <option value="">— выберите —</option>
                <?php foreach ($plans as $plan): ?>
                <option value="<?= htmlspecialchars((string) $plan['id'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string) ($plan['shortName'] ?? $plan['name']), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="admin-modal__actions">
              <button type="button" class="btn btn--primary btn--sm" id="admin-modal-grant-btn">Назначить тариф</button>
              <button type="button" class="btn btn--secondary btn--sm" id="admin-modal-revoke-btn" hidden>Снять тариф</button>
            </div>
          </section>

          <section class="admin-modal__section admin-modal__section--danger">
            <h3 class="admin-modal__section-title">Опасная зона</h3>
            <p class="admin-modal__hint">Удаление необратимо: прогресс, статистика и история оплат будут стёрты.</p>
            <button type="button" class="btn btn--danger btn--sm" id="admin-modal-delete-btn">Удалить пользователя</button>
          </section>
        </div>
      </div>
    </div>

    <div class="admin-confirm" id="admin-confirm" hidden aria-hidden="true">
      <div class="admin-confirm__backdrop" data-admin-confirm-cancel></div>
      <div class="admin-confirm__dialog" role="alertdialog" aria-modal="true" aria-labelledby="admin-confirm-title">
        <h3 class="admin-confirm__title" id="admin-confirm-title">Подтвердите действие</h3>
        <p class="admin-confirm__text" id="admin-confirm-text"></p>
        <div class="admin-confirm__actions">
          <button type="button" class="btn btn--secondary btn--sm" data-admin-confirm-cancel>Отмена</button>
          <button type="button" class="btn btn--danger btn--sm" id="admin-confirm-ok">Подтвердить</button>
        </div>
      </div>
    </div>

    <div class="admin-toast" id="admin-toast" hidden role="status"></div>
    <?php endif; ?>
  </div>

  <?php if (($isAdmin ?? false) && ($adminConfigured ?? false)): ?>
  <script type="application/json" id="admin-plans-data"><?= json_encode(array_map(
    static fn(array $plan): array => [
      'id' => (string) ($plan['id'] ?? ''),
      'shortName' => (string) ($plan['shortName'] ?? $plan['name'] ?? ''),
    ],
    $plans ?? [],
  ), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <?php require __DIR__ . '/partials/js-import-map.php'; ?>
  <script type="module" src="<?= htmlspecialchars(\PianoTrainer\AssetVersion::versionedUrl('/assets/js/admin.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
  <?php endif; ?>
</body>
</html>
