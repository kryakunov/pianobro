<?php

declare(strict_types=1);

namespace PianoTrainer;

final class MelodySeo
{
  /**
   * @return array{
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   h1: string,
   *   lead: string,
   *   facts: list<string>,
   *   sections: list<array{h2: string, paragraphs: list<string>}>,
   *   faqs: list<array{q: string, a: string}>,
   *   notes: list<array{name: string, digit: string, octave: string}>,
   *   opening: string
   * }
   */
  public static function forLesson(Lesson $lesson, string $difficulty, string $composer, string $notesLabel, string $hands): array
  {
    $rows = self::noteRows($lesson);
    $opening = self::openingLine($rows);

    if ($lesson->id === 'fur-elise') {
      return self::furElise($rows, $opening, $notesLabel, $hands);
    }

    $profile = MelodySeoProfiles::forId($lesson->id, $opening, $notesLabel, $hands, $difficulty);
    if ($profile !== null) {
      $profile['notes'] = $rows;
      $profile['opening'] = $opening;

      return $profile;
    }

    $who = $composer !== '' ? $composer . '. ' : '';
    $h1 = 'Ноты «' . $lesson->title . '» для фортепиано';

    return [
      'title' => $h1 . ' | Piano Bro',
      'description' => 'Ноты «' . $lesson->title . '» для фортепиано и пианино. ' . $who . 'Уровень — ' . $difficulty . ', ' . $notesLabel . ', ' . $hands . '. Названия, цифры и кнопка «Сыграть ноты».',
      'keywords' => implode(', ', array_filter([
        'ноты ' . $lesson->title,
        $lesson->title . ' ноты для фортепиано',
        $lesson->title . ' ноты для пианино',
        $lesson->title . ' ноты для начинающих',
        $composer,
      ])),
      'h1' => $h1,
      'lead' => $who . 'Уровень — ' . $difficulty . ', ' . $notesLabel . ', ' . $hands . '. Кнопка открывает стан: следующая клавиша подсвечивается.',
      'facts' => array_values(array_filter([$composer, $difficulty, $notesLabel, $hands, 'для фортепиано'])),
      'sections' => [
        [
          'h2' => 'Как сыграть на пианино',
          'paragraphs' => [
            'Нажмите «Сыграть ноты». Партия откроется на нотном стане в браузере: без установки и без файла. Подойдут цифровое пианино, синтезатор или клавиши компьютера.',
          ],
        ],
      ],
      'faqs' => [
        [
          'q' => 'Это ноты «' . $lesson->title . '» для начинающих?',
          'a' => 'Уровень партии — ' . $difficulty . ', ' . $hands . ', ' . $notesLabel . '. На стане подписывается следующая клавиша, поэтому фрагмент можно разбирать с нуля.',
        ],
        [
          'q' => 'Можно ли скачать ноты «' . $lesson->title . '»?',
          'a' => 'Отдельного PDF нет. Ноты открываются бесплатно в браузере: их можно сразу сыграть, а не скачивать.',
        ],
      ],
      'notes' => $rows,
      'opening' => $opening,
    ];
  }

  /**
   * @param list<array{name: string, digit: string, octave: string}> $rows
   * @return array{
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   h1: string,
   *   lead: string,
   *   facts: list<string>,
   *   sections: list<array{h2: string, paragraphs: list<string>}>,
   *   faqs: list<array{q: string, a: string}>,
   *   notes: list<array{name: string, digit: string, octave: string}>,
   *   opening: string
   * }
   */
  private static function furElise(array $rows, string $opening, string $notesLabel, string $hands): array
  {
    return [
      'title' => 'К Элизе — ноты для фортепиано, Бетховен | Piano Bro',
      'description' => 'Ноты «К Элизе» Людвига ван Бетховена для фортепиано и пианино: облегчённый вариант для начинающих, ' . $notesLabel . ', ' . $hands . '. С подписями и по цифрам. Сыграйте бесплатно в браузере, без скачивания.',
      'keywords' => 'к элизе ноты, к элизе ноты для фортепиано, бетховен к элизе ноты, к элизе ноты для начинающих, к элизе ноты на пианино, к элизе ноты по цифрам, к элизе ноты легкие, письмо к элизе ноты',
      'h1' => 'К Элизе — ноты для фортепиано',
      'lead' => 'Людвиг ван Бетховен. Здесь облегчённый вариант знаменитой темы для начинающих: ' . $notesLabel . ', ' . $hands . ', для пианино и синтезатора. Это не полная пьеса и не табы для гитары — короткая партия, которую можно сразу сыграть.',
      'facts' => ['Людвиг ван Бетховен', 'для начинающих', $notesLabel, $hands, 'облегчённый вариант'],
      'sections' => [
        [
          'h2' => 'Облегчённый вариант для начинающих',
          'paragraphs' => [
            'В поиске «К Элизе» чаще всего нужна не вся пьеса, а узнаваемое начало. На этой странице как раз оно: простая версия для фортепиано, одна рука, без аккомпанемента левой.',
            'В теме есть ре-диез, поэтому это лёгкая, но не совсем «нулевая» мелодия. Если названия нот ещё путаются, откройте тренажёр нот, а затем вернитесь к «К Элизе».',
          ],
        ],
        [
          'h2' => 'Скачать, распечатать или сыграть',
          'paragraphs' => [
            'Файла для скачивания нет: ноты «К Элизе» бесплатно открываются в браузере. Кнопка «Сыграть ноты» показывает стан и подсвечивает клавишу. Печатать PDF не нужно — партия короткая, её проще пройти на экране.',
            'Та же правая рука подходит для синтезатора. Для гитары здесь нет табулатуры: это ноты для фортепиано и пианино.',
          ],
        ],
      ],
      'faqs' => [
        [
          'q' => 'Кто написал «К Элизе»?',
          'a' => 'Людвиг ван Бетховен. В поиске пьесу также называют «Письмо к Элизе».',
        ],
        [
          'q' => 'Это ноты «К Элизе» полностью?',
          'a' => 'Нет. Здесь тема — облегчённый вариант для фортепиано, ' . $notesLabel . '. Полная пьеса Бетховена длиннее и играется двумя руками.',
        ],
        [
          'q' => 'Где ноты «К Элизе» для начинающих на пианино?',
          'a' => 'На этой странице: одна рука, подписи под нотами и цифры. Кнопка «Сыграть ноты» открывает стан с подсказкой на клавиатуре.',
        ],
        [
          'q' => 'Можно ли скачать ноты «К Элизе» Бетховена бесплатно?',
          'a' => 'Скачивать нечего: партия бесплатно играется онлайн. Отдельной распечатки PDF на странице нет.',
        ],
        [
          'q' => 'Есть ли «К Элизе» ноты для гитары?',
          'a' => 'Нет, только для фортепиано, пианино и синтезатора. Гитарных табов на странице нет.',
        ],
      ],
      'notes' => $rows,
      'opening' => $opening,
    ];
  }

  /**
   * @return list<array{name: string, digit: string, octave: string}>
   */
  private static function noteRows(Lesson $lesson): array
  {
    $rows = [];
    foreach ($lesson->toArray()['events'] as $event) {
      if (!is_array($event) || !isset($event['notes']) || !is_array($event['notes'])) {
        continue;
      }
      $names = [];
      $digits = [];
      $octave = '';
      foreach ($event['notes'] as $note) {
        if (!is_array($note)) {
          continue;
        }
        $midi = (int) ($note['midi'] ?? 0);
        $names[] = self::noteName($midi);
        $digits[] = self::digit($midi);
        $octave = self::octaveLabel($midi);
      }
      if ($names === []) {
        continue;
      }
      $rows[] = [
        'name' => implode(' + ', $names),
        'digit' => implode(' + ', $digits),
        'octave' => $octave,
      ];
    }

    return $rows;
  }

  /**
   * @param list<array{name: string, digit: string, octave: string}> $rows
   */
  private static function openingLine(array $rows): string
  {
    $slice = array_slice($rows, 0, 9);
    $parts = [];
    foreach ($slice as $row) {
      $parts[] = $row['name'] . ' (' . $row['digit'] . ')';
    }

    return implode(', ', $parts);
  }

  private static function noteName(int $midi): string
  {
    return str_replace('-диез', '♯', NoteNames::fromMidi($midi));
  }

  private static function digit(int $midi): string
  {
    $digits = ['1', '1♯', '2', '2♯', '3', '4', '4♯', '5', '5♯', '6', '6♯', '7'];

    return $digits[$midi % 12];
  }

  private static function octaveLabel(int $midi): string
  {
    return match (intdiv($midi, 12) - 1) {
      2 => 'большая',
      3 => 'малая',
      4 => 'первая',
      5 => 'вторая',
      6 => 'третья',
      7 => 'четвёртая',
      default => '',
    };
  }
}
