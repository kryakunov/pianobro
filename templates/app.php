<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Piano Bro — онлайн обучение игре на пианино с MIDI</title>
  <meta name="description" content="ПианоТренажёр — бесплатный онлайн-тренажёр для обучения игре на цифровом пианино. Подключите MIDI-клавиатуру, учите ноты до ре ми фа соль ля си, тренируйте мелодии с нотным станом и прослушиванием.">
  <meta name="keywords" content="обучение пианино, тренажёр пианино, цифровое пианино, MIDI клавиатура, ноты для начинающих, до ре ми, нотный стан, игра на пианино онлайн, Ludovico Einaudi, мелодии для пианино">
  <meta name="author" content="ПианоТренажёр">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#6c8cff">
  <?php
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
  ?>
  <link rel="canonical" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/">
  <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="apple-touch-icon" href="/assets/favicon.svg">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="ru_RU">
  <meta property="og:title" content="ПианоТренажёр — обучение игре на пианино онлайн">
  <meta property="og:description" content="Учитесь играть на цифровом пианино: MIDI, нотный стан, мелодии, тренажёр нот до ре ми. Бесплатно в браузере.">
  <meta property="og:site_name" content="ПианоТренажёр">
  <meta property="og:url" content="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="ПианоТренажёр — обучение игре на пианино">
  <meta name="twitter:description" content="Онлайн-тренажёр пианино с MIDI, нотным станом и мелодиями для начинающих и продвинутых.">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "ПианоТренажёр",
    "description": "Онлайн-тренажёр для обучения игре на цифровом пианино с поддержкой MIDI, нотного стана и мелодий.",
    "applicationCategory": "EducationalApplication",
    "operatingSystem": "Web",
    "inLanguage": "ru",
    "offers": {
      "@type": "Offer",
      "price": "0",
      "priceCurrency": "RUB"
    }
  }
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">

  <!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=110624464', 'ym');

    ym(110624464, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/110624464" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
</head>
<body>
  <div class="app" id="app">
    <header class="header" id="main-header">
      <div class="header__brand">
        <span class="header__logo icon-badge icon-badge--brand" aria-hidden="true">
          <svg class="icon icon--badge" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-piano"/></svg>
        </span>
        <div>
          <h1>Piano Bro</h1>
          <p class="header__subtitle">Обучение на пианино</p>
        </div>
      </div>
      <div class="header__auth" id="auth-panel">
        <button type="button" class="btn btn--secondary btn--sm" id="btn-open-auth">Войти</button>
        <div class="auth-user" id="auth-user" hidden>
          <span class="auth-user__name" id="auth-user-name"></span>
          <button type="button" class="btn btn--secondary btn--sm" id="btn-go-stats">Статистика</button>
          <button type="button" class="btn btn--secondary btn--sm" id="btn-logout" hidden>Выйти</button>
        </div>
      </div>
      <div class="header__midi" id="midi-panel">
        <span class="midi-dot midi-dot--off"></span>
        <div class="midi-panel__info">
          <span id="midi-status-text">MIDI не подключён</span>
          <span class="midi-panel__live" id="midi-live-note"></span>
        </div>
        <select class="midi-select" id="midi-device-select" disabled hidden>
          <option value="">Выбор устройства…</option>
        </select>
        <label class="midi-transpose" id="midi-transpose-wrap" hidden title="Если на пианино включена транспозиция, укажите её здесь">
          <span>Трансп.</span>
          <select id="midi-transpose">
            <option value="0">0</option>
            <option value="-12">-12</option>
            <option value="-11">-11</option>
            <option value="-10">-10</option>
            <option value="-9">-9</option>
            <option value="-8">-8</option>
            <option value="-7">-7</option>
            <option value="-6">-6</option>
            <option value="-5">-5</option>
            <option value="-4">-4</option>
            <option value="-3">-3</option>
            <option value="-2">-2</option>
            <option value="-1">-1</option>
            <option value="1">+1</option>
            <option value="2">+2</option>
            <option value="3">+3</option>
            <option value="4">+4</option>
            <option value="5">+5</option>
            <option value="6">+6</option>
            <option value="7">+7</option>
            <option value="8">+8</option>
            <option value="9">+9</option>
            <option value="10">+10</option>
            <option value="11">+11</option>
            <option value="12">+12</option>
          </select>
        </label>
        <button type="button" class="btn btn--secondary btn--sm" id="btn-connect-midi">
          <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-midi"/></svg>
          Подключить пианино
        </button>
        <button type="button" class="btn btn--secondary btn--sm" id="btn-connect-mic">
          <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-mic"/></svg>
          Микрофон
        </button>
      </div>
    </header>

    <!-- Главная -->
    <section class="screen screen--active" id="screen-home">
      <div class="home-grid">
        <button type="button" class="home-card home-card--melody" id="btn-go-melodies">
          <span class="home-card__icon icon-badge icon-badge--melody" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-melody"/></svg>
          </span>
          <span class="home-card__title">Мелодии</span>
          <span class="home-card__desc">Учите популярные песни и классику по нотному стану</span>
        </button>
        <button type="button" class="home-card home-card--notes" id="btn-go-notes">
          <span class="home-card__icon icon-badge icon-badge--notes" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
          </span>
          <span class="home-card__title">Тренажёр нот</span>
          <span class="home-card__desc">Запоминайте ноты на стане и находите их на клавиатуре</span>
        </button>
        <button type="button" class="home-card home-card--stats" id="btn-go-stats-home">
          <span class="home-card__icon icon-badge icon-badge--stats" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-stats"/></svg>
          </span>
          <span class="home-card__title">Моя статистика</span>
          <span class="home-card__desc">Какие ноты выучены хорошо, а какие стоит потренировать</span>
        </button>
      </div>
    </section>

    <!-- Статистика -->
    <section class="screen" id="screen-stats" hidden>
      <div class="screen-header">
        <button type="button" class="btn-back" id="btn-back-stats">← Назад</button>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--stats" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-stats"/></svg>
          </span>
          Моя статистика
        </h2>
      </div>
      <div class="pick-panel stats-panel" id="stats-panel">
        <p class="loading">Загрузка статистики…</p>
      </div>
    </section>

    <!-- Выбор мелодии -->
    <section class="screen" id="screen-melody-pick" hidden>
      <div class="screen-header">
        <button type="button" class="btn-back" id="btn-back-melody">← Назад</button>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--melody" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-melody"/></svg>
          </span>
          Выберите мелодию
        </h2>
      </div>
      <div class="pick-panel">
        <div class="melody-search">
          <span class="melody-search__icon" aria-hidden="true">
            <svg class="icon" viewBox="0 0 24 24"><use href="#ico-search"/></svg>
          </span>
          <input
            type="search"
            class="melody-search__input"
            id="melody-search"
            placeholder="Название песни…"
            autocomplete="off"
            aria-label="Поиск мелодии"
          >
          <button type="button" class="btn btn--secondary btn--sm melody-search__upload" id="btn-midi-upload" title="Загрузить MIDI-файл">
            <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-upload"/></svg>
            MIDI
          </button>
          <input type="file" id="midi-upload" accept=".mid,.midi" hidden>
        </div>
        <div class="difficulty-tabs" id="difficulty-tabs">
          <button type="button" class="difficulty-tab difficulty-tab--active" data-difficulty="all">Все</button>
          <button type="button" class="difficulty-tab" data-difficulty="beginner">Начальный</button>
          <button type="button" class="difficulty-tab" data-difficulty="intermediate">Средний</button>
          <button type="button" class="difficulty-tab" data-difficulty="advanced">Продвинутый</button>
        </div>
        <div class="lesson-list" id="lesson-list">
          <p class="loading">Загрузка уроков…</p>
        </div>
      </div>
    </section>

    <!-- Настройки тренажёра нот -->
    <section class="screen" id="screen-notes-pick" hidden>
      <div class="screen-header">
        <button type="button" class="btn-back" id="btn-back-notes">← Назад</button>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--notes" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
          </span>
          Настройки тренажёра
        </h2>
      </div>
      <div class="pick-panel">
        <div class="weak-notes-offer" id="weak-notes-offer" hidden></div>
        <div class="daily-goal" id="daily-goal-panel" hidden>
          <div class="daily-goal__ring" aria-hidden="true">
            <svg class="daily-goal__svg" viewBox="0 0 40 40">
              <circle class="daily-goal__track" cx="20" cy="20" r="16"></circle>
              <circle class="daily-goal__fill" id="daily-goal-ring" cx="20" cy="20" r="16"></circle>
            </svg>
            <span class="daily-goal__percent" id="daily-goal-percent">0%</span>
          </div>
          <div class="daily-goal__content">
            <strong class="daily-goal__title">Ежедневная цель</strong>
            <p class="daily-goal__text" id="daily-goal-text">Сегодня: 0 / 20 верных нот</p>
          </div>
        </div>
        <form class="notes-settings" id="notes-settings-form">
          <div class="notes-settings__grid">
          <fieldset class="settings-group settings-group--treble">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--notes"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-treble"/></svg></span>
              <span class="settings-group__title">Скрипичный ключ</span>
            </legend>
            <div class="settings-group__options">
              <label class="settings-check">
                <input type="checkbox" name="treble-first" checked>
                <span>Первая октава</span>
              </label>
              <label class="settings-check">
                <input type="checkbox" name="treble-second">
                <span>Вторая октава</span>
              </label>
            </div>
          </fieldset>

          <fieldset class="settings-group settings-group--bass">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--bass"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-bass"/></svg></span>
              <span class="settings-group__title">Басовый ключ</span>
            </legend>
            <div class="settings-group__options">
              <label class="settings-check">
                <input type="checkbox" name="bass-small">
                <span>Малая октава</span>
              </label>
              <label class="settings-check">
                <input type="checkbox" name="bass-great">
                <span>Большая октава</span>
              </label>
            </div>
          </fieldset>

          <fieldset class="settings-group settings-group--alt">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--accent"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-sharp"/></svg></span>
              <span class="settings-group__title">Знаки альтерации</span>
            </legend>
            <div class="settings-group__options">
              <label class="settings-check">
                <input type="checkbox" name="alt-sharp">
                <span class="settings-check__icon" aria-hidden="true">♯</span>
                <span>Диез</span>
              </label>
              <label class="settings-check">
                <input type="checkbox" name="alt-flat">
                <span class="settings-check__icon" aria-hidden="true">♭</span>
                <span>Бемоль</span>
              </label>
            </div>
          </fieldset>

          <fieldset class="settings-group settings-group--options">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--session"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-target"/></svg></span>
              <span class="settings-group__title">Режим тренировки</span>
            </legend>
            <div class="settings-group__options settings-group__options--stack">
              <label class="settings-check settings-check--wide">
                <input type="checkbox" name="sound-enabled" checked>
                <span>Звук нот</span>
              </label>
              <label class="settings-check settings-check--wide">
                <input type="checkbox" name="exam-mode">
                <span>Режим экзамена</span>
              </label>
            </div>
            <p class="settings-group__hint">Экзамен: без подсказок на клавиатуре, одна попытка на ноту.</p>
          </fieldset>

          <fieldset class="settings-group settings-group--daily">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--success"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-mastered"/></svg></span>
              <span class="settings-group__title">Ежедневная цель</span>
            </legend>
            <label class="settings-select">
              <span class="settings-select__label">Сколько верных нот в день</span>
              <select name="daily-goal" id="notes-daily-goal" class="settings-select__input">
                <option value="10">10 нот</option>
                <option value="20" selected>20 нот</option>
                <option value="30">30 нот</option>
                <option value="50">50 нот</option>
              </select>
            </label>
          </fieldset>

          <fieldset class="settings-group settings-group--session">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--session"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-session"/></svg></span>
              <span class="settings-group__title">Длина сессии</span>
            </legend>
            <label class="settings-select">
              <span class="settings-select__label">Сколько нот тренировать</span>
              <select name="session-limit" id="notes-session-limit" class="settings-select__input">
                <option value="10" selected>10 нот</option>
                <option value="20">20 нот</option>
                <option value="30">30 нот</option>
                <option value="50">50 нот</option>
              </select>
            </label>
          </fieldset>
          </div>

          <div class="notes-settings__footer">
            <p class="settings-hint">Отметьте, что хотите тренировать, и нажмите «Начать».</p>
            <p class="settings-error" id="notes-settings-error" hidden></p>
            <button type="submit" class="btn btn--primary notes-settings__submit" id="btn-start-notes">
              <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-play"/></svg>
              Начать тренировку
            </button>
          </div>
        </form>
      </div>
    </section>

    <!-- Тренировка -->
    <section class="screen screen--practice" id="screen-practice" hidden>
      <div class="practice-topbar">
        <button type="button" class="btn-back" id="btn-back-practice" aria-label="Назад">←</button>
        <h2 class="practice-topbar__title" id="practice-title">Тренировка</h2>
        <span class="practice-mode-badge" id="practice-mode-badge" hidden>Экзамен</span>
        <div class="practice-daily-goal" id="practice-daily-goal" hidden>
          <span class="practice-daily-goal__label">Цель</span>
          <span class="practice-daily-goal__value" id="practice-daily-goal-text">0 / 20</span>
        </div>
        <div class="practice-progress" id="practice-progress">0 / 10</div>
      </div>

      <div
        class="practice-session-progress"
        id="practice-session-progress"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="10"
        aria-valuenow="0"
        aria-label="Прогресс тренировки"
      >
        <div class="practice-session-progress__fill" id="practice-session-progress-fill"></div>
      </div>

      <div class="practice-input-status practice-input-status--off" id="practice-input-status">
        <span class="practice-input-status__dot" id="practice-input-dot" aria-hidden="true"></span>
        <span class="practice-input-status__text" id="practice-input-status-text">Пианино не подключено</span>
        <button type="button" class="practice-input-status__btn" id="btn-practice-connect-midi">Подключить MIDI</button>
      </div>

      <div class="practice-feedback-wrap">
        <div class="practice-feedback" id="practice-feedback" aria-live="polite"></div>
      </div>

      <div class="practice-controls" id="practice-controls" hidden>
        <div class="practice-controls__row">
          <div class="keyboard-mode" id="keyboard-hints-panel" hidden>
            <span class="keyboard-mode__label">Подсказки</span>
            <div class="keyboard-mode__tabs" role="group" aria-label="Режим подсказок на клавиатуре">
              <button type="button" class="keyboard-mode__tab keyboard-mode__tab--active" data-hints="on">С подсказками</button>
              <button type="button" class="keyboard-mode__tab" data-hints="off">Без подсказок</button>
            </div>
          </div>

          <div class="keyboard-mode" id="sound-mode-panel">
            <span class="keyboard-mode__label">Звук</span>
            <div class="keyboard-mode__tabs" role="group" aria-label="Звук клавиш">
              <button type="button" class="keyboard-mode__tab keyboard-mode__tab--active" data-sound="on">Включён</button>
              <button type="button" class="keyboard-mode__tab" data-sound="off">Выключен</button>
            </div>
          </div>
        </div>
      </div>

      <div class="practice-layout practice-layout--keyboard-hidden">
        <div class="practice-staff staff-viewport" id="staff-viewport">
          <div class="staff-scroll">
            <svg class="staff-svg" id="staff-svg" role="img" aria-label="Нотный стан"></svg>
          </div>
        </div>

        <div class="practice-keyboard-area practice-keyboard-area--hidden" id="practice-keyboard-area" hidden>
          <div class="practice-keyboard" id="piano-wrap">
            <div class="practice-keyboard__viewport" id="piano-viewport">
              <div class="piano-case">
                <div class="piano-case__octaves-host" id="piano-octaves-host"></div>
                <div class="piano-case__lid"></div>
                <div class="piano-case__keys">
                  <div class="piano" id="piano" role="application" aria-label="Клавиатура пианино 88 клавиш"></div>
                </div>
                <div class="piano-case__board"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="practice-keyboard-footer">
          <div class="practice-keyboard-spoiler">
            <button type="button" class="practice-spoiler" id="btn-toggle-piano" aria-expanded="false">
              <span class="practice-spoiler__keys icon-badge icon-badge--keyboard" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-keyboard"/></svg>
              </span>
              <span class="practice-spoiler__text">
                <span class="practice-spoiler__label">Показать клавиатуру</span>
                <span class="practice-spoiler__hint">Нажмите, чтобы открыть клавиши пианино</span>
              </span>
              <span class="practice-spoiler__icon" aria-hidden="true">▸</span>
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Авторизация -->
    <div class="modal" id="auth-modal" hidden>
      <div class="modal__backdrop" data-close-auth></div>
      <div class="modal__card modal__card--auth" role="dialog" aria-labelledby="auth-modal-title">
        <h2 class="modal__title" id="auth-modal-title">Вход</h2>
        <div class="auth-tabs" role="tablist" aria-label="Авторизация">
          <button type="button" class="auth-tab auth-tab--active" data-auth-tab="login" role="tab" aria-selected="true" aria-controls="auth-form-login">Вход</button>
          <button type="button" class="auth-tab" data-auth-tab="register" role="tab" aria-selected="false" aria-controls="auth-form-register">Регистрация</button>
        </div>
        <form class="auth-form" id="auth-form-login" data-auth-form="login" role="tabpanel" aria-labelledby="auth-tab-login">
          <label class="auth-field">
            <span class="auth-field__label">Email</span>
            <input type="email" name="email" required autocomplete="email">
          </label>
          <label class="auth-field">
            <span class="auth-field__label">Пароль</span>
            <input type="password" name="password" required autocomplete="current-password" minlength="6">
          </label>
          <p class="auth-error" id="auth-error-login" hidden></p>
          <button type="submit" class="btn btn--primary auth-form__submit">Войти</button>
        </form>
        <form class="auth-form" id="auth-form-register" data-auth-form="register" role="tabpanel" aria-labelledby="auth-tab-register" hidden>
          <div class="auth-honeypot" aria-hidden="true">
            <label class="auth-field">
              <span class="auth-field__label">Компания</span>
              <input type="text" name="website" tabindex="-1" autocomplete="off">
            </label>
          </div>
          <label class="auth-field">
            <span class="auth-field__label">Имя</span>
            <input type="text" name="name" required autocomplete="name" minlength="2">
          </label>
          <label class="auth-field">
            <span class="auth-field__label">Email</span>
            <input type="email" name="email" required autocomplete="email">
          </label>
          <label class="auth-field">
            <span class="auth-field__label">Пароль</span>
            <input type="password" name="password" required autocomplete="new-password" minlength="6">
          </label>
          <label class="auth-field">
            <span class="auth-field__label">Подтверждение пароля</span>
            <input type="password" name="password_confirm" required autocomplete="new-password" minlength="6">
          </label>
          <p class="auth-error" id="auth-error-register" hidden></p>
          <button type="submit" class="btn btn--primary auth-form__submit">Зарегистрироваться</button>
        </form>
      </div>
    </div>

    <!-- Итоги тренировки -->
    <div class="modal" id="session-modal" hidden>
      <div class="modal__backdrop"></div>
      <div class="modal__card" role="dialog" aria-labelledby="modal-title">
        <div class="modal__icon icon-badge icon-badge--success" aria-hidden="true">
          <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
        </div>
        <h2 class="modal__title" id="modal-title">Тренировка завершена!</h2>
        <p class="modal__subtitle" id="modal-subtitle">Тренировка завершена</p>
        <div class="modal__stats">
          <div class="modal-stat">
            <span class="modal-stat__label">Верно</span>
            <span class="modal-stat__value modal-stat__value--success" id="modal-correct">0</span>
          </div>
          <div class="modal-stat">
            <span class="modal-stat__label">Ошибки</span>
            <span class="modal-stat__value modal-stat__value--error" id="modal-wrong">0</span>
          </div>
          <div class="modal-stat">
            <span class="modal-stat__label">Точность</span>
            <span class="modal-stat__value" id="modal-accuracy">—</span>
          </div>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn btn--primary" id="btn-modal-retry">Ещё раз</button>
          <button type="button" class="btn btn--secondary" id="btn-modal-pick">Другой урок</button>
          <button type="button" class="btn btn--secondary" id="btn-modal-home">На главную</button>
        </div>
      </div>
    </div>
  </div>

  <svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
    <symbol id="ico-piano" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 10h16v10H4z"/><path d="M7 10V8a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><path d="M8 14h2v4H8zM11 14h2v4h-2zM14 14h2v4h-2zM17 14h2v4h-2z"/><path d="M9.5 10v4M12.5 10v4M15.5 10v4" stroke-width="2"/>
    </symbol>
    <symbol id="ico-melody" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2.5" fill="currentColor" stroke="none"/><circle cx="17" cy="16" r="2.5" fill="currentColor" stroke="none"/><path d="M4 20h20" opacity="0.35"/>
    </symbol>
    <symbol id="ico-treble" viewBox="0 0 24 24">
      <path d="M2 7.5h20M2 10h20M2 12.5h20M2 15h20M2 17.5h20" stroke="currentColor" stroke-width="0.65" opacity="0.3"/>
      <text x="12" y="16.8" text-anchor="middle" font-size="18" font-family="Georgia, 'Times New Roman', 'Noto Music', serif" fill="currentColor">𝄞</text>
    </symbol>
    <symbol id="ico-bass" viewBox="0 0 24 24">
      <path d="M2 7.5h20M2 10h20M2 12.5h20M2 15h20M2 17.5h20" stroke="currentColor" stroke-width="0.65" opacity="0.3"/>
      <text x="12" y="16.2" text-anchor="middle" font-size="17" font-family="Georgia, 'Times New Roman', 'Noto Music', serif" fill="currentColor">𝄢</text>
    </symbol>
    <symbol id="ico-notes" viewBox="0 0 24 24">
      <path d="M2 8h10M2 10.5h10M2 13h10M2 15.5h10M2 18h10" stroke="currentColor" stroke-width="0.6" opacity="0.28"/>
      <ellipse cx="6.5" cy="15.5" rx="1.7" ry="1.3" fill="currentColor"/>
      <path d="M8.2 15.5V9.5" stroke="currentColor" stroke-width="1.15" stroke-linecap="round"/>
      <path d="M11.5 12.5h2.8M13.4 11.4l1.8 1.1-1.8 1.1" fill="none" stroke="currentColor" stroke-width="1.05" stroke-linecap="round" stroke-linejoin="round" opacity="0.75"/>
      <rect x="16.2" y="10.2" width="2.4" height="5.8" rx="0.55" fill="currentColor" opacity="0.22"/>
      <rect x="19.1" y="10.2" width="2.4" height="5.8" rx="0.55" fill="currentColor" opacity="0.22"/>
      <rect x="17.35" y="10.2" width="1.55" height="3.4" rx="0.35" fill="currentColor" opacity="0.55"/>
      <rect x="19.1" y="11.6" width="2.4" height="4.4" rx="0.55" fill="currentColor"/>
    </symbol>
    <symbol id="ico-stats" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 20V10M10 20V4M16 20v-7M22 20V7"/>
    </symbol>
    <symbol id="ico-sharp" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 4v16M15 4v16M6 8h12M6 16h12"/>
    </symbol>
    <symbol id="ico-flat" viewBox="0 0 24 24">
      <path d="M14 4v16M14 4c-3 0-5 1.5-5 4s2 4 5 4v-8z" fill="currentColor"/>
    </symbol>
    <symbol id="ico-session" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/>
    </symbol>
    <symbol id="ico-sessions" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h10M7 13h6"/>
    </symbol>
    <symbol id="ico-mastered" viewBox="0 0 24 24">
      <path d="M12 3l2.4 4.8 5.3.8-3.8 3.7 1 5.3L12 15.8 7.1 17.6l1-5.3L4.3 8.6l5.3-.8L12 3z" fill="currentColor"/>
    </symbol>
    <symbol id="ico-practice" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
    </symbol>
    <symbol id="ico-learning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 19V5l8-2 8 2v14"/><path d="M12 3v16M4 9h16"/>
    </symbol>
    <symbol id="ico-warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 3 2 21h20L12 3z"/><path d="M12 10v4M12 17h.01"/>
    </symbol>
    <symbol id="ico-keyboard" viewBox="0 0 24 24">
      <rect x="2" y="8" width="20" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.75"/><path d="M5 12h2v4H5zM9 12h2v4H9zM13 12h2v4h-2zM17 12h2v4h-2zM7 12h2v3H7zM11 12h2v3h-2zM15 12h2v3h-2z" fill="currentColor"/>
    </symbol>
    <symbol id="ico-chart" viewBox="0 0 24 24">
      <path d="M3 20h18" fill="none" stroke="currentColor" stroke-width="1.75"/><rect x="5" y="12" width="3" height="8" rx="1" fill="currentColor"/><rect x="10.5" y="8" width="3" height="12" rx="1" fill="currentColor" opacity="0.65"/><rect x="16" y="5" width="3" height="15" rx="1" fill="currentColor" opacity="0.4"/>
    </symbol>
    <symbol id="ico-target" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/>
    </symbol>
    <symbol id="ico-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="11" cy="11" r="6"/><path d="M16 16l4 4"/>
    </symbol>
    <symbol id="ico-upload" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 16V6M8 10l4-4 4 4"/><path d="M4 18h16"/>
    </symbol>
    <symbol id="ico-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-7 8-7s8 3 8 7"/>
    </symbol>
    <symbol id="ico-midi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M5 5h14v14H5z"/><path d="M9 9v6M15 9v6M12 7v10"/>
    </symbol>
    <symbol id="ico-play" viewBox="0 0 24 24">
      <polygon points="8,5 20,12 8,19" fill="currentColor"/>
    </symbol>
    <symbol id="ico-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
      <path d="M5 12l4 4 10-10"/>
    </symbol>
    <symbol id="ico-mic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3z"/><path d="M6 11a6 6 0 0 0 12 0"/><path d="M12 17v3"/>
    </symbol>
    <symbol id="ico-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M5 12h14M13 6l6 6-6 6"/>
    </symbol>
  </svg>

  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
