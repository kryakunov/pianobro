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
        <span class="header__logo">♪</span>
        <div>
          <h1>Piano Bro</h1>
          <p class="header__subtitle">Онлайн-обучение игре на цифровом пианино</p>
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
        <button type="button" class="btn btn--secondary btn--sm" id="btn-connect-midi">Подключить пианино</button>
        <button type="button" class="btn btn--secondary btn--sm" id="btn-connect-mic">Микрофон</button>
      </div>
    </header>

    <!-- Главная -->
    <section class="screen screen--active" id="screen-home">
      <div class="home-grid">
        <button type="button" class="home-card" id="btn-go-melodies">
          <span class="home-card__icon" aria-hidden="true">🎵</span>
          <span class="home-card__title">Мелодии</span>
          <span class="home-card__desc">Учите популярные песни и классику по нотному стану</span>
        </button>
        <button type="button" class="home-card" id="btn-go-notes">
          <span class="home-card__icon" aria-hidden="true">𝄞</span>
          <span class="home-card__title">Тренажёр нот</span>
          <span class="home-card__desc">Запоминайте ноты на стане и находите их на клавиатуре</span>
        </button>
      </div>
    </section>

    <!-- Выбор мелодии -->
    <section class="screen" id="screen-melody-pick" hidden>
      <div class="screen-header">
        <button type="button" class="btn-back" id="btn-back-melody">← Назад</button>
        <h2 class="screen-header__title">Выберите мелодию</h2>
      </div>
      <div class="pick-panel">
        <div class="melody-search">
          <input
            type="search"
            class="melody-search__input"
            id="melody-search"
            placeholder="Название песни…"
            autocomplete="off"
            aria-label="Поиск мелодии"
          >
          <button type="button" class="btn btn--secondary btn--sm melody-search__upload" id="btn-midi-upload" title="Загрузить MIDI-файл">
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
        <h2 class="screen-header__title">Настройки тренажёра</h2>
      </div>
      <div class="pick-panel">
        <form class="notes-settings" id="notes-settings-form">
          <fieldset class="settings-group">
            <legend class="settings-group__title">Скрипичный ключ</legend>
            <div class="settings-group__options">
              <label class="settings-check">
                <input type="checkbox" name="treble-second">
                <span>Вторая октава</span>
              </label>
              <label class="settings-check">
                <input type="checkbox" name="treble-first" checked>
                <span>Первая октава</span>
              </label>
            </div>
          </fieldset>

          <fieldset class="settings-group">
            <legend class="settings-group__title">Басовый ключ</legend>
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

          <fieldset class="settings-group">
            <legend class="settings-group__title">Знаки альтерации</legend>
            <div class="settings-group__options">
              <label class="settings-check">
                <input type="checkbox" name="alt-sharp">
                <span>Диез</span>
              </label>
              <label class="settings-check">
                <input type="checkbox" name="alt-flat">
                <span>Бемоль</span>
              </label>
            </div>
          </fieldset>

          <fieldset class="settings-group">
            <legend class="settings-group__title">Тональности</legend>
            <div class="settings-group__options">
              <label class="settings-check">
                <input type="checkbox" name="tonal-sharp">
                <span>Диезные</span>
              </label>
              <label class="settings-check">
                <input type="checkbox" name="tonal-flat">
                <span>Бемольные</span>
              </label>
            </div>
          </fieldset>

          <p class="settings-hint">Сессия — 10 нот. Отметьте, что хотите тренировать, и нажмите «Начать».</p>
          <p class="settings-error" id="notes-settings-error" hidden></p>
          <button type="submit" class="btn btn--primary notes-settings__submit" id="btn-start-notes">Начать тренировку</button>
        </form>
      </div>
    </section>

    <!-- Тренировка -->
    <section class="screen screen--practice" id="screen-practice" hidden>
      <div class="practice-topbar">
        <button type="button" class="btn-back" id="btn-back-practice" aria-label="Назад">←</button>
        <h2 class="practice-topbar__title" id="practice-title">Тренировка</h2>
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

      <div class="practice-feedback" id="practice-feedback" aria-live="polite"></div>

      <div class="practice-layout practice-layout--keyboard-hidden">
        <div class="practice-staff staff-viewport" id="staff-viewport">
          <div class="staff-scroll">
            <svg class="staff-svg" id="staff-svg" role="img" aria-label="Нотный стан"></svg>
          </div>
        </div>

        <div class="practice-keyboard-spoiler">
          <button type="button" class="practice-spoiler" id="btn-toggle-piano" aria-expanded="false">
            <span class="practice-spoiler__keys" aria-hidden="true">🎹</span>
            <span class="practice-spoiler__text">
              <span class="practice-spoiler__label">Показать клавиатуру</span>
              <span class="practice-spoiler__hint">Нажмите, чтобы открыть клавиши пианино</span>
            </span>
            <span class="practice-spoiler__icon" aria-hidden="true">▸</span>
          </button>
        </div>

        <div class="practice-keyboard-area practice-keyboard-area--hidden" id="practice-keyboard-area" hidden>
          <div class="keyboard-mode" id="keyboard-hints-panel">
            <span class="keyboard-mode__label">Подсказки</span>
            <div class="keyboard-mode__tabs" role="group" aria-label="Режим подсказок на клавиатуре">
              <button type="button" class="keyboard-mode__tab keyboard-mode__tab--active" data-hints="on">С подсказками</button>
              <button type="button" class="keyboard-mode__tab" data-hints="off">Без подсказок</button>
            </div>
          </div>

          <div class="practice-keyboard piano-wrap" id="piano-wrap">
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
    </section>

    <!-- Итоги тренировки -->
    <div class="modal" id="session-modal" hidden>
      <div class="modal__backdrop"></div>
      <div class="modal__card" role="dialog" aria-labelledby="modal-title">
        <div class="modal__icon" aria-hidden="true">✓</div>
        <h2 class="modal__title" id="modal-title">Тренировка завершена!</h2>
        <p class="modal__subtitle">Вы прошли 10 нот</p>
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

  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
