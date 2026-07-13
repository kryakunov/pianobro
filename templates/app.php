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
  <div class="app">
    <header class="header">
      <div class="header__brand">
        <span class="header__logo">♪</span>
        <div>
          <h1>Piano Bro</h1>
          <p class="header__subtitle">Онлайн-обучение игре на цифровом пианино с MIDI</p>
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
      </div>
    </header>

    <main class="main">
      <aside class="sidebar">
        <div class="mode-tabs">
          <button type="button" class="mode-tab mode-tab--active" data-mode="melody">Мелодии</button>
          <button type="button" class="mode-tab" data-mode="notes">Тренажёр нот</button>
        </div>

        <div id="sidebar-melody">
          <h2>Мелодии</h2>
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
          <div class="lesson-list" id="lesson-list">
            <p class="loading">Загрузка уроков…</p>
          </div>
        </div>

        <div id="sidebar-notes" hidden>
          <h2>Уровень</h2>
          <div class="level-list" id="level-list"></div>
        </div>
      </aside>

      <section class="workspace">
        <div class="score-panel">
          <div class="score-panel__info">
            <h2 id="lesson-title">Выберите урок</h2>
            <p id="lesson-meta" class="lesson-meta"></p>
          </div>
          <div class="score-panel__stats">
            <div class="stat">
              <span class="stat__label" id="stat-progress-label">Прогресс</span>
              <span class="stat__value" id="stat-progress">0 / 0</span>
            </div>
            <div class="stat">
              <span class="stat__label">Верно</span>
              <span class="stat__value stat__value--success" id="stat-correct">0</span>
            </div>
            <div class="stat">
              <span class="stat__label">Ошибки</span>
              <span class="stat__value stat__value--error" id="stat-wrong">0</span>
            </div>
            <div class="stat" id="stat-best-wrap" hidden>
              <span class="stat__label">Рекорд</span>
              <span class="stat__value" id="stat-best">0</span>
            </div>
            <div class="stat" id="stat-accuracy-wrap">
              <span class="stat__label">Точность</span>
              <span class="stat__value" id="stat-accuracy">—</span>
            </div>
          </div>
        </div>

        <div class="next-note-panel" id="next-note-panel">
          <div class="next-note-panel__label">Следующая нота</div>
          <div class="next-note-panel__note" id="next-note-name">—</div>
          <div class="next-note-panel__hint" id="next-note-hint">Выберите урок и нажмите «Начать»</div>
        </div>

        <div class="feedback" id="feedback" aria-live="polite"></div>

        <div class="staff-viewport" id="staff-viewport">
          <div class="staff-scroll">
            <svg class="staff-svg" id="staff-svg" role="img" aria-label="Нотный стан"></svg>
          </div>
        </div>

        <div class="piano-wrap" id="piano-wrap">
          <div class="piano-case">
            <div class="piano-case__lid"></div>
            <div class="piano-case__keys">
              <div class="piano" id="piano" role="application" aria-label="Клавиатура пианино 88 клавиш"></div>
            </div>
            <div class="piano-case__board"></div>
          </div>
        </div>

        <div class="controls">
          <button type="button" class="btn btn--secondary controls-melody-only" id="btn-preview" disabled>Прослушать</button>
          <button type="button" class="btn btn--primary" id="btn-start" disabled>Начать</button>
          <button type="button" class="btn btn--secondary" id="btn-pause" disabled>Пауза</button>
          <button type="button" class="btn btn--secondary" id="btn-reset" disabled>Сброс</button>
          <label class="toggle controls-melody-only">
            <input type="checkbox" id="toggle-wait" checked>
            <span>Ждать нажатия (пошаговый режим)</span>
          </label>
          <label class="toggle">
            <input type="checkbox" id="toggle-keyboard" checked>
            <span>Играть с клавиатуры ПК</span>
          </label>
          <label class="toggle">
            <input type="checkbox" id="toggle-piano-visible" checked>
            <span>Показывать клавиатуру</span>
          </label>
        </div>
      </section>
    </main>
  </div>

  <script type="module" src="/assets/js/app.js"></script>
</body>
</html>
