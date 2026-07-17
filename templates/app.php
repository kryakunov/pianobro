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
    $publicDir = dirname(__DIR__) . '/public';
    $assetVersion = max(
      filemtime($publicDir . '/assets/js/app.js'),
      filemtime($publicDir . '/assets/js/stats-staff.js'),
      filemtime($publicDir . '/assets/js/note-roadmap.js'),
      filemtime($publicDir . '/assets/js/staff.js'),
      filemtime($publicDir . '/assets/js/clef-glyphs.js'),
      filemtime($publicDir . '/assets/css/style.css'),
      filemtime($publicDir . '/assets/favicon.svg'),
    );
  ?>
  <link rel="canonical" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/">
  <link rel="icon" href="/assets/favicon.svg?v=<?= (int) $assetVersion ?>" type="image/svg+xml">
  <link rel="icon" href="/favicon.ico?v=<?= (int) $assetVersion ?>" sizes="32x32">
  <link rel="apple-touch-icon" href="/assets/favicon.svg?v=<?= (int) $assetVersion ?>">
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
  <link rel="stylesheet" href="/assets/css/style.css?v=<?= (int) $assetVersion ?>">

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
          <svg class="icon icon--badge icon--brand" viewBox="0 0 32 32" aria-hidden="true"><use href="#ico-brand"/></svg>
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
    </header>

    <!-- Главная -->
    <section class="screen screen--active" id="screen-home">
      <div class="landing">
        <div class="landing-hero">
          <div class="landing-hero__content">
            <p class="landing-badge">
              <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-check"/></svg>
              Бесплатно · Без установки · В браузере
            </p>
            <h2 class="landing-hero__title">Научитесь читать ноты и&nbsp;играть любимые мелодии</h2>
            <p class="landing-hero__lead">
              Piano Bro — интерактивный тренажёр для цифрового пианино. Подключите MIDI-клавиатуру
              или играйте на экране — нотный стан, подсказки и статистика прогресса уже ждут вас.
            </p>
            <div class="landing-hero__actions">
              <button type="button" class="btn btn--primary btn--lg" id="btn-go-roadmap">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-target"/></svg>
                Путь новичка
              </button>
              <button type="button" class="btn btn--secondary btn--lg" id="btn-go-notes">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-notes"/></svg>
                Свободная тренировка
              </button>
              <button type="button" class="btn btn--secondary btn--lg" id="btn-go-melodies">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-melody"/></svg>
                Выбрать мелодию
              </button>
            </div>
            <ul class="landing-hero__pills" aria-label="Возможности">
              <li class="landing-pill">
                <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-midi"/></svg>
                MIDI-клавиатура
              </li>
              <li class="landing-pill">
                <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-mic"/></svg>
                Микрофон
              </li>
              <li class="landing-pill">
                <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-keyboard"/></svg>
                Экранное пианино
              </li>
              <li class="landing-pill">
                <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chart"/></svg>
                Статистика прогресса
              </li>
            </ul>
          </div>

          <div class="landing-hero__visual" aria-hidden="true">
            <div class="landing-visual">
              <div class="landing-visual__glow"></div>
              <div class="landing-visual__staff">
                <div class="landing-visual__lines"></div>
                <div class="landing-visual__note landing-visual__note--1"></div>
                <div class="landing-visual__note landing-visual__note--2"></div>
                <div class="landing-visual__note landing-visual__note--3"></div>
                <div class="landing-visual__clef">
                  <svg viewBox="0 0 24 24"><use href="#ico-treble"/></svg>
                </div>
              </div>
              <div class="landing-visual__keys">
                <span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span>
              </div>
              <div class="landing-visual__badge landing-visual__badge--correct">
                <svg class="icon icon--sm" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
                Верно!
              </div>
            </div>
          </div>
        </div>

        <section class="landing-section" aria-labelledby="landing-features-title">
          <h3 class="landing-section__title" id="landing-features-title">Почему Piano Bro</h3>
          <p class="landing-section__lead">Всё, что нужно для регулярных занятий — в одном месте</p>
          <div class="landing-features">
            <article class="landing-feature">
              <span class="landing-feature__icon icon-badge icon-badge--notes" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
              </span>
              <h4 class="landing-feature__title">Тренажёр нот</h4>
              <p class="landing-feature__text">Скрипичный и басовый ключ, диезы и бемоли. Учитесь узнавать ноты на стане и находить их на клавиатуре за секунды.</p>
            </article>
            <article class="landing-feature">
              <span class="landing-feature__icon icon-badge icon-badge--melody" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-melody"/></svg>
              </span>
              <h4 class="landing-feature__title">Мелодии и песни</h4>
              <p class="landing-feature__text">От простых упражнений до классики и популярных треков. Играйте по нотному стану с подсказками на клавишах.</p>
            </article>
            <article class="landing-feature">
              <span class="landing-feature__icon icon-badge icon-badge--stats" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-stats"/></svg>
              </span>
              <h4 class="landing-feature__title">Статистика прогресса</h4>
              <p class="landing-feature__text">Смотрите, какие ноты уже освоены, а какие ещё в процессе — на наглядном нотном стане и графике по дням.</p>
            </article>
            <article class="landing-feature">
              <span class="landing-feature__icon icon-badge icon-badge--brand" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-midi"/></svg>
              </span>
              <h4 class="landing-feature__title">MIDI и микрофон</h4>
              <p class="landing-feature__text">Подключите цифровое пианино по USB или играйте в микрофон — тренажёр распознает нажатия и оценивает вашу игру.</p>
            </article>
          </div>
        </section>

        <section class="landing-section landing-section--steps" aria-labelledby="landing-steps-title">
          <h3 class="landing-section__title" id="landing-steps-title">Как начать за 3 шага</h3>
          <ol class="landing-steps">
            <li class="landing-step">
              <span class="landing-step__num">1</span>
              <div class="landing-step__body">
                <strong class="landing-step__title">Подключите инструмент</strong>
                <p class="landing-step__text">MIDI-клавиатура, микрофон или экранное пианино — выберите удобный способ.</p>
              </div>
            </li>
            <li class="landing-step">
              <span class="landing-step__num">2</span>
              <div class="landing-step__body">
                <strong class="landing-step__title">Выберите режим</strong>
                <p class="landing-step__text">Тренируйте отдельные ноты или учите целую мелодию по нотному стану.</p>
              </div>
            </li>
            <li class="landing-step">
              <span class="landing-step__num">3</span>
              <div class="landing-step__body">
                <strong class="landing-step__title">Играйте и растите</strong>
                <p class="landing-step__text">Получайте мгновенную обратную связь, включайте подсказки и отслеживайте прогресс.</p>
              </div>
            </li>
          </ol>
        </section>

        <section class="landing-section" aria-labelledby="landing-modes-title">
          <h3 class="landing-section__title" id="landing-modes-title">Выберите, с чего начать</h3>
          <div class="landing-modes">
            <button type="button" class="home-card home-card--roadmap landing-mode" id="btn-go-roadmap-card">
              <span class="home-card__icon icon-badge icon-badge--roadmap" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-target"/></svg>
              </span>
              <span class="home-card__title">Путь новичка</span>
              <span class="home-card__desc">8 уровней от простых белых нот до полного диапазона — с прогрессом и наградами</span>
              <span class="landing-mode__cta">Открыть карту →</span>
            </button>
            <button type="button" class="home-card home-card--notes landing-mode" data-landing-go="notes">
              <span class="home-card__icon icon-badge icon-badge--notes" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
              </span>
              <span class="home-card__title">Тренажёр нот</span>
              <span class="home-card__desc">Запоминайте ноты на стане и находите их на клавиатуре — идеально для начинающих</span>
              <span class="landing-mode__cta">Настроить и начать →</span>
            </button>
            <button type="button" class="home-card home-card--melody landing-mode" data-landing-go="melodies">
              <span class="home-card__icon icon-badge icon-badge--melody" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-melody"/></svg>
              </span>
              <span class="home-card__title">Мелодии</span>
              <span class="home-card__desc">Учите популярные песни и классику — ноты подсвечиваются на клавишах</span>
              <span class="landing-mode__cta">Открыть каталог →</span>
            </button>
            <button type="button" class="home-card home-card--stats landing-mode" id="btn-go-stats-home">
              <span class="home-card__icon icon-badge icon-badge--stats" aria-hidden="true">
                <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-stats"/></svg>
              </span>
              <span class="home-card__title">Моя статистика</span>
              <span class="home-card__desc">Смотрите, какие ноты выучены хорошо, а какие стоит потренировать сегодня</span>
              <span class="landing-mode__cta">Посмотреть прогресс →</span>
            </button>
          </div>
        </section>

        <section class="landing-cta" aria-labelledby="landing-cta-title">
          <div class="landing-cta__card">
            <h3 class="landing-cta__title" id="landing-cta-title">Готовы сыграть первую ноту?</h3>
            <p class="landing-cta__text">Начните прямо сейчас — регистрация не обязательна, всё работает бесплатно в браузере.</p>
            <button type="button" class="btn btn--primary btn--lg" data-landing-go="notes">
              <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-play"/></svg>
              Попробовать бесплатно
            </button>
          </div>
        </section>
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

    <!-- Путь новичка -->
    <section class="screen" id="screen-roadmap" hidden>
      <div class="screen-header">
        <button type="button" class="btn-back" id="btn-back-roadmap">← Назад</button>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--roadmap" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-target"/></svg>
          </span>
          Путь новичка
        </h2>
      </div>
      <div class="roadmap-panel">
        <div class="roadmap-hero" id="roadmap-hero">
          <div class="roadmap-hero__rank">
            <span class="roadmap-hero__emoji" id="roadmap-rank-emoji">🌱</span>
            <div>
              <p class="roadmap-hero__label">Ваш ранг</p>
              <p class="roadmap-hero__title" id="roadmap-rank-title">Новичок</p>
            </div>
          </div>
          <div class="roadmap-hero__stats">
            <div class="roadmap-stat">
              <span class="roadmap-stat__value" id="roadmap-xp-total">0</span>
              <span class="roadmap-stat__label">XP</span>
            </div>
            <div class="roadmap-stat">
              <span class="roadmap-stat__value" id="roadmap-stages-done">0/8</span>
              <span class="roadmap-stat__label">Уровней</span>
            </div>
          </div>
        </div>
        <p class="roadmap-lead" id="roadmap-lead">Проходите уровни по порядку. В каждом уроке нужно верно сыграть все ноты задания — только они и каждая по разу.</p>
        <div class="roadmap-guest-hint" id="roadmap-guest-hint" hidden>
          <p>Войдите в аккаунт, чтобы сохранить прогресс на всех устройствах.</p>
          <button type="button" class="btn btn--secondary btn--sm" id="btn-roadmap-login">Войти</button>
        </div>
        <div class="roadmap-path" id="roadmap-path" aria-live="polite"></div>
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
        <div class="practice-progress" id="practice-progress">0 / 10</div>
      </div>

      <div
        class="input-status-banner practice-input-status practice-input-status--off"
        id="input-status-banner"
        hidden
      >
        <span class="practice-input-status__dot" id="input-status-dot" aria-hidden="true"></span>
        <span class="practice-input-status__text" id="input-status-text">Пианино не подключено</span>
        <div class="input-status-banner__actions">
          <select class="midi-select input-status-banner__select" id="input-status-midi-select" disabled hidden>
            <option value="">Выбор устройства…</option>
          </select>
          <button type="button" class="practice-input-status__btn" id="btn-input-connect-midi">Подключить MIDI</button>
          <button type="button" class="practice-input-status__btn" id="btn-input-connect-mic">Микрофон</button>
        </div>
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

      <div class="practice-feedback-wrap">
        <div class="practice-feedback" id="practice-feedback" aria-live="polite"></div>
      </div>

      <div class="practice-controls" id="practice-controls" hidden>
        <div class="practice-controls__row">
          <div class="keyboard-mode" id="keyboard-visibility-panel">
            <span class="keyboard-mode__label">Клавиатура</span>
            <div class="keyboard-mode__tabs" role="group" aria-label="Видимость клавиатуры">
              <button type="button" class="keyboard-mode__tab keyboard-mode__tab--active" data-keyboard="on">На экране</button>
              <button type="button" class="keyboard-mode__tab" data-keyboard="off">Скрыта</button>
            </div>
          </div>

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
        <div class="auth-social" id="auth-social" hidden>
          <p class="auth-social__divider"><span>или</span></p>
          <div class="auth-social__buttons" id="auth-social-buttons"></div>
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
          <button type="button" class="btn btn--primary" id="btn-modal-roadmap-next" hidden>Следующий уровень</button>
          <button type="button" class="btn btn--secondary" id="btn-modal-roadmap" hidden>К пути обучения</button>
          <button type="button" class="btn btn--secondary" id="btn-modal-pick">Другой урок</button>
          <button type="button" class="btn btn--secondary" id="btn-modal-home">На главную</button>
        </div>
      </div>
    </div>
  </div>

  <svg xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" aria-hidden="true">
    <symbol id="ico-brand" viewBox="0 0 32 32">
      <ellipse cx="11.5" cy="22.5" rx="5" ry="3.8" transform="rotate(-18 11.5 22.5)" fill="currentColor"/>
      <rect x="15.2" y="7" width="2.8" height="16.8" rx="1.4" fill="currentColor"/>
    </symbol>
    <symbol id="ico-piano" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M4 10h16v10H4z"/><path d="M7 10V8a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"/><path d="M8 14h2v4H8zM11 14h2v4h-2zM14 14h2v4h-2zM17 14h2v4h-2z"/><path d="M9.5 10v4M12.5 10v4M15.5 10v4" stroke-width="2"/>
    </symbol>
    <symbol id="ico-melody" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2.5" fill="currentColor" stroke="none"/><circle cx="17" cy="16" r="2.5" fill="currentColor" stroke="none"/><path d="M4 20h20" opacity="0.35"/>
    </symbol>
    <symbol id="ico-treble" viewBox="0 0 24 24">
      <path d="M2 7.5h20M2 10h20M2 12.5h20M2 15h20M2 17.5h20" stroke="currentColor" stroke-width="0.65" opacity="0.3"/>
      <path d="M 5.30 10.80 C 5.28 10.68 5.30 10.68 5.36 10.61 C 6.46 9.59 7.28 8.31 7.28 6.76 C 7.28 5.88 7.04 5.01 6.62 4.41 C 6.47 4.18 6.21 3.90 6.10 3.90 C 5.96 3.90 5.64 4.16 5.45 4.39 C 4.69 5.21 4.45 6.48 4.45 7.53 C 4.45 8.11 4.53 8.77 4.60 9.19 C 4.62 9.31 4.62 9.33 4.50 9.43 C 3.04 10.63 1.50 12.08 1.50 14.12 C 1.50 15.88 2.70 17.55 5.18 17.55 C 5.41 17.55 5.68 17.53 5.88 17.49 C 5.99 17.46 6.01 17.46 6.03 17.58 C 6.15 18.26 6.30 19.13 6.30 19.61 C 6.30 21.11 5.29 21.29 4.69 21.29 C 4.15 21.29 3.89 21.13 3.89 21.00 C 3.89 20.92 3.98 20.90 4.21 20.82 C 4.53 20.73 4.88 20.46 4.88 19.87 C 4.88 19.32 4.53 18.84 3.91 18.84 C 3.24 18.84 2.83 19.38 2.83 20.01 C 2.83 20.66 3.23 21.65 4.76 21.65 C 5.43 21.65 6.74 21.35 6.74 19.63 C 6.74 19.05 6.56 18.10 6.46 17.46 C 6.44 17.34 6.44 17.36 6.58 17.30 C 7.61 16.89 8.28 16.03 8.28 14.89 C 8.28 13.60 7.33 12.45 5.85 12.45 C 5.59 12.45 5.59 12.45 5.55 12.27 M 6.25 5.47 C 6.58 5.47 6.86 5.74 6.86 6.30 C 6.86 7.42 5.89 8.33 5.10 9.03 C 5.03 9.09 4.99 9.08 4.97 8.94 C 4.93 8.68 4.90 8.34 4.90 8.02 C 4.90 6.44 5.63 5.47 6.25 5.47 M 5.15 12.35 C 5.18 12.54 5.18 12.54 5.00 12.59 C 4.11 12.89 3.53 13.69 3.53 14.56 C 3.53 15.46 4.01 16.11 4.69 16.35 C 4.78 16.38 4.90 16.40 4.97 16.40 C 5.05 16.40 5.09 16.35 5.09 16.29 C 5.09 16.22 5.01 16.19 4.94 16.17 C 4.51 15.98 4.21 15.55 4.21 15.08 C 4.21 14.50 4.60 14.07 5.22 13.90 C 5.38 13.86 5.40 13.87 5.42 13.98 L 5.93 16.99 C 5.95 17.11 5.94 17.11 5.79 17.13 C 5.63 17.16 5.42 17.18 5.22 17.18 C 3.45 17.18 2.31 16.20 2.31 14.80 C 2.31 14.20 2.41 13.40 3.25 12.45 C 3.86 11.78 4.32 11.40 4.79 11.02 C 4.90 10.94 4.92 10.95 4.94 11.05 M 5.85 13.96 C 5.82 13.83 5.84 13.81 5.96 13.82 C 6.78 13.89 7.45 14.58 7.45 15.46 C 7.45 16.10 7.07 16.61 6.51 16.90 C 6.39 16.96 6.36 16.96 6.34 16.84" fill="currentColor"/>
    </symbol>
    <symbol id="ico-bass" viewBox="0 0 24 24">
      <path d="M2 7.5h20M2 10h20M2 12.5h20M2 15h20M2 17.5h20" stroke="currentColor" stroke-width="0.65" opacity="0.3"/>
      <path d="M 4.05 7.35 C 2.29 7.35 1.50 8.64 1.50 9.61 C 1.50 10.41 1.92 11.11 2.74 11.11 C 3.38 11.11 3.82 10.67 3.82 10.04 C 3.82 9.40 3.34 8.99 2.85 8.99 C 2.57 8.99 2.47 9.06 2.34 9.06 C 2.21 9.06 2.17 8.98 2.17 8.88 C 2.17 8.48 2.78 7.73 3.82 7.73 C 4.88 7.73 5.35 8.79 5.35 10.37 C 5.35 13.19 3.96 14.77 1.60 16.11 C 1.51 16.16 1.45 16.22 1.45 16.30 C 1.45 16.36 1.49 16.42 1.58 16.42 C 1.63 16.42 1.69 16.40 1.75 16.37 C 4.24 15.15 6.87 13.36 6.87 10.28 C 6.87 8.53 5.80 7.35 4.05 7.35 M 7.86 8.18 C 7.54 8.18 7.31 8.42 7.31 8.74 C 7.31 9.05 7.54 9.29 7.86 9.29 C 8.17 9.29 8.41 9.05 8.41 8.74 C 8.41 8.42 8.17 8.18 7.86 8.18 M 7.87 10.72 C 7.56 10.72 7.32 10.95 7.32 11.26 C 7.32 11.58 7.56 11.81 7.87 11.81 C 8.18 11.81 8.41 11.58 8.41 11.26 C 8.41 10.95 8.18 10.72 7.87 10.72" fill="currentColor"/>
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

  <script type="module" src="/assets/js/app.js?v=<?= (int) $assetVersion ?>"></script>
</body>
</html>
