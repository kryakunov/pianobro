<!DOCTYPE html>
<html lang="ru">
<head>
  <?php
    use PianoTrainer\PageRegistry;

    $page = $page ?? PageRegistry::match('/', null, null) ?? [
      'screen' => 'home',
      'path' => '/',
      'title' => 'Piano Bro',
      'description' => '',
      'boot' => ['screen' => 'home'],
    ];
    $initialScreen = (string) ($page['screen'] ?? 'home');

    $assetVersion = \PianoTrainer\AssetVersion::compute();

    $screenActive = static function (string $screen) use ($initialScreen): string {
      return $screen === $initialScreen ? ' screen--active' : '';
    };
    $screenHidden = static function (string $screen) use ($initialScreen): string {
      return $screen === $initialScreen ? '' : ' hidden';
    };

    require __DIR__ . '/partials/head.php';
    require __DIR__ . '/partials/blog-render.php';

    $user = $user ?? null;
    $isTeacher = $isTeacher ?? false;
    $isStudent = $isStudent ?? false;
    $isLoggedIn = $user !== null;
  ?>
  <script>
    window.__BOOT__ = <?= json_encode($page['boot'] ?? ['screen' => 'home'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
    window.__USER__ = <?= json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.__PRICING__ = <?= json_encode($pricing ?? \PianoTrainer\PricingConfig::toPublicArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
    window.__BILLING_BOOT__ = <?= json_encode($billingBoot ?? ['mockMode' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;
    window.__ASSET_VERSION__ = <?= (int) $assetVersion ?>;
  </script>
</head>
<body>
  <div class="app" id="app">
    <header class="header" id="main-header">
      <div class="header__brand">
        <a href="/" class="header__brand-link">
          <span class="header__logo icon-badge icon-badge--brand" aria-hidden="true">
            <svg class="icon icon--badge icon--brand" viewBox="0 0 32 32" aria-hidden="true"><use href="#ico-brand"/></svg>
          </span>
          <div>
            <h2>Piano Bro</h2>
            <p class="header__subtitle">Обучение на пианино</p>
          </div>
        </a>
      </div>
      <div class="header__auth" id="auth-panel">
        <button type="button" class="btn btn--secondary btn--sm" id="btn-open-auth"<?= $isLoggedIn ? ' hidden' : '' ?>>
          <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-login"/></svg>
          Войти
        </button>
        <div class="auth-user" id="auth-user"<?= $isLoggedIn ? '' : ' hidden' ?>>
          <div class="auth-user__profile">
            <?php
              $avatar = $headerAvatar ?? null;
              $avatarClass = 'auth-user__avatar auth-user__avatar--rank-' . (int) ($avatar['rankIndex'] ?? 0);
              if (!empty($avatar['isPremium'])) {
                $avatarClass .= ' auth-user__avatar--premium';
              }
              $avatarStyle = '--avatar-hue: ' . (int) (($avatar['userId'] ?? 1) * 47 % 360);
            ?>
            <div
              class="<?= htmlspecialchars($avatarClass, ENT_QUOTES, 'UTF-8') ?>"
              id="auth-user-avatar"
              style="<?= htmlspecialchars($avatarStyle, ENT_QUOTES, 'UTF-8') ?>"
              title="<?= $isLoggedIn ? htmlspecialchars((string) (($avatar['rankTitle'] ?? 'Новичок') . (!empty($avatar['isPremium']) ? ' · Premium' : '')), ENT_QUOTES, 'UTF-8') : '' ?>"
              aria-hidden="true"
            ><?= $isLoggedIn ? htmlspecialchars((string) ($avatar['emoji'] ?? '🎹'), ENT_QUOTES, 'UTF-8') : '' ?></div>
            <div class="auth-user__identity">
              <span class="auth-user__name" id="auth-user-name"><?= $isLoggedIn ? htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') : '' ?></span>
              <span class="auth-user__rank" id="auth-user-rank"<?= $isLoggedIn ? '' : ' hidden' ?>><?= $isLoggedIn ? htmlspecialchars((string) ($avatar['rankTitle'] ?? ''), ENT_QUOTES, 'UTF-8') : '' ?></span>
              <span class="auth-user__plan" id="auth-user-plan"<?= $isLoggedIn ? '' : ' hidden' ?>></span>
            </div>
          </div>
          <a href="/statistika" class="btn btn--secondary btn--sm" id="btn-go-stats">
            <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-stats"/></svg>
            Статистика
          </a>
          <a href="/domashka" class="btn btn--secondary btn--sm" id="btn-go-homework"<?= ($isLoggedIn && $isStudent) ? '' : ' hidden' ?>>
            <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-homework"/></svg>
            Домашка
          </a>
          <a href="/teacher" class="btn btn--secondary btn--sm" id="btn-go-teacher"<?= ($isLoggedIn && $isTeacher) ? '' : ' hidden' ?>>
            <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-users"/></svg>
            Ученики
          </a>
          <button type="button" class="btn btn--secondary btn--sm" id="btn-logout"<?= $isLoggedIn ? '' : ' hidden' ?>>
            <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-logout"/></svg>
            Выйти
          </button>
        </div>
      </div>
    </header>

    <div class="invite-banner" id="invite-banner" hidden>
      <div class="invite-banner__inner">
        <p id="invite-banner-text">Преподаватель приглашает вас зарегистрироваться.</p>
        <button type="button" class="btn btn--primary btn--sm" id="btn-invite-register">Зарегистрироваться</button>
      </div>
    </div>

    <?php if (!empty($page['lesson']) && is_array($page['lesson'])): ?>
    <article class="seo-intro seo-intro--compact seo-intro--crawler" id="seo-intro" aria-hidden="true">
      <div class="seo-intro__inner">
        <h1 class="seo-intro__title"><?= htmlspecialchars((string) $page['lesson']['title'], ENT_QUOTES, 'UTF-8') ?> — ноты для пианино</h1>
        <p class="seo-intro__lead">
          Мелодия «<?= htmlspecialchars((string) $page['lesson']['title'], ENT_QUOTES, 'UTF-8') ?>»
          (<?= htmlspecialchars((string) $page['lesson']['composer'], ENT_QUOTES, 'UTF-8') ?>).
          Сложность: <?= htmlspecialchars((string) $page['lesson']['difficulty'], ENT_QUOTES, 'UTF-8') ?>.
          Тренажёр мелодий на нотном стане с подсказками на клавишах.
        </p>
      </div>
    </article>
    <?php elseif (!empty($page['seoIntro']) && is_array($page['seoIntro'])): ?>
    <article class="seo-intro seo-intro--compact seo-intro--crawler" id="seo-intro" aria-hidden="true">
      <div class="seo-intro__inner">
        <h1 class="seo-intro__title"><?= htmlspecialchars((string) ($page['seoIntro']['h1'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($page['seoIntro']['lead'])): ?>
        <p class="seo-intro__lead"><?= htmlspecialchars((string) $page['seoIntro']['lead'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
      </div>
      <?php if (!empty($page['seoIntro']['features']) && is_array($page['seoIntro']['features'])): ?>
      <ul class="seo-intro__list">
        <?php foreach ($page['seoIntro']['features'] as $feature): ?>
        <li><?= htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </article>
    <?php endif; ?>

    <!-- Главная -->
    <section class="screen<?= $screenActive('home') ?>" id="screen-home"<?= $screenHidden('home') ?>>
      <div class="landing">
        <div class="landing-hero">
          <div class="landing-hero__content">
            <p class="landing-badge">
              <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-check"/></svg>
              Бесплатная диагностика · Без установки · В браузере
            </p>
            <?php if (($page['screen'] ?? '') === 'home'): ?>
            <h1 class="landing-hero__title">Перестаньте путаться в нотах на пианино</h1>
            <?php else: ?>
            <p class="landing-hero__title">Перестаньте путаться в нотах на пианино</p>
            <?php endif; ?>
            <p class="landing-hero__lead">
              PianoBro запоминает ваши ошибки и подбирает персональные упражнения, чтобы вы быстрее читали ноты в скрипичном и басовом ключе.
            </p>
            <div class="landing-hero__actions">
              <button type="button" class="btn btn--secondary btn--lg landing-hero__cta" id="btn-start-diagnostic">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-chart"/></svg>
                Узнать, какие ноты путаю
              </button>
            </div>
            <nav class="landing-nav" aria-label="Разделы тренажёра">
              <a href="/put-novichka" class="landing-nav__item landing-nav__item--roadmap" id="btn-go-roadmap">
                <span class="landing-nav__icon icon-badge icon-badge--roadmap" aria-hidden="true">
                  <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-leaf"/></svg>
                </span>
                <span class="landing-nav__label">Путь новичка</span>
              </a>
              <a href="/noty" class="landing-nav__item landing-nav__item--notes" id="btn-go-notes">
                <span class="landing-nav__icon icon-badge icon-badge--notes" aria-hidden="true">
                  <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
                </span>
                <span class="landing-nav__label">Тренажёр нот</span>
              </a>
              <a href="/ritm" class="landing-nav__item landing-nav__item--rhythm" id="btn-go-rhythm">
                <span class="landing-nav__icon icon-badge icon-badge--session" aria-hidden="true">
                  <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-note-half"/></svg>
                </span>
                <span class="landing-nav__label">Ритм-игра</span>
              </a>
              <a href="/melodii" class="landing-nav__item landing-nav__item--melody" id="btn-go-melodies">
                <span class="landing-nav__icon icon-badge icon-badge--melody" aria-hidden="true">
                  <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-melody"/></svg>
                </span>
                <span class="landing-nav__label">Мелодии</span>
              </a>
            </nav>
            <ul class="landing-hero__pills" aria-label="Для педагогов">
              <li class="landing-pill">
                <svg class="icon icon--sm" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-users"/></svg>
                Для педагогов — домашка и ученики
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

        <section class="landing-compare pick-panel" aria-labelledby="landing-compare-title">
          <p class="landing-compare__eyebrow">Персонализация</p>
          <h2 class="landing-compare__title" id="landing-compare-title">Чем PianoBro отличается от обычных бесплатных тренажёров</h2>
          <div class="landing-compare__grid">
            <article class="landing-compare__card landing-compare__card--plain">
              <header class="landing-compare__head">
                <span class="landing-compare__head-icon landing-compare__head-icon--plain" aria-hidden="true">
                  <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-note-half"/></svg>
                </span>
                <h3 class="landing-compare__subtitle">Обычный тренажёр</h3>
              </header>
              <ul class="landing-compare__list">
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--no" aria-hidden="true">×</span>
                  <span>Даёт случайные упражнения</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--no" aria-hidden="true">×</span>
                  <span>Не помнит ошибки</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--no" aria-hidden="true">×</span>
                  <span>Не показывает слабые места</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--no" aria-hidden="true">×</span>
                  <span>Не строит программу</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--no" aria-hidden="true">×</span>
                  <span>Быстро надоедает</span>
                </li>
              </ul>
            </article>

            <div class="landing-compare__vs" aria-hidden="true"><span>vs</span></div>

            <article class="landing-compare__card landing-compare__card--accent">
              <header class="landing-compare__head">
                <span class="landing-compare__head-icon landing-compare__head-icon--accent" aria-hidden="true">
                  <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-brand"/></svg>
                </span>
                <h3 class="landing-compare__subtitle">PianoBro</h3>
              </header>
              <ul class="landing-compare__list">
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--yes" aria-hidden="true">
                    <svg class="icon icon--sm" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
                  </span>
                  <span>Запоминает ваши ошибки</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--yes" aria-hidden="true">
                    <svg class="icon icon--sm" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
                  </span>
                  <span>Чаще повторяет сложные ноты</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--yes" aria-hidden="true">
                    <svg class="icon icon--sm" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
                  </span>
                  <span>Показывает прогресс</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--yes" aria-hidden="true">
                    <svg class="icon icon--sm" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
                  </span>
                  <span>Составляет персональные тренировки</span>
                </li>
                <li class="landing-compare__item">
                  <span class="landing-compare__mark landing-compare__mark--yes" aria-hidden="true">
                    <svg class="icon icon--sm" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
                  </span>
                  <span>Помогает заниматься регулярно</span>
                </li>
              </ul>
            </article>
          </div>
        </section>

        <section class="landing-showcase" aria-labelledby="landing-show-roadmap">
          <div class="landing-showcase__art" aria-hidden="true">
            <div class="landing-art landing-art--roadmap">
              <div class="landing-art__glow landing-art__glow--roadmap"></div>
              <div class="landing-art__roadmap-steps">
                <span class="is-done">1</span>
                <span class="is-done">2</span>
                <span class="is-active">3</span>
                <span>4</span>
                <span>5</span>
                <span>6</span>
                <span>7</span>
                <span>8</span>
              </div>
              <p class="landing-art__roadmap-caption">Уровни с мелодиями-закреплением</p>
            </div>
          </div>
          <div class="landing-showcase__content">
            <span class="landing-showcase__tag landing-showcase__tag--roadmap">Путь новичка</span>
            <h3 class="landing-showcase__title" id="landing-show-roadmap">От первой ноты до уверенного чтения</h3>
            <p class="landing-showcase__text">8 уровней с постепенным усложнением — ноты, ключи и короткие мелодии для закрепления.</p>
            <a href="/put-novichka" class="landing-showcase__link" id="btn-go-roadmap-card">Начать путь →</a>
          </div>
        </section>

        <section class="landing-showcase landing-showcase--reverse" aria-labelledby="landing-show-notes">
          <div class="landing-showcase__art" aria-hidden="true">
            <div class="landing-art landing-art--notes">
              <div class="landing-art__glow"></div>
              <div class="landing-art__staff">
                <div class="landing-art__lines"></div>
                <div class="landing-art__clef"><svg viewBox="0 0 24 24"><use href="#ico-treble"/></svg></div>
                <div class="landing-art__note landing-art__note--a"></div>
                <div class="landing-art__cursor"></div>
              </div>
              <div class="landing-art__keys">
                <span></span><span></span><span class="is-lit"></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span>
              </div>
            </div>
          </div>
          <div class="landing-showcase__content">
            <span class="landing-showcase__tag">Тренажёр</span>
            <h3 class="landing-showcase__title" id="landing-show-notes">Тренажёр нот на нотном стане</h3>
            <p class="landing-showcase__text">Угадай ноту, тренируй попадание в клавиши и запоминание — скрипичный и басовый ключ, чтение нот с листа.</p>
            <a href="/noty" class="landing-showcase__link">Открыть тренажёр →</a>
          </div>
        </section>

        <section class="landing-showcase" aria-labelledby="landing-show-melody">
          <div class="landing-showcase__art" aria-hidden="true">
            <div class="landing-art landing-art--melody">
              <div class="landing-art__glow landing-art__glow--violet"></div>
              <div class="landing-art__wave">
                <svg viewBox="0 0 240 80" preserveAspectRatio="none">
                  <path d="M0 40 Q30 10 60 40 T120 40 T180 40 T240 40" fill="none" stroke="currentColor" stroke-width="3"/>
                  <path d="M0 50 Q30 70 60 50 T120 50 T180 50 T240 50" fill="none" stroke="currentColor" stroke-width="2" opacity="0.4"/>
                </svg>
              </div>
              <div class="landing-art__play">
                <svg viewBox="0 0 24 24"><use href="#ico-volume"/></svg>
              </div>
              <div class="landing-art__melody-notes">
                <span></span><span></span><span></span><span></span><span></span>
              </div>
            </div>
          </div>
          <div class="landing-showcase__content">
            <span class="landing-showcase__tag">Мелодии</span>
            <h3 class="landing-showcase__title" id="landing-show-melody">Играйте любимые песни</h3>
            <p class="landing-showcase__text">Twinkle, Mario, «К Элизе» и десятки других — с прослушиванием и подсветкой клавиш.</p>
            <a href="/melodii" class="landing-showcase__link">Выбрать мелодию →</a>
          </div>
        </section>

        <section class="landing-showcase landing-showcase--reverse" aria-labelledby="landing-show-stats">
          <div class="landing-showcase__art" aria-hidden="true">
            <div class="landing-art landing-art--stats">
              <div class="landing-art__glow landing-art__glow--green"></div>
              <div class="landing-art__chart">
                <span style="--h:35%"></span>
                <span style="--h:55%"></span>
                <span style="--h:40%"></span>
                <span style="--h:70%"></span>
                <span style="--h:60%"></span>
                <span style="--h:85%"></span>
                <span style="--h:75%"></span>
              </div>
              <div class="landing-art__dots">
                <span class="is-mastered"></span><span class="is-mastered"></span><span></span>
                <span class="is-mastered"></span><span></span><span class="is-mastered"></span>
                <span></span><span class="is-mastered"></span><span></span><span class="is-mastered"></span>
              </div>
            </div>
          </div>
          <div class="landing-showcase__content">
            <span class="landing-showcase__tag">Прогресс</span>
            <h3 class="landing-showcase__title" id="landing-show-stats">Видно, что уже получается</h3>
            <p class="landing-showcase__text">Карта нот на стане и график занятий — сразу понятно, что повторить сегодня.</p>
            <a href="/statistika" class="landing-showcase__link" id="btn-go-stats-home">Смотреть статистику →</a>
          </div>
        </section>

        <section class="landing-showcase" aria-labelledby="landing-show-teacher">
          <div class="landing-showcase__art" aria-hidden="true">
            <div class="landing-art landing-art--teacher">
              <div class="landing-art__glow landing-art__glow--teacher"></div>
              <ul class="landing-art__students">
                <li><span class="landing-art__student-avatar">А</span> Анна · 12/20 нот</li>
                <li><span class="landing-art__student-avatar">М</span> Максим · домашка</li>
                <li><span class="landing-art__student-avatar">С</span> София · 85% точность</li>
              </ul>
              <div class="landing-art__assignment">
                <span class="landing-art__assignment-tag">Задание</span>
                <strong>До–Ре–Ми, скрипичный ключ</strong>
                <span>20 нот · мин. 70%</span>
              </div>
              <div class="landing-art__chart landing-art__chart--compact">
                <span style="--h:40%"></span>
                <span style="--h:55%"></span>
                <span style="--h:48%"></span>
                <span style="--h:72%"></span>
                <span style="--h:68%"></span>
              </div>
            </div>
          </div>
          <div class="landing-showcase__content">
            <span class="landing-showcase__tag landing-showcase__tag--teacher">Педагогам</span>
            <h3 class="landing-showcase__title" id="landing-show-teacher">Домашка и прогресс учеников</h3>
            <p class="landing-showcase__text">
              Приглашайте учеников по email, назначайте тренировки по нотам и следите за их результатами —
              карта освоенных нот, статистика сессий и выполненные задания в одном кабинете.
            </p>
            <ul class="landing-showcase__list">
              <li>Приглашение учеников и список класса</li>
              <li>Индивидуальные задания с требованиями к точности</li>
              <li>Прогресс каждого ученика, как в его личной статистике</li>
            </ul>
            <a href="/teacher" class="landing-showcase__link">В кабинет педагога →</a>
          </div>
        </section>

        <section class="landing-quickstart" aria-label="Как начать">
          <div class="landing-quickstart__item">
            <span class="landing-quickstart__icon icon-badge icon-badge--brand" aria-hidden="true">
              <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-midi"/></svg>
            </span>
            <span class="landing-quickstart__label">Подключите MIDI, микрофон или экран</span>
          </div>
          <span class="landing-quickstart__arrow" aria-hidden="true">→</span>
          <div class="landing-quickstart__item">
            <span class="landing-quickstart__icon icon-badge icon-badge--notes" aria-hidden="true">
              <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
            </span>
            <span class="landing-quickstart__label">Выберите ноты или мелодию</span>
          </div>
          <span class="landing-quickstart__arrow" aria-hidden="true">→</span>
          <div class="landing-quickstart__item">
            <span class="landing-quickstart__icon icon-badge icon-badge--melody" aria-hidden="true">
              <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-play"/></svg>
            </span>
            <span class="landing-quickstart__label">Играйте — тренажёр подскажет</span>
          </div>
        </section>

        <section class="landing-cta" aria-labelledby="landing-cta-title">
          <div class="landing-cta__visual" aria-hidden="true">
            <svg viewBox="0 0 120 120" class="landing-cta__note">
              <ellipse cx="38" cy="78" rx="14" ry="10" transform="rotate(-18 38 78)" fill="currentColor"/>
              <rect x="48" y="28" width="5" height="52" rx="2.5" fill="currentColor"/>
            </svg>
          </div>
          <div class="landing-cta__body">
            <h3 class="landing-cta__title" id="landing-cta-title">Первая нота — через минуту</h3>
            <a href="/noty" class="btn btn--primary btn--lg">
              <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-play"/></svg>
              Попробовать бесплатно
            </a>
            <a href="/blog" class="landing-cta__blog">Блог о нотах и тренировках →</a>
          </div>
        </section>

        <?php require __DIR__ . '/partials/social-links.php'; ?>
      </div>
    </section>

    <!-- Блог -->
    <section class="screen<?= $screenActive('blog') ?>" id="screen-blog"<?= $screenHidden('blog') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-blog">← На главную</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--accent" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-session"/></svg>
          </span>
          Блог
        </h2>
      </div>
      <div class="blog-page pick-panel">
        <p class="blog-page__lead">Статьи о чтении нот, тренировках на фортепиано и работе с тренажёром онлайн.</p>
        <div class="blog-list">
          <?php foreach (($page['blogPosts'] ?? []) as $post): ?>
            <article class="blog-card">
              <time class="blog-card__date" datetime="<?= htmlspecialchars((string) ($post['publishedAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(formatBlogDate((string) ($post['publishedAt'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
              </time>
              <h3 class="blog-card__title">
                <a href="<?= htmlspecialchars((string) ($post['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars((string) ($post['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h3>
              <p class="blog-card__excerpt"><?= htmlspecialchars((string) ($post['lead'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
              <a class="blog-card__more" href="<?= htmlspecialchars((string) ($post['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">Читать →</a>
            </article>
          <?php endforeach; ?>
        </div>
        <?php require __DIR__ . '/partials/social-links.php'; ?>
      </div>
    </section>

    <?php
      $blogPost = is_array($page['blogPost'] ?? null) ? $page['blogPost'] : null;
      $blogRelated = is_array($page['blogRelated'] ?? null) ? $page['blogRelated'] : [];
      $blogHeadingTag = ($page['screen'] ?? '') === 'blog-article' ? 'h1' : 'h2';
    ?>
    <section class="screen<?= $screenActive('blog-article') ?>" id="screen-blog-article"<?= $screenHidden('blog-article') ?>>
      <div class="screen-header">
        <a href="/blog" class="btn-back" id="btn-back-blog-article">← К блогу</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--accent" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-session"/></svg>
          </span>
          Блог
        </h2>
      </div>
      <?php if ($blogPost): ?>
      <article class="blog-article pick-panel">
        <header class="blog-article__header">
          <time class="blog-article__date" datetime="<?= htmlspecialchars((string) ($blogPost['publishedAt'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars(formatBlogDate((string) ($blogPost['publishedAt'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
          </time>
          <<?= $blogHeadingTag ?> class="blog-article__title"><?= htmlspecialchars((string) ($blogPost['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></<?= $blogHeadingTag ?>>
          <?php if (!empty($blogPost['lead'])): ?>
            <p class="blog-article__lead"><?= htmlspecialchars((string) $blogPost['lead'], ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
        </header>
        <div class="blog-article__body">
          <?php foreach (($blogPost['sections'] ?? []) as $section): ?>
            <?php if (is_array($section)): ?>
              <?= renderBlogSection($section) ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <?php if ($blogRelated !== []): ?>
          <aside class="blog-related" aria-label="Читайте также">
            <h2 class="blog-related__title">Читайте также</h2>
            <ul class="blog-related__list">
              <?php foreach ($blogRelated as $related): ?>
                <li>
                  <a href="<?= htmlspecialchars((string) ($related['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars((string) ($related['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          </aside>
        <?php endif; ?>
        <?php require __DIR__ . '/partials/social-links.php'; ?>
      </article>
      <?php endif; ?>
    </section>

    <!-- Оплата -->
    <section class="screen<?= $screenActive('payment') ?>" id="screen-payment"<?= $screenHidden('payment') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-payment">← На главную</a>
      </div>
      <div class="payment-page pick-panel">
        <?php require __DIR__ . '/partials/payment-page.php'; ?>
        <?php require __DIR__ . '/partials/social-links.php'; ?>
      </div>
    </section>

    <!-- Публичная оферта -->
    <section class="screen<?= $screenActive('offer') ?>" id="screen-offer"<?= $screenHidden('offer') ?>>
      <div class="screen-header">
        <a href="/payment" class="btn-back" id="btn-back-offer">← К оплате</a>
      </div>
      <div class="payment-page pick-panel">
        <?php require __DIR__ . '/partials/offer-page.php'; ?>
        <?php require __DIR__ . '/partials/social-links.php'; ?>
      </div>
    </section>

    <!-- Успешная оплата -->
    <section class="screen<?= $screenActive('payment-success') ?>" id="screen-payment-success"<?= $screenHidden('payment-success') ?>>
      <div class="payment-success pick-panel">
        <div class="payment-success__icon icon-badge icon-badge--success" aria-hidden="true">
          <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
        </div>
        <h2 class="payment-success__title">Подписка активирована</h2>
        <p class="payment-success__text">Теперь PianoBro будет сохранять ваш прогресс, находить слабые ноты и подбирать персональные тренировки.</p>
        <button type="button" class="btn btn--primary btn--lg" id="btn-payment-success-start">Начать тренировку</button>
        <a href="/payment" class="payment-success__link">Посмотреть тарифы</a>
      </div>
    </section>

    <!-- Персональный план -->
    <section class="screen<?= $screenActive('personal-plan') ?>" id="screen-personal-plan"<?= $screenHidden('personal-plan') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-personal-plan">← На главную</a>
        <h2 class="screen-header__title">Персональный план</h2>
      </div>
      <div class="personal-plan pick-panel" id="personal-plan-panel">
        <p class="personal-plan__loading">Загрузка…</p>
      </div>
    </section>

    <!-- Статистика -->
    <section class="screen<?= $screenActive('stats') ?>" id="screen-stats"<?= $screenHidden('stats') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-stats">← Назад</a>
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

    <!-- Домашние задания -->
    <section class="screen<?= $screenActive('homework') ?>" id="screen-homework"<?= $screenHidden('homework') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-homework">← Назад</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--roadmap" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-target"/></svg>
          </span>
          Домашние задания
        </h2>
      </div>
      <div class="pick-panel homework-panel" id="homework-panel">
        <p class="loading">Загрузка…</p>
      </div>
    </section>

    <!-- Кабинет преподавателя -->
    <section class="screen<?= $screenActive('teacher') ?>" id="screen-teacher"<?= $screenHidden('teacher') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-teacher">← Назад</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--teacher" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-user"/></svg>
          </span>
          Кабинет преподавателя
        </h2>
      </div>
      <div class="pick-panel teacher-access-gate" id="teacher-access-gate"<?= $isTeacher ? ' hidden' : '' ?>>
        <?php if (!$isLoggedIn): ?>
        <section class="admin-card">
          <h2 class="admin-card__title">Нужен вход</h2>
          <p>Войдите в аккаунт преподавателя, чтобы управлять учениками и назначать задания.</p>
          <button type="button" class="btn btn--primary" id="btn-teacher-login">Войти</button>
        </section>
        <?php else: ?>
        <section class="admin-card admin-card--warn">
          <h2 class="admin-card__title">Нет доступа</h2>
          <p>У аккаунта <strong><?= htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8') ?></strong> нет роли преподавателя.</p>
          <p class="admin-footnote">При регистрации отметьте «Вы педагог?» или попросите администратора назначить роль в <a href="/admin">админ-панели</a>.</p>
          <a href="/" class="btn btn--secondary btn--sm">На главную</a>
        </section>
        <?php endif; ?>
      </div>
      <?php if ($isTeacher): ?>
      <div id="teacher-app" class="teacher-layout">
        <aside class="teacher-sidebar">
          <section class="admin-card teacher-invite-card">
            <h2 class="admin-card__title">Пригласить ученика</h2>
            <form id="form-invite-student" class="teacher-form">
              <label class="teacher-form__field teacher-form__field--wide">
                <span>Email ученика</span>
                <input type="email" name="email" required placeholder="student@example.com" autocomplete="email">
              </label>
              <button type="submit" class="btn btn--primary">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-send"/></svg>
                Отправить приглашение
              </button>
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
            <p>Выберите ученика слева — откроются вкладки с обзором, нотами, занятиями и домашкой.</p>
          </section>
        </main>
      </div>
      <?php endif; ?>
    </section>

    <!-- Выбор мелодии -->
    <section class="screen<?= $screenActive('melody-pick') ?>" id="screen-melody-pick"<?= $screenHidden('melody-pick') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-melody">← Назад</a>
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
    <section class="screen<?= $screenActive('roadmap') ?>" id="screen-roadmap"<?= $screenHidden('roadmap') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-roadmap">← Назад</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--roadmap" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-leaf"/></svg>
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
        <p class="roadmap-lead" id="roadmap-lead">Проходите уровни по порядку: сначала закрепите все ноты уровня, затем сыграйте мелодию — так знания переходят в музыкальную практику.</p>
        <div class="roadmap-guest-hint" id="roadmap-guest-hint" hidden>
          <p>Войдите в аккаунт, чтобы сохранить прогресс на всех устройствах.</p>
          <button type="button" class="btn btn--secondary btn--sm" id="btn-roadmap-login">Войти</button>
        </div>
        <div class="roadmap-path" id="roadmap-path" aria-live="polite"></div>
      </div>
    </section>

    <!-- Настройки тренажёра нот -->
    <section class="screen<?= $screenActive('notes-pick') ?>" id="screen-notes-pick"<?= $screenHidden('notes-pick') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-notes">← Назад</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--notes" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-notes"/></svg>
          </span>
          <span id="notes-pick-title">Настройки тренажёра</span>
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

          <fieldset class="settings-group settings-group--session" id="notes-session-group">
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
            <p class="settings-hint notes-free-tier-hint" id="notes-free-tier-hint" hidden></p>
            <p class="settings-error" id="notes-settings-error" hidden></p>
            <div class="notes-settings__footer-actions">
              <p class="settings-hint" id="notes-pick-hint">Отметьте, что хотите тренировать, и нажмите «Начать».</p>
              <button type="submit" class="btn btn--primary notes-settings__submit" id="btn-start-notes">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-play"/></svg>
                Начать тренировку
              </button>
            </div>
          </div>
        </form>
      </div>
    </section>

    <!-- Настройки ритм-игры -->
    <section class="screen<?= $screenActive('rhythm-pick') ?>" id="screen-rhythm-pick"<?= $screenHidden('rhythm-pick') ?>>
      <div class="screen-header">
        <a href="/" class="btn-back" id="btn-back-rhythm">← Назад</a>
        <h2 class="screen-header__title">
          <span class="screen-header__icon icon-badge icon-badge--accent" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-play"/></svg>
          </span>
          Ритм-игра
        </h2>
      </div>
      <div class="pick-panel">
        <form class="notes-settings" id="rhythm-settings-form">
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

          <fieldset class="settings-group settings-group--session settings-group--rhythm-game">
            <legend class="settings-group__head">
              <span class="settings-group__icon icon-badge icon-badge--session"><svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-session"/></svg></span>
              <span class="settings-group__title">Длительности нот</span>
            </legend>
            <div class="rhythm-pick-grid rhythm-pick-grid--durations" role="group" aria-label="Длительности нот">
              <label class="rhythm-pick rhythm-pick--duration" title="Целая">
                <input type="checkbox" name="dur-whole" aria-label="Целая">
                <span class="rhythm-pick__surface">
                  <span class="rhythm-duration-icon rhythm-duration-icon--svg" aria-hidden="true">
                    <svg class="icon" viewBox="0 0 24 24"><use href="#ico-note-whole"/></svg>
                  </span>
                  <span class="rhythm-pick__title">Целая</span>
                </span>
              </label>
              <label class="rhythm-pick rhythm-pick--duration" title="Половинная">
                <input type="checkbox" name="dur-half" checked aria-label="Половинная">
                <span class="rhythm-pick__surface">
                  <span class="rhythm-duration-icon rhythm-duration-icon--svg" aria-hidden="true">
                    <svg class="icon" viewBox="0 0 24 24"><use href="#ico-note-half"/></svg>
                  </span>
                  <span class="rhythm-pick__title">Половинная</span>
                </span>
              </label>
              <label class="rhythm-pick rhythm-pick--duration" title="Четверть">
                <input type="checkbox" name="dur-quarter" checked aria-label="Четверть">
                <span class="rhythm-pick__surface">
                  <span class="rhythm-duration-icon" aria-hidden="true">♩</span>
                  <span class="rhythm-pick__title">Четверть</span>
                </span>
              </label>
              <label class="rhythm-pick rhythm-pick--duration" title="Восьмая">
                <input type="checkbox" name="dur-eighth" aria-label="Восьмая">
                <span class="rhythm-pick__surface">
                  <span class="rhythm-duration-icon" aria-hidden="true">♪</span>
                  <span class="rhythm-pick__title">Восьмая</span>
                </span>
              </label>
              <label class="rhythm-pick rhythm-pick--duration" title="Шестнадцатая">
                <input type="checkbox" name="dur-sixteenth" aria-label="Шестнадцатая">
                <span class="rhythm-pick__surface">
                  <span class="rhythm-duration-icon" aria-hidden="true">♬</span>
                  <span class="rhythm-pick__title">Шестнадцатая</span>
                </span>
              </label>
            </div>
            <div class="rhythm-options-row">
              <div class="rhythm-option">
                <label class="rhythm-option__label" for="rhythm-speed">Скорость игры</label>
                <select id="rhythm-speed" name="rhythm-speed" class="rhythm-option__select">
                  <option value="very_slow">Очень медленно</option>
                  <option value="slow" selected>Медленно</option>
                  <option value="medium">Средне</option>
                  <option value="fast">Быстро</option>
                </select>
              </div>
              <div class="rhythm-option">
                <label class="rhythm-option__label" for="rhythm-lives">Допустимые ошибки</label>
                <select id="rhythm-lives" name="rhythm-lives" class="rhythm-option__select">
                  <option value="1">1 жизнь</option>
                  <option value="2">2 жизни</option>
                  <option value="3" selected>3 жизни</option>
                  <option value="5">5 жизней</option>
                  <option value="10">10 жизней</option>
                </select>
              </div>
            </div>
          </fieldset>
          </div>

          <div class="notes-settings__footer">
            <p class="settings-error" id="rhythm-settings-error" hidden></p>
            <div class="notes-settings__footer-actions">
              <p class="settings-hint">Ноты движутся по стану, синяя линия ползёт слева направо — нажимайте клавишу, когда нота доходит до линии. Каждая ошибка отнимает жизнь.</p>
              <button type="submit" class="btn btn--primary notes-settings__submit" id="btn-start-rhythm">
                <svg class="icon icon--btn" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-play"/></svg>
                Начать игру
              </button>
            </div>
          </div>
        </form>
      </div>
    </section>

    <!-- Тренировка -->
    <section class="screen screen--practice<?= $screenActive('practice') ?>" id="screen-practice"<?= $screenHidden('practice') ?>>
      <div class="practice-topbar">
        <button type="button" class="btn-back" id="btn-back-practice" aria-label="Назад">←</button>
        <h2 class="practice-topbar__title" id="practice-title">Тренировка</h2>
        <div class="runner-lives" id="runner-lives" hidden aria-label="Жизни"></div>
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

      <p class="practice-quota" id="practice-quota" hidden></p>

      <div class="practice-feedback-wrap">
        <div class="practice-feedback" id="practice-feedback" aria-live="polite"></div>
      </div>

      <div class="practice-controls" id="practice-controls" hidden>
        <div class="practice-controls__row">
          <div class="practice-preview" id="melody-preview-panel" hidden>
            <button type="button" class="practice-preview__btn" id="btn-preview-melody" aria-pressed="false">
              <svg class="icon icon--btn practice-preview__icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#ico-volume"/></svg>
              <span class="practice-preview__label">Прослушать</span>
            </button>
          </div>

          <div class="practice-controls__settings">
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
              <button type="button" class="keyboard-mode__tab" data-hints="on">С подсказками</button>
              <button type="button" class="keyboard-mode__tab keyboard-mode__tab--active" data-hints="off">Без подсказок</button>
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
      </div>

      <div class="practice-layout practice-layout--keyboard-hidden">
        <div class="practice-staff staff-viewport" id="staff-viewport">
          <div class="runner-hit-line" id="runner-hit-line" hidden aria-hidden="true"></div>
          <div class="runner-countdown" id="runner-countdown" hidden aria-hidden="true"></div>
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
          <div class="auth-teacher-option" id="auth-teacher-option">
            <label class="auth-teacher-option__toggle">
              <input type="checkbox" name="is_teacher" value="1">
              <span class="auth-teacher-option__title">Вы педагог?</span>
            </label>
            <p class="auth-teacher-option__hint">
              Откроется раздел «Ученики»: вы сможете приглашать учеников по email и назначать им задания.
            </p>
          </div>
          <p class="auth-error" id="auth-error-register" hidden></p>
          <button type="submit" class="btn btn--primary auth-form__submit">Зарегистрироваться</button>
        </form>
      </div>
    </div>

    <!-- Paywall -->
    <div class="modal" id="paywall-modal" hidden>
      <div class="modal__backdrop" data-close-paywall></div>
      <div class="modal__card modal__card--paywall" role="dialog" aria-labelledby="paywall-title">
        <button type="button" class="modal__close" id="paywall-close" aria-label="Закрыть">×</button>
        <h2 class="modal__title" id="paywall-title">Откройте персональные тренировки</h2>
        <div class="paywall-weak" id="paywall-weak" hidden>
          <p class="paywall-weak__label">Сложные ноты:</p>
          <div class="paywall-weak__tags" id="paywall-weak-tags"></div>
        </div>
        <p class="modal__text modal__text--muted" id="paywall-quota" hidden></p>
        <p class="modal__text" id="paywall-message">PianoBro будет запоминать ваши ошибки, чаще повторять сложные ноты и показывать прогресс, чтобы вы быстрее читали ноты на пианино.</p>
        <button type="button" class="btn btn--primary" id="paywall-choose-plan">Выбрать тариф</button>
      </div>
    </div>

    <!-- Результат диагностики -->
    <div class="modal" id="diagnostic-modal" hidden>
      <div class="modal__backdrop" data-close-diagnostic></div>
      <div class="modal__card modal__card--diagnostic" role="dialog" aria-labelledby="diagnostic-title">
        <h2 class="modal__title" id="diagnostic-title">Результат диагностики</h2>
        <div class="modal__stats modal__stats--diagnostic">
          <div class="modal-stat">
            <span class="modal-stat__label">Верно</span>
            <span class="modal-stat__value modal-stat__value--success" id="diagnostic-correct">0</span>
          </div>
          <div class="modal-stat">
            <span class="modal-stat__label">Ошибки</span>
            <span class="modal-stat__value modal-stat__value--error" id="diagnostic-wrong">0</span>
          </div>
          <div class="modal-stat">
            <span class="modal-stat__label">Точность</span>
            <span class="modal-stat__value" id="diagnostic-accuracy">—</span>
          </div>
        </div>
        <div class="diagnostic-result__weak weak-notes-offer" id="diagnostic-weak-notes">
          <div class="weak-notes-offer__content">
            <div class="weak-notes-offer__text">
              <strong id="diagnostic-weak-title">Сложнее всего давались</strong>
              <p id="diagnostic-weak-hint">PianoBro чаще будет повторять эти ноты в тренировках:</p>
            </div>
            <div class="weak-notes-offer__tags" id="diagnostic-weak-tags"></div>
          </div>
        </div>
        <div class="diagnostic-offer" id="diagnostic-offer">
          <strong>Персональный план</strong>
          <p>Сервис составит программу тренировок и будет чаще показывать ноты, которые пока путаются.</p>
        </div>
        <div class="modal__actions">
          <button type="button" class="btn btn--primary" id="diagnostic-open-plan">Открыть персональный план</button>
          <button type="button" class="btn btn--secondary" id="diagnostic-close">Закрыть</button>
        </div>
      </div>
    </div>

    <!-- Итоги тренировки -->
    <div class="modal" id="session-modal" hidden>
      <div class="modal__backdrop" data-close-session></div>
      <div class="modal__card modal__card--session" id="session-modal-card" role="dialog" aria-labelledby="modal-title">
        <div class="session-modal-classic" id="session-modal-classic">
          <div class="modal__icon icon-badge icon-badge--success" aria-hidden="true">
            <svg class="icon icon--badge" viewBox="0 0 24 24"><use href="#ico-check"/></svg>
          </div>
        </div>
        <h2 class="modal__title" id="modal-title">Тренировка завершена!</h2>
        <div class="modal__stats" id="session-modal-stats">
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
        <div class="session-modal-training-result" id="session-modal-training-result" hidden>
          <div class="diagnostic-result__weak weak-notes-offer" id="modal-weak-notes">
            <div class="weak-notes-offer__content">
              <div class="weak-notes-offer__text">
                <strong id="modal-weak-notes-title">Сложнее всего давались</strong>
                <p id="modal-weak-notes-hint">PianoBro чаще будет повторять эти ноты в тренировках:</p>
              </div>
              <div class="weak-notes-offer__tags" id="modal-weak-notes-tags"></div>
            </div>
          </div>
          <div class="diagnostic-offer" id="modal-session-offer">
            <strong>Персональный план</strong>
            <p>Сервис составит программу тренировок и будет чаще показывать ноты, которые пока путаются.</p>
          </div>
          <div class="modal__actions">
            <button type="button" class="btn btn--primary" id="modal-open-plan">Открыть персональный план</button>
            <button type="button" class="btn btn--secondary" id="modal-close-training">Закрыть</button>
          </div>
        </div>
        <ul class="modal-register-hint" id="modal-register-hint" hidden></ul>
        <div class="modal-discover" id="modal-discover" hidden></div>
        <div class="modal__actions" id="modal-actions"></div>
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
    <symbol id="ico-note-whole" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75">
      <ellipse cx="12" cy="14.5" rx="8" ry="5.5"/>
    </symbol>
    <symbol id="ico-note-half" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round">
      <ellipse cx="9.5" cy="15" rx="5.5" ry="4"/>
      <path d="M14 12.5V3"/>
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
    <symbol id="ico-leaf" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M12 21c-5.5-4.5-8.5-8.5-8.5-13.5C3.5 4 7 2 12 2s8.5 2 8.5 5.5C20.5 12.5 17.5 16.5 12 21z"/>
      <path d="M12 21V9"/>
      <path d="M8.5 10.5C10 12 12 12.5 12 12.5s2-.5 3.5-2"/>
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
    <symbol id="ico-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </symbol>
    <symbol id="ico-homework" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
      <path d="M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/><path d="M9 14l2 2 4-4"/>
    </symbol>
    <symbol id="ico-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/>
    </symbol>
    <symbol id="ico-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>
    </symbol>
    <symbol id="ico-login" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M16 12H8"/><path d="M21 9l-3 3 3 3"/>
    </symbol>
    <symbol id="ico-logout" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M15 12H8"/><path d="M18 9l3 3-3 3"/>
    </symbol>
    <symbol id="ico-midi" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M5 5h14v14H5z"/><path d="M9 9v6M15 9v6M12 7v10"/>
    </symbol>
    <symbol id="ico-play" viewBox="0 0 24 24">
      <polygon points="8,5 20,12 8,19" fill="currentColor"/>
    </symbol>
    <symbol id="ico-volume" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
      <path d="M11 5L6 9H3v6h3l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18 6a8.5 8.5 0 0 1 0 12"/>
    </symbol>
    <symbol id="ico-stop" viewBox="0 0 24 24">
      <rect x="7" y="7" width="10" height="10" rx="1.5" fill="currentColor"/>
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
    <symbol id="ico-vk" viewBox="0 0 24 24">
      <path fill="currentColor" d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.408 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.525-2.049-1.727-1.033-1-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.678-1.253.678-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4.03 8.57 4.03 8.096c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.78.677.863 2.049 2.303 3.896 2.896 3.896.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.203.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .373.17.508.271.508.22 0 .407-.136.813-.542 1.254-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.763-.491h1.744c.525 0 .644.271.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.049.17.49-.085.744-.576.744z"/>
    </symbol>
    <symbol id="ico-telegram" viewBox="0 0 24 24">
      <path fill="currentColor" d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.693-1.653-1.124-2.678-1.8-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.241-1.865-.44-.752-.244-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.14.121.099.154.232.17.326.016.094.036.308.02.475z"/>
    </symbol>
    <symbol id="ico-instagram" viewBox="0 0 24 24">
      <path fill="currentColor" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
    </symbol>
  </svg>

  <?php require __DIR__ . '/partials/cookie-banner.php'; ?>
  <?php require __DIR__ . '/partials/js-import-map.php'; ?>
  <script type="module" src="<?= htmlspecialchars(\PianoTrainer\AssetVersion::versionedUrl('/assets/js/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
