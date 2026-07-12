<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Piano Trainer — обучение игре на пианино</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
  <div class="app">
    <header class="header">
      <div class="header__brand">
        <span class="header__logo">♪</span>
        <div>
          <h1>Piano Trainer</h1>
          <p class="header__subtitle">Играйте на пианино — приложение подскажет верную ноту</p>
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
          <h2>Уроки</h2>
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
