<?php

declare(strict_types=1);

/** @var array{id:int,email:string,name:string,role?:string}|null $user */
/** @var bool $isTeacher */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Кабинет преподавателя | Piano Bro</title>
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="admin-page teacher-page">
  <div class="admin teacher">
    <header class="admin__header">
      <div>
        <h1 class="admin__title">Кабинет преподавателя</h1>
        <p class="admin__subtitle">Ученики, статистика и индивидуальные задания</p>
      </div>
      <div class="admin__header-actions">
        <a href="/" class="btn btn--secondary btn--sm">На сайт</a>
        <?php if ($user !== null): ?>
        <span class="admin__user"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>
    </header>

    <?php if ($user === null): ?>
    <section class="admin-card">
      <h2 class="admin-card__title">Нужен вход</h2>
      <p>Войдите в аккаунт преподавателя на главной странице, затем вернитесь сюда.</p>
      <a href="/" class="btn btn--primary">Перейти на сайт</a>
    </section>
    <?php elseif (!$isTeacher): ?>
    <section class="admin-card admin-card--warn">
      <h2 class="admin-card__title">Нет доступа</h2>
      <p>У аккаунта <strong><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong> нет роли преподавателя.</p>
      <p class="admin-footnote">При регистрации отметьте «Вы педагог?» или попросите администратора назначить роль в <a href="/admin">админ-панели</a>.</p>
    </section>
    <?php else: ?>
    <div id="teacher-app" class="teacher-layout">
      <aside class="teacher-sidebar">
        <section class="admin-card teacher-invite-card">
          <h2 class="admin-card__title">Пригласить ученика</h2>
          <form id="form-invite-student" class="teacher-form">
            <label class="teacher-form__field teacher-form__field--wide">
              <span>Email ученика</span>
              <input type="email" name="email" required placeholder="student@example.com" autocomplete="email">
            </label>
            <button type="submit" class="btn btn--primary">Отправить приглашение</button>
          </form>
          <p class="admin-footnote" id="invite-message" hidden></p>
          <div id="pending-invites" class="teacher-pending-invites"></div>
        </section>

        <section class="admin-card teacher-students-card">
          <h2 class="admin-card__title">Мои ученики</h2>
          <div id="teacher-students-list" class="teacher-students-list">
            <p class="loading">Загрузка…</p>
          </div>
        </section>
      </aside>

      <main class="teacher-main" id="teacher-main">
        <section class="admin-card teacher-empty-state">
          <h2 class="admin-card__title">Выберите ученика</h2>
          <p>Отправьте приглашение на email или выберите ученика из списка слева, чтобы увидеть статистику по нотам и назначить задание.</p>
        </section>
      </main>
    </div>

    <div class="modal" id="teacher-assignment-modal" hidden>
      <div class="modal__backdrop" data-close-assignment-modal></div>
      <div class="modal__card modal__card--teacher-assignment" role="dialog" aria-labelledby="teacher-assignment-modal-title" aria-modal="true">
        <header class="teacher-assignment-modal__header">
          <div>
            <h2 class="teacher-assignment-modal__title" id="teacher-assignment-modal-title">Назначить тренировку</h2>
            <p class="teacher-assignment-modal__subtitle" id="teacher-assignment-modal-subtitle"></p>
          </div>
          <button type="button" class="btn btn--secondary btn--sm" data-close-assignment-modal aria-label="Закрыть">✕</button>
        </header>
        <div id="teacher-assignment-modal-body" class="teacher-assignment-modal__body"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($isTeacher ?? false): ?>
  <script type="module" src="/assets/js/teacher.js"></script>
  <?php endif; ?>
</body>
</html>
