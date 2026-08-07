<?php

declare(strict_types=1);

namespace PianoTrainer;

final class PageRegistry
{
  private const SITE_NAME = 'Piano Bro';

  /** Частотные запросы по теме «тренажёр нот» (для meta keywords). */
  private const NOTE_TRAINER_KEYWORDS = 'тренажер нот, тренажёр нот, ноты тренажер онлайн, тренажер для запоминания нот, чтение нот тренажер, тренажер угадай ноту, тренажер пианино ноты, нотный тренажер для нот, ноты на слух тренажер, тренажер для нот на нотном стане, угадай ноту онлайн тренажер, тренажер попадания в ноты, тренажер изучение нот, тренажер басовых нот, тренажер нот фортепиано онлайн, тренажер определения нот, онлайн тренажер запоминания нот, тренажер чтения нот с листа, тренажер для нот басового ключа, пианино тренажер для запоминания нот, пианино тренажер нот онлайн, тренажер распознавания нот, тренажер по нотам фортепиано, тренажер для запоминания нот на нотном стане, тренажер попадания в ноты онлайн';

  /**
   * @return array<string, mixed>|null
   */
  public static function match(string $path, ?LessonRepository $lessons = null): ?array
  {
    $path = rtrim($path, '/') ?: '/';

    return match (true) {
      $path === '/' => self::home(),
      $path === '/put-novichka' => self::roadmap(),
      $path === '/noty' => self::notes(),
      $path === '/melodii' => self::melodies(),
      $path === '/statistika' => self::stats(),
      $path === '/trenirovka/noty' => self::practiceNotes(),
      preg_match('#^/melodii/([a-z0-9\-]+)$#', $path, $m) === 1 => self::melodyDetail($m[1], $lessons),
      preg_match('#^/trenirovka/melodiya/([a-z0-9\-]+)$#', $path, $m) === 1 => self::practiceMelody($m[1], $lessons),
      default => null,
    };
  }

  /** @return list<string> */
  public static function sitemapPaths(LessonRepository $lessons): array
  {
    $paths = [
      '/',
      '/put-novichka',
      '/noty',
      '/melodii',
    ];

    foreach ($lessons->all() as $lesson) {
      $paths[] = '/melodii/' . $lesson->id;
    }

    return $paths;
  }

  /** @return array<string, mixed> */
  private static function home(): array
  {
    return self::base(
      screen: 'home',
      path: '/',
      title: 'Тренажёр нот онлайн — пианино, нотный стан, MIDI | Piano Bro',
      description: 'Бесплатный тренажёр нот и пианино онлайн: угадай ноту на нотном стане, запоминание, чтение с листа, попадание в клавиши. Скрипичный и басовый ключ, MIDI, мелодии.',
      keywords: self::NOTE_TRAINER_KEYWORDS . ', обучение пианино, MIDI клавиатура, игра на пианино онлайн',
      jsonLd: [
        '@context' => 'https://schema.org',
        '@type' => 'WebApplication',
        'name' => 'Тренажёр нот онлайн — Piano Bro',
        'description' => 'Онлайн-тренажёр для запоминания и чтения нот на нотном стане, тренировки на MIDI-клавиатуре и фортепиано.',
        'applicationCategory' => 'EducationalApplication',
        'operatingSystem' => 'Web',
        'inLanguage' => 'ru',
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'RUB'],
      ],
    );
  }

  /** @return array<string, mixed> */
  private static function roadmap(): array
  {
    return self::base(
      screen: 'roadmap',
      path: '/put-novichka',
      title: 'Тренажёр изучения нот — пошаговый курс с нуля | Piano Bro',
      description: 'Пошаговое изучение и чтение нот с листа: 8 уровней от белых нот до полного диапазона. Скрипичный и басовый ключ, тренажёр запоминания нот, мелодии-закрепление.',
      keywords: 'тренажер изучение нот, тренажер чтения нот с листа, обучение нотам пошагово, тренажер для запоминания нот, путь новичка пианино',
      seoIntro: [
        'h1' => 'Тренажёр изучения нот — путь новичка',
        'lead' => 'Пошаговое обучение нотам с нуля: 8 уровней от белых нот до полного диапазона. Скрипичный и басовый ключ, запоминание нот и мелодии-закрепление на каждом этапе.',
      ],
      jsonLd: [
        '@context' => 'https://schema.org',
        '@type' => 'Course',
        'name' => 'Тренажёр изучения нот — путь новичка',
        'description' => 'Пошаговая программа из 8 уровней: запоминание нот, чтение с листа, скрипичный и басовый ключ.',
        'provider' => ['@type' => 'Organization', 'name' => self::SITE_NAME],
        'inLanguage' => 'ru',
        'isAccessibleForFree' => true,
      ],
    );
  }

  /** @return array<string, mixed> */
  private static function notes(): array
  {
    return self::base(
      screen: 'notes-pick',
      path: '/noty',
      title: 'Тренажёр нот онлайн — угадай ноту на нотном стане | Piano Bro',
      description: 'Бесплатный нотный тренажёр: угадай ноту на стане, тренируй попадание в клавиши, запоминание и чтение нот с листа. Скрипичный и басовый ключ, фортепиано, MIDI — без установки.',
      keywords: self::NOTE_TRAINER_KEYWORDS,
      seoIntro: [
        'h1' => 'Тренажёр нот онлайн',
        'lead' => 'Нотный тренажёр для запоминания и чтения нот с листа: на экране — нота на стане, вы нажимаете клавишу на MIDI-клавиатуре, микрофоне или экранном пианино. Подходит как тренажёр «угадай ноту», попадания в ноты и определения нот на слух.',
        'features' => [
          'Скрипичный ключ, басовый ключ и басовые ноты — отдельные режимы',
          'Тренажёр попадания в ноты: мгновенная обратная связь «верно / неверно»',
          'Чтение нот с листа и запоминание — статистика по каждой ноте',
          'Фортепиано онлайн: MIDI, микрофон или клавиатура компьютера',
        ],
      ],
      jsonLd: [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [
          [
            '@type' => 'Question',
            'name' => 'Что такое тренажёр нот онлайн?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'Это интерактивный нотный тренажёр: на нотном стане показывается нота, а вы находите её на клавиатуре фортепиано. Подходит для запоминания нот, чтения с листа и тренировки попадания в нужную клавишу.',
            ],
          ],
          [
            '@type' => 'Question',
            'name' => 'Можно ли тренировать басовый ключ и басовые ноты?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'Да. В настройках включается басовый ключ — малая и большая октава. Это полноценный тренажёр для нот басового ключа и распознавания нот в нижнем регистре.',
            ],
          ],
          [
            '@type' => 'Question',
            'name' => 'Нужно ли устанавливать программу?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'Нет. Тренажёр нот фортепиано онлайн работает в браузере бесплатно. Можно подключить MIDI-клавиатуру или играть на экранном пианино.',
            ],
          ],
          [
            '@type' => 'Question',
            'name' => 'Чем отличается от тренажёра «угадай ноту»?',
            'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => 'Принцип тот же: определите ноту на стане и нажмите правильную клавишу. Дополнительно ведётся статистика, есть пошаговый путь обучения и режимы для скрипичного и басового ключа.',
            ],
          ],
        ],
      ],
    );
  }

  /** @return array<string, mixed> */
  private static function melodies(): array
  {
    return self::base(
      screen: 'melody-pick',
      path: '/melodii',
      title: 'Мелодии для пианино — каталог песен и классики | Piano Bro',
      description: 'Twinkle, Super Mario, «К Элизе», Ludovico Einaudi и другие мелодии. Прослушивание, нотный стан и подсказки на клавишах.',
      keywords: 'мелодии для пианино, ноты популярных песен, фортепиано онлайн, классика для начинающих',
      seoIntro: [
        'h1' => 'Мелодии для пианино онлайн',
        'lead' => 'Каталог мелодий для фортепиано: популярные песни, классика и саундтреки. Прослушивание, нотный стан и подсветка клавиш — играйте в браузере без установки.',
      ],
    );
  }

  /** @return array<string, mixed> */
  private static function stats(): array
  {
    return self::base(
      screen: 'stats',
      path: '/statistika',
      title: 'Статистика прогресса по нотам | Piano Bro',
      description: 'Карта выученных нот на нотном стане, график занятий по дням и тренировка слабых мест.',
      keywords: 'статистика обучения пианино, прогресс нот',
      robots: 'noindex, follow',
    );
  }

  /** @return array<string, mixed> */
  private static function practiceNotes(): array
  {
    return self::base(
      screen: 'practice',
      path: '/trenirovka/noty',
      title: 'Тренажёр попадания в ноты — тренировка | Piano Bro',
      description: 'Интерактивная тренировка: определение нот на нотном стане и попадание в клавиши фортепиано онлайн.',
      keywords: 'тренажер попадания в ноты онлайн, тренажер угадай ноту, тренажер распознавания нот',
      robots: 'noindex, follow',
      boot: [
        'mode' => 'notes',
        'returnPath' => '/noty',
      ],
    );
  }

  /**
   * @return array<string, mixed>|null
   */
  private static function melodyDetail(string $id, ?LessonRepository $lessons): ?array
  {
    if ($lessons === null) {
      return null;
    }

    $lesson = $lessons->find($id);
    if ($lesson === null) {
      return null;
    }

    $difficulty = self::difficultyLabel($lesson->difficulty);
    $composer = $lesson->composer !== '' ? $lesson->composer : 'Неизвестный автор';

    return self::base(
      screen: 'melody-pick',
      path: '/melodii/' . $lesson->id,
      title: "{$lesson->title} — ноты для пианино | Piano Bro",
      description: "Научитесь играть «{$lesson->title}» ({$composer}) на пианино онлайн. Сложность: {$difficulty}. Нотный стан, прослушивание и подсказки.",
      keywords: "{$lesson->title}, {$composer}, ноты для пианино, мелодия для начинающих",
      boot: [
        'focusLessonId' => $lesson->id,
        'redirectToPractice' => true,
      ],
      lesson: [
        'id' => $lesson->id,
        'title' => $lesson->title,
        'composer' => $composer,
        'difficulty' => $difficulty,
        'noteCount' => $lesson->toArray()['noteCount'],
        'twoHands' => $lesson->twoHands,
      ],
      jsonLd: [
        '@context' => 'https://schema.org',
        '@type' => 'MusicComposition',
        'name' => $lesson->title,
        'composer' => ['@type' => 'Person', 'name' => $composer],
        'inLanguage' => 'ru',
      ],
    );
  }

  /**
   * @return array<string, mixed>|null
   */
  private static function practiceMelody(string $id, ?LessonRepository $lessons): ?array
  {
    if ($lessons === null) {
      return null;
    }

    $lesson = $lessons->find($id);
    if ($lesson === null) {
      return null;
    }

    return self::base(
      screen: 'practice',
      path: '/trenirovka/melodiya/' . $lesson->id,
      title: "Играть: {$lesson->title} | Piano Bro",
      description: "Тренировка мелодии «{$lesson->title}» на пианино онлайн.",
      robots: 'noindex, follow',
      boot: [
        'mode' => 'melody',
        'lessonId' => $lesson->id,
        'returnPath' => '/melodii',
      ],
    );
  }

  /**
   * @param array<string, mixed>|null $boot
   * @param array<string, mixed>|null $jsonLd
   * @param array<string, mixed>|null $lesson
   * @param array<string, mixed>|null $seoIntro
   * @return array<string, mixed>
   */
  private static function base(
    string $screen,
    string $path,
    string $title,
    string $description,
    string $keywords = '',
    string $robots = 'index, follow',
    ?array $boot = null,
    ?array $jsonLd = null,
    ?array $lesson = null,
    ?array $seoIntro = null,
  ): array {
    $page = [
      'screen' => $screen,
      'path' => $path,
      'title' => $title,
      'description' => $description,
      'keywords' => $keywords,
      'robots' => $robots,
      'ogTitle' => $title,
      'ogDescription' => $description,
      'boot' => array_merge(['screen' => $screen], $boot ?? []),
      'jsonLd' => $jsonLd,
      'lesson' => $lesson,
      'seoIntro' => $seoIntro,
    ];

    return $page;
  }

  private static function difficultyLabel(string $difficulty): string
  {
    return match ($difficulty) {
      'beginner' => 'начальный',
      'intermediate' => 'средний',
      'advanced' => 'продвинутый',
      default => $difficulty,
    };
  }
}
