<?php

declare(strict_types=1);

namespace PianoTrainer;

final class MelodySeoProfiles
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
   *   faqs: list<array{q: string, a: string}>
   * }|null
   */
  public static function forId(string $id, string $opening, string $notesLabel, string $hands, string $difficulty): ?array
  {
    $profiles = self::profiles();
    if (!isset($profiles[$id])) {
      return null;
    }

    $profile = $profiles[$id];
    $replace = static function (string $text) use ($opening, $notesLabel, $hands, $difficulty): string {
      return strtr($text, [
        '{opening}' => $opening !== '' ? $opening : 'партии на этой странице',
        '{notes}' => $notesLabel,
        '{hands}' => $hands,
        '{level}' => $difficulty,
      ]);
    };

    $faqs = [];
    foreach ($profile['faqs'] as $faq) {
      $faqs[] = [
        'q' => $replace($faq['q']),
        'a' => $replace($faq['a']),
      ];
    }

    return [
      'title' => $replace($profile['title']),
      'description' => $replace($profile['description']),
      'keywords' => $profile['keywords'],
      'h1' => $profile['h1'],
      'lead' => $replace($profile['lead']),
      'facts' => array_map($replace, $profile['facts']),
      'sections' => [
        [
          'h2' => $profile['aboutTitle'],
          'paragraphs' => array_map($replace, $profile['about']),
        ],
        [
          'h2' => 'Сыграть на пианино',
          'paragraphs' => [
            $replace($profile['play']),
          ],
        ],
      ],
      'faqs' => $faqs,
    ];
  }

  /**
   * @return array<string, array{
   *   h1: string,
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   lead: string,
   *   facts: list<string>,
   *   aboutTitle: string,
   *   about: list<string>,
   *   play: string,
   *   faqs: list<array{q: string, a: string}>
   * }>
   */
  private static function profiles(): array
  {
    return [
      'v-lesu-elochka' => self::piece(
        h1: 'В лесу родилась ёлочка — ноты на пианино',
        keywords: 'елочка на пианино ноты, в лесу родилась елочка на пианино ноты, ёлочка ноты',
        who: 'Л. Бекман',
        lead: 'Детская песня «В лесу родилась ёлочка». На пианино здесь {notes}, {hands}. Это не сборник всех новогодних песен: соседняя страница — «Маленькой ёлочке холодно зимой».',
        aboutTitle: 'Ёлочка для пианино',
        about: [
          'Запросы «елочка на пианино» и «в лесу родилась елочка» ведут на эту партию. Уровень — {level}.',
        ],
      ),
      'malenkaya-elochka' => self::piece(
        h1: 'Маленькой ёлочке холодно зимой — ноты на пианино',
        keywords: 'елочка на пианино ноты, маленькой елочке холодно зимой ноты',
        who: 'М. Красев',
        lead: 'Вторая ёлочка в каталоге: «Маленькой ёлочке холодно зимой», {notes}, {hands}. Песню «В лесу родилась ёлочка» ищите отдельно.',
        aboutTitle: 'Короткий детский вариант',
        about: ['Партия для начинающих: {level}, без второй руки.'],
      ),
      'moonlight' => self::piece(
        h1: 'Лунная соната — ноты на пианино',
        keywords: 'лунная соната ноты на пианино, бетховен лунная соната ноты',
        who: 'Л. ван Бетховен',
        lead: 'Людвиг ван Бетховен. Здесь тема «Лунной сонаты» для пианино: {notes}, {hands}. Это не вся соната.',
        aboutTitle: 'Тема, не вся соната',
        about: ['В поиске обычно нужно узнаваемое начало, а не все три части. На странице облегчённая тема, уровень — {level}.'],
      ),
      'interstellar' => self::piece(
        h1: 'Интерстеллар — ноты на пианино',
        keywords: 'интерстеллар на пианино ноты, interstellar ноты на пианино',
        who: 'Х. Циммер',
        lead: 'Тема Ханса Циммера из «Интерстеллар». На пианино — {notes}, {hands}, не полный саундтрек.',
        aboutTitle: 'Тема из фильма',
        about: ['Короткий фрагмент, который можно сыграть в браузере. Уровень — {level}.'],
      ),
      'happy-birthday' => self::piece(
        h1: 'С днём рождения — ноты на пианино',
        keywords: 'песня с днем рождения на пианино ноты, ноты хэппи бездей на пианино, happy birthday ноты',
        who: 'Народная мелодия',
        lead: '«С днём рождения» — та же мелодия, что Happy Birthday и «хэппи бездей». Для пианино: {notes}, {hands}.',
        aboutTitle: 'Песня для начинающих',
        about: ['Короткая партия без слов: на стане ноты, не текст поздравления. Уровень — {level}.'],
      ),
      'chopsticks' => self::piece(
        h1: 'Собачий вальс — ноты на пианино для начинающих',
        keywords: 'ноты собачьего вальса на пианино для начинающих, собачий вальс ноты, чопстикс',
        who: 'A. Allen',
        lead: 'Собачий вальс для начинающих: {notes}, {hands}. В других странах ту же пьесу называют Chopsticks.',
        aboutTitle: 'Лёгкий вальс по цифрам',
        about: ['Пальцы чередуют соседние клавиши. Это учебная пьеса, её часто дают в первый год.'],
      ),
      'katyusha' => self::piece(
        h1: 'Катюша — ноты на пианино',
        keywords: 'катюша на пианино ноты, катюша ноты для начинающих',
        who: 'М. Блантер',
        lead: 'М. Блантер. Облегчённый мотив «Катюши» для пианино: {notes}, {hands}. Это не вся песня и не аккомпанемент.',
        aboutTitle: 'Мотив куплета и припева',
        about: ['Сначала подъём куплета, затем более простой припев. Уровень — {level}.'],
      ),
      'gimn-rossii' => self::piece(
        h1: 'Гимн России — ноты на пианино',
        keywords: 'гимн россии на пианино ноты, гимн российской федерации ноты',
        who: 'А. Александров',
        lead: 'А. Александров. Первая фраза гимна России для одной руки: {notes}. Это не официальная партитура и не текст.',
        aboutTitle: 'Первая фраза',
        about: ['На странице только мелодия вступления, без гармонии и без слов.'],
      ),
      'svyashchennaya-voyna' => self::piece(
        h1: 'Священная война — ноты на пианино',
        keywords: 'урал гайсин священная война ноты на пианино, священная война ноты на пианино',
        who: 'А. Александров',
        lead: 'А. Александров, «Священная война». Запрос часто ищут по исполнению Урала Гайсина — здесь та же песня, короткой мелодией для пианино: {notes}, {hands}.',
        aboutTitle: 'Начало песни',
        about: ['Облегчённый мотив вступления, не концертная обработка и не караоке.'],
      ),
      'murka' => self::piece(
        h1: 'Мурка — ноты на пианино',
        keywords: 'мурка на пианино ноты, мурка ноты для начинающих',
        who: 'Народная мелодия',
        lead: '«Мурка» — городская песня. На пианино упрощённый припев: {notes}, {hands}.',
        aboutTitle: 'Упрощённый припев',
        about: ['Одной рукой, без блатного аккомпанемента. Уровень — {level}.'],
      ),
      'prekrasnoe-daleko' => self::piece(
        h1: 'Прекрасное далёко — ноты на пианино',
        keywords: 'ноты на пианино прекрасное далеко, прекрасное далеко ноты',
        who: 'Е. Крылатов',
        lead: 'Е. Крылатов. Припев «Прекрасного далёка» для пианино: {notes}, {hands}. Слов на стане нет.',
        aboutTitle: 'Припев',
        about: ['Узнаваемое начало припева, не вся песня из фильма.'],
      ),
      'sedaya-noch' => self::piece(
        h1: 'Седая ночь — ноты на пианино',
        keywords: 'седая ночь на пианино ноты, седая ночь шатунов ноты',
        who: 'С. Кузнецов',
        lead: '«Седая ночь». На пианино — синтезаторный ход песни одной рукой: {notes}. Это не полная аранжировка.',
        aboutTitle: 'Узнаваемый ход',
        about: ['Короткий мотив, с которого песню узнают. Уровень — {level}.'],
      ),
      'kon' => self::piece(
        h1: 'Конь — ноты на пианино',
        keywords: 'конь ноты на пианино, любэ конь ноты',
        who: 'И. Матвиенко',
        lead: '«Конь» (Любэ). Облегчённый припев для пианино: {notes}, {hands}.',
        aboutTitle: 'Припев одной рукой',
        about: ['Без второй руки и без полного куплета. Уровень — {level}.'],
      ),
      'kolybelnaya' => self::piece(
        h1: 'Колыбельная — ноты на пианино',
        keywords: 'колыбельная на пианино ноты, колыбельная брамса ноты',
        who: 'И. Брамс',
        lead: 'Колыбельная Брамса. Для пианино: {notes}, {hands}. Других колыбельных на этой странице нет.',
        aboutTitle: 'Колыбельная Брамса',
        about: ['Тихая мелодия для начинающих, без аккомпанемента левой руки.'],
      ),
      'titanic' => self::piece(
        h1: 'Титаник — ноты на пианино',
        keywords: 'титаник на пианино ноты, my heart will go on ноты на пианино',
        who: 'Дж. Хорнер',
        lead: 'Тема из «Титаника» (My Heart Will Go On). На пианино — {notes}, {hands}, не вся песня.',
        aboutTitle: 'Главная тема',
        about: ['Фраза, которую узнают без слов. Уровень — {level}.'],
      ),
      'undertale' => self::piece(
        h1: 'Undertale — ноты на пианино',
        keywords: 'ноты андертейл на пианино, megalovania ноты на пианино, undertale ноты',
        who: 'Тоби Фокс',
        lead: 'По запросу «Андертейл» здесь рифф Megalovania: {notes}, {hands}. Это не весь саундтрек игры.',
        aboutTitle: 'Рифф Megalovania',
        about: ['Короткий повтор, который ищут как «ноты Андертейл». Быстрый темп можно снизить в тренировке.'],
      ),
      'minecraft' => self::piece(
        h1: 'Майнкрафт — ноты на пианино',
        keywords: 'ноты майнкрафт на пианино, minecraft sweden ноты, c418 ноты',
        who: 'C418',
        lead: 'Тема Sweden из Minecraft, облегчённо: {notes}, {hands}. Это не все мелодии игры.',
        aboutTitle: 'Тема Sweden',
        about: ['Одна рука, без аккордов из оригинала. Уровень — {level}.'],
      ),
      'golden-brown' => self::piece(
        h1: 'Golden Brown — ноты на пианино',
        keywords: 'golden brown на пианино ноты, голден браун ноты',
        who: 'The Stranglers',
        lead: 'Golden Brown. На пианино выписано остинато одной линией: {notes}. Это не переложение всего клавесина.',
        aboutTitle: 'Остинато',
        about: ['Повторяющийся ход, по которому тему узнают. Уровень — {level}.'],
      ),
      'old-doll' => self::piece(
        h1: 'Old Doll — ноты на пианино',
        keywords: 'old doll на пианино ноты, старая кукла ноты, mad father ноты',
        who: 'Sen',
        lead: 'Old Doll (Старая кукла) из Mad Father. Здесь первые такты темы для одной руки: {notes}. Вальс целиком на странице не помещается.',
        aboutTitle: 'Первые такты',
        about: ['Короткий мотив в минорном движении, не полная пьеса Сеню.'],
      ),
      'gravity-falls' => self::piece(
        h1: 'Гравити Фолз — ноты на пианино',
        keywords: 'ноты песни гравити фолз на пианино, gravity falls ноты',
        who: 'Gravity Falls',
        lead: 'Заставка «Гравити Фолз» короткой мелодией: {notes}, {hands}. Это не все песни мультсериала.',
        aboutTitle: 'Мотив заставки',
        about: ['Узнаваемые первые такты, уровень — {level}.'],
      ),
      'v-krayu-magnoliy' => self::piece(
        h1: 'В краю магнолий — ноты на пианино',
        keywords: 'в краю магнолий ноты на пианино',
        who: 'Народная обработка',
        lead: '«В краю магнолий». Облегчённая мелодия для пианино: {notes}, {hands}.',
        aboutTitle: 'Мелодия одной рукой',
        about: ['Без танго-аккомпанемента. Уровень — {level}.'],
      ),
      'lesnik' => self::piece(
        h1: 'Лесник — ноты на пианино',
        keywords: 'ноты лесник на пианино, король и шут лесник ноты',
        who: 'Король и Шут',
        lead: '«Лесник» группы «Король и Шут». На пианино — упрощённый мотив: {notes}, {hands}. Остальные песни группы — на соседней странице.',
        aboutTitle: 'Мотив, не весь альбом',
        about: ['Одна рука, без гитарного боя. «Кукла колдуна» вынесена отдельно.'],
      ),
      'kukla-kolduna' => self::piece(
        h1: 'Кукла колдуна — ноты на пианино',
        keywords: 'ноты кукла колдуна на пианино, король и шут кукла колдуна ноты',
        who: 'Король и Шут',
        lead: '«Кукла колдуна». Рифф переложен на одну руку: {notes}. Это не табы и не вся песня.',
        aboutTitle: 'Рифф одной рукой',
        about: ['Гитарный ход, который играют на пианино. Уровень — {level}.'],
      ),
      'gruppa-krovi' => self::piece(
        h1: 'Цой — ноты на пианино',
        keywords: 'цой на пианино ноты, группа крови ноты на пианино, кино ноты',
        who: 'В. Цой',
        lead: 'По запросу «Цой на пианино» здесь рифф «Группы крови»: {notes}, {hands}. Других песен Кино на странице нет.',
        aboutTitle: 'Рифф «Группы крови»',
        about: ['Короткий гитарный ход одной рукой, не полный концерт.'],
      ),
      'billie-jean' => self::piece(
        h1: 'Майкл Джексон — ноты на пианино',
        keywords: 'майкл джексон на пианино ноты, billie jean ноты на пианино',
        who: 'Майкл Джексон',
        lead: 'По этому запросу собран басовый рифф Billie Jean: {notes}, {hands}. Это не сборник всех песен Джексона.',
        aboutTitle: 'Рифф Billie Jean',
        about: ['Повторяющийся бас, перенесённый в удобную октаву. Мелодия припева сюда не входит.'],
      ),
      'gamma-do' => self::piece(
        h1: 'Гаммы на пианино — ноты',
        keywords: 'гаммы на пианино ноты, гамма до мажор ноты',
        who: 'Упражнение',
        lead: 'Гамма до мажор вверх и вниз: {notes}, {hands}. Остальные гаммы в эту партию не входят — сначала белые клавиши от до.',
        aboutTitle: 'До мажор',
        about: [
          'До ре ми фа соль ля си до и обратно. На клавиатуре это соседние белые клавиши, без диезов.',
        ],
        play: 'Кнопка «Сыграть ноты» ведёт по гамме и подписывает следующую клавишу. Отдельного PDF нет.',
      ),
      'raspevka' => self::piece(
        h1: 'Распевка на пианино — ноты',
        keywords: 'распевка на пианино ноты, распевка для начинающих ноты',
        who: 'Упражнение',
        lead: 'Короткая распевка на пяти нотах: {notes}, {hands}. Это учебный ход, не конкретная песня.',
        aboutTitle: 'Пять нот',
        about: ['До–ре–ми–фа–соль и обратно. Её проходят до песен, чтобы поставить пальцы.'],
        play: 'Кнопка «Сыграть ноты» открывает распевку на стане. Голос подпевать не обязан: задача — попасть в клавишу.',
      ),
    ];
  }

  /**
   * @param list<string> $about
   * @return array{
   *   h1: string,
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   lead: string,
   *   facts: list<string>,
   *   aboutTitle: string,
   *   about: list<string>,
   *   play: string,
   *   faqs: list<array{q: string, a: string}>
   * }
   */
  private static function piece(
    string $h1,
    string $keywords,
    string $who,
    string $lead,
    string $aboutTitle,
    array $about,
    string $play = 'Кнопка «Сыграть ноты» открывает стан в браузере и подсвечивает следующую клавишу. Скачивать и печатать PDF не нужно.',
  ): array {
    return [
      'h1' => $h1,
      'title' => $h1 . ' | Piano Bro',
      'description' => $h1 . '. ' . $who . '. {notes}, {hands}. Можно сыграть в браузере, без файла.',
      'keywords' => $keywords,
      'lead' => $lead,
      'facts' => [$who, '{level}', '{notes}', '{hands}', 'для пианино'],
      'aboutTitle' => $aboutTitle,
      'about' => $about,
      'play' => $play,
      'faqs' => [
        [
          'q' => 'Это полные ноты?',
          'a' => 'Нет. На странице короткий вариант для пианино: {notes}, {hands}.',
        ],
        [
          'q' => 'Можно ли скачать ноты?',
          'a' => 'Файла нет. Партия бесплатно открывается в браузере по кнопке «Сыграть ноты».',
        ],
      ],
    ];
  }
}
