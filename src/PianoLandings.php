<?php

declare(strict_types=1);

namespace PianoTrainer;

final class PianoLandings
{
  /** @return list<string> */
  public static function paths(): array
  {
    $paths = [];
    foreach (array_keys(self::pages()) as $slug) {
      $paths[] = '/pianino/' . $slug;
    }

    return $paths;
  }

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
   *   practicePath: string,
   *   relatedIds: list<string>,
   *   relatedHeading: string
   * }|null
   */
  public static function find(string $slug): ?array
  {
    $pages = self::pages();

    return $pages[$slug] ?? null;
  }

  /**
   * @return array<string, array{
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   h1: string,
   *   lead: string,
   *   facts: list<string>,
   *   sections: list<array{h2: string, paragraphs: list<string>}>,
   *   faqs: list<array{q: string, a: string}>,
   *   practicePath: string,
   *   relatedIds: list<string>,
   *   relatedHeading: string
   * }>
   */
  private static function pages(): array
  {
    $digits = 'На стане До — 1, Ре — 2, Ми — 3, Фа — 4, Соль — 5, Ля — 6, Си — 7. Буквы те же звуки: C D E F G A B.';
    $trainer = '/trenirovka/noty';

    return [
      'proizvedenie' => self::guide(
        h1: 'Произведение на пианино — ноты',
        keywords: 'произведение на пианино ноты, ноты произведения для пианино',
        lead: '«Произведение» в поиске — это любая пьеса, не одна песня. Ниже каталог, который уже можно сыграть: классика, детские и современные темы.',
        facts: ['каталог', 'для пианино', 'сыграть в браузере'],
        sections: [
          ['h2' => 'Что открыть первым', 'paragraphs' => [
            'Если нужна конкретная пьеса, откройте её страницу: «К Элизе», «Лунная соната», «Катюша». Кнопка «Сыграть ноты» на этой странице запускает тему «Лунной сонаты».',
          ]],
        ],
        faqs: [self::faqPlay('Это ноты одного произведения?', 'Нет. Страница собирает запрос «произведение на пианино». Конкретная пьеса открывается по ссылке ниже, кнопка сразу включает «Лунную сонату».')],
        practicePath: '/trenirovka/melodiya/moonlight',
        relatedIds: ['fur-elise', 'moonlight', 'katyusha', 'ode-to-joy', 'interstellar', 'clair-de-lune'],
      ),
      'detskie' => self::guide(
        h1: 'Детские песни на пианино — ноты',
        keywords: 'детские песни на пианино ноты, ноты для детей пианино',
        lead: 'Детские песни для пианино одной рукой: ёлочка, «С днём рождения», «Катюша», колыбельная. Кнопка включает «В лесу родилась ёлочка».',
        facts: ['для детей', 'для начинающих', 'одна рука'],
        sections: [
          ['h2' => 'С чего начать', 'paragraphs' => [
            'Сначала песни на пяти–восьми нотах, без басового ключа. Цифры те же: ' . $digits,
          ]],
        ],
        faqs: [self::faqPlay('Подойдут ли ноты для детского сада?', 'Да, если брать короткие партии из списка: ёлочка, день рождения, распевка. Это не сборник с текстом песен.')],
        practicePath: '/trenirovka/melodiya/v-lesu-elochka',
        relatedIds: ['v-lesu-elochka', 'malenkaya-elochka', 'happy-birthday', 'katyusha', 'kolybelnaya', 'twinkle'],
      ),
      'bukvy' => self::guide(
        h1: 'Песни на пианино — ноты буквами',
        keywords: 'песни на пианино ноты буквами, ноты буквами для пианино',
        lead: 'Буквенные ноты — это C D E F G A B. На пианино они совпадают с до ре ми фа соль ля си. Кнопка открывает тренажёр: на стане нота, на клавише та же высота.',
        facts: ['буквы', 'C D E F G A B', 'тренажёр'],
        sections: [
          ['h2' => 'Как читать буквы', 'paragraphs' => [
            $digits,
            'Песню всё равно играют клавишами, не алфавитом. Буква только называет звук.',
          ]],
        ],
        faqs: [self::faqPlay('Где песни, подписанные буквами?', 'В тренажёре подписывается название ноты. Готовые песни — в списке ниже, их тоже можно сыграть с подсказкой.')],
        practicePath: $trainer,
        relatedIds: ['ode-to-joy', 'happy-birthday', 'katyusha', 'chopsticks'],
      ),
      'dlya-detey' => self::guide(
        h1: 'Ноты для начинающих на пианино для детей',
        keywords: 'ноты для начинающих на пианино для детей, легкие ноты для детей',
        lead: 'Детям удобнее начинать с белых клавиш и коротких песен. Кнопка «Сыграть ноты» открывает «С днём рождения».',
        facts: ['дети', 'начинающие', 'короткие песни'],
        sections: [
          ['h2' => 'Порядок', 'paragraphs' => [
            'Сначала распевка и гамма до мажор, потом ёлочка и день рождения. Басовый ключ можно отложить.',
            $digits,
          ]],
        ],
        faqs: [self::faqPlay('Нужен ли педагог?', 'Партию можно пройти с подсветкой клавиши. Педагог нужен, когда появятся две руки и аппликатура.')],
        practicePath: '/trenirovka/melodiya/happy-birthday',
        relatedIds: ['raspevka', 'gamma-do', 'happy-birthday', 'v-lesu-elochka', 'twinkle', 'chopsticks'],
      ),
      'po-tsifram' => self::guide(
        h1: 'Ноты на пианино по цифрам для начинающих',
        keywords: 'ноты на пианино по цифрам для начинающих, легкие ноты на пианино цифрами',
        lead: 'Цифры для начинающих: до = 1, ре = 2, ми = 3, фа = 4, соль = 5, ля = 6, си = 7. Кнопка открывает «Оду к радости» — в ней как раз эти ступени.',
        facts: ['по цифрам', 'для начинающих', 'до = 1'],
        sections: [
          ['h2' => 'Таблица', 'paragraphs' => [
            $digits,
            'Диез не отдельная цифра: рядом со ступенью ставится знак, например ре-диез — 2♯.',
          ]],
        ],
        faqs: [self::faqPlay('Цифры заменяют нотный стан?', 'Нет. Цифра помогает назвать звук, а стан показывает длительность и октаву. В тренировке видны оба способа.')],
        practicePath: '/trenirovka/melodiya/ode-to-joy',
        relatedIds: ['ode-to-joy', 'chopsticks', 'raspevka', 'gamma-do', 'happy-birthday'],
      ),
      'sovremennye' => self::guide(
        h1: 'Современные песни на пианино — ноты',
        keywords: 'современные песни на пианино ноты, красивые песни на пианино ноты',
        lead: 'Современные темы, которые уже лежат в каталоге: Интерстеллар, Undertale, Майнкрафт, Титаник, Billie Jean. Кнопка включает тему «Интерстеллар».',
        facts: ['саундтреки', 'для пианино', 'облегчённые темы'],
        sections: [
          ['h2' => 'Что именно можно сыграть', 'paragraphs' => [
            'Это короткие мотивы, не официальные песенники. Полных аранжировок с текстом на сайте нет.',
          ]],
        ],
        faqs: [self::faqPlay('Есть ли все хиты чартов?', 'Нет. В списке только те песни и темы, для которых собрана партия.')],
        practicePath: '/trenirovka/melodiya/interstellar',
        relatedIds: ['interstellar', 'undertale', 'minecraft', 'titanic', 'billie-jean', 'golden-brown'],
      ),
      'podpisi' => self::guide(
        h1: 'Ноты на пианино с подписями',
        keywords: 'ноты на пианино подписанные, ноты с подписанными нотами',
        lead: 'Подписанные ноты — когда у знака на стане есть имя: до, ре, ми или цифра. Кнопка открывает «К Элизе»: следующая клавиша подсвечивается.',
        facts: ['подписи', 'названия нот', 'подсветка клавиш'],
        sections: [
          ['h2' => 'Как это выглядит', 'paragraphs' => [
            $digits,
            'В тренировке не нужно помнить стан наизусть: имя ноты видно до нажатия.',
          ]],
        ],
        faqs: [self::faqPlay('Подписи остаются навсегда?', 'На знакомстве с пьесой — да, подсказка включена. Когда нота узнаётся, её можно закрыть в тренажёре.')],
        practicePath: '/trenirovka/melodiya/fur-elise',
        relatedIds: ['fur-elise', 'ode-to-joy', 'happy-birthday', 'moonlight'],
      ),
      'angliyskie' => self::guide(
        h1: 'Английские ноты на пианино',
        keywords: 'ноты на пианино английские, английские названия нот пианино',
        lead: 'Английские названия: C — до, D — ре, E — ми, F — фа, G — соль, A — ля, B — си. Кнопка открывает тренажёр с подписью ноты.',
        facts: ['C D E F G A B', 'до ре ми', 'тренажёр'],
        sections: [
          ['h2' => 'Соответствие', 'paragraphs' => [
            'C = до = 1, D = ре = 2, E = ми = 3, F = фа = 4, G = соль = 5, A = ля = 6, B = си = 7.',
            'В американской системе B — это си, а не си-бемоль. Бемоль и диез подписываются отдельно.',
          ]],
        ],
        faqs: [self::faqPlay('H или B?', 'На этом сайте си — B, как в английской буквенной записи. Отдельной буквы H в подсказках нет.')],
        practicePath: $trainer,
        relatedIds: ['ode-to-joy', 'gamma-do', 'happy-birthday'],
      ),
      'oboznachenie' => self::guide(
        h1: 'Обозначение нот на пианино',
        keywords: 'обозначение нот на пианино, где какая нота на пианино',
        lead: 'У клавиши три обозначения: имя, буква и цифра. До первой октавы — белая клавиша слева от двух чёрных. Кнопка открывает тренажёр.',
        facts: ['имена', 'буквы', 'цифры'],
        sections: [
          ['h2' => 'Как найти звук', 'paragraphs' => [
            $digits,
            'Чёрная клавиша справа от белой — диез этой ноты. Слева — бемоль следующей.',
          ]],
        ],
        faqs: [self::faqPlay('Сколько октав у пианино?', 'У акустического инструмента их больше семи. Для первых песен хватает первой и второй октавы, они в центре клавиатуры.')],
        practicePath: $trainer,
        relatedIds: ['gamma-do', 'raspevka', 'ode-to-joy'],
      ),
      'pervyy-klass' => self::guide(
        h1: 'Ноты на пианино — 1 класс',
        keywords: 'ноты на пианино 1 класс, ноты 1 класс фортепиано',
        lead: 'Для первого класса — белые клавиши, короткие песни и гамма до мажор. Кнопка включает «Twinkle Twinkle Little Star».',
        facts: ['1 класс', 'начинающие', 'белые клавиши'],
        sections: [
          ['h2' => 'Что обычно проходят', 'paragraphs' => [
            'Скрипичный ключ, ноты первой октавы, четверти и половинные. Басовый ключ и две руки — следующий шаг, он есть в пути новичка.',
          ]],
        ],
        faqs: [self::faqPlay('Это школьная программа?', 'Нет, это не учебник конкретного УМК. По уровню партии совпадают с самым началом: одна рука и знакомые песни.')],
        practicePath: '/trenirovka/melodiya/twinkle',
        relatedIds: ['twinkle', 'raspevka', 'gamma-do', 'happy-birthday', 'chopsticks', 'v-lesu-elochka'],
      ),
      'legkie' => self::guide(
        h1: 'Лёгкие песни на пианино — ноты для начинающих',
        keywords: 'легкие песни на пианино ноты для начинающих, легкие ноты на пианино цифрами',
        lead: 'Лёгкие песни для начинающих: одна рука и знакомая мелодия. Кнопка открывает «Оду к радости». Цифры: до = 1 … си = 7.',
        facts: ['лёгкие', 'для начинающих', 'одна рука'],
        sections: [
          ['h2' => 'Какие партии короткие', 'paragraphs' => [
            'Собачий вальс, день рождения, распевка, ёлочка, Катюша. «К Элизе» тоже популярна, но в ней есть ре-диез.',
          ]],
        ],
        faqs: [self::faqPlay('Что сыграть в первый день?', 'Распевку, затем «Оду к радости» или собачий вальс. Две руки лучше не брать, пока правая не попадает без подсказки.')],
        practicePath: '/trenirovka/melodiya/ode-to-joy',
        relatedIds: ['ode-to-joy', 'chopsticks', 'raspevka', 'happy-birthday', 'katyusha', 'fur-elise'],
      ),
      'uchim' => self::guide(
        h1: 'Учим ноты на пианино для начинающих',
        keywords: 'учим ноты на пианино для начинающих, как выучить ноты на пианино',
        lead: 'Ноты для начинающих учат не списком, а нажатием: увидели знак — нашли клавишу. Кнопка сразу открывает тренажёр. Дальше есть путь новичка по шагам.',
        facts: ['с нуля', 'стан', 'клавиатура'],
        sections: [
          ['h2' => 'Короткий порядок', 'paragraphs' => [
            'Скрипичный ключ и до первой октавы, потом соседние ноты, потом песня. Басовый ключ — отдельный шаг.',
            $digits,
          ]],
        ],
        faqs: [self::faqPlay('Сколько времени занимает первая песня?', 'Короткую партию вроде дня рождения проходят за одно занятие, если клавиша подсвечивается. Без подсказки те же ноты закрепляются повторением.')],
        practicePath: $trainer,
        relatedIds: ['raspevka', 'gamma-do', 'ode-to-joy', 'happy-birthday'],
      ),
      'vysokie' => self::guide(
        h1: 'Высокие ноты на пианино',
        keywords: 'высокие ноты на пианино, верхние ноты пианино',
        lead: 'Высокие ноты лежат правее на клавиатуре и выше на нотном стане. До второй октавы — на октаву правее до первой. Кнопка открывает тренажёр во всём диапазоне.',
        facts: ['вторая октава', 'правая сторона', 'скрипичный ключ'],
        sections: [
          ['h2' => 'Как не перепутать октаву', 'paragraphs' => [
            'Одно и то же имя повторяется каждые семь белых клавиш. «До» в первой октаве и «до» во второй — разные клавиши, на стане у второй больше добавочных линеек или она выше.',
          ]],
        ],
        faqs: [self::faqPlay('С какой октавы начинают?', 'С первой. Вторая и третья подключаются, когда песня уходит вверх, как тема «К Элизе».')],
        practicePath: $trainer,
        relatedIds: ['fur-elise', 'gamma-do', 'ode-to-joy'],
      ),
      'nota-do' => self::guide(
        h1: 'Где нота до на пианино',
        keywords: 'где нота до на пианино, нота до первой октавы',
        lead: 'Нота до — белая клавиша сразу слева от группы из двух чёрных. До первой октавы стоит примерно в середине клавиатуры. Кнопка открывает тренажёр и показывает эту ноту на стане.',
        facts: ['до = 1', 'до = C', 'первая октава'],
        sections: [
          ['h2' => 'Как узнать именно первую октаву', 'paragraphs' => [
            'Слева от двух чёрных клавиш всегда до, но октавы разные. Первая — та, от которой удобно играть правой рукой детские песни, не упираясь в край инструмента.',
            'Буква этой ноты — C, цифра — 1.',
          ]],
        ],
        faqs: [self::faqPlay('До есть только одно?', 'Имя одно, клавиш несколько: большая, малая, первая, вторая октава. Для начинающих важно до первой.')],
        practicePath: $trainer,
        relatedIds: ['gamma-do', 'raspevka', 'ode-to-joy'],
      ),
      'kartinka' => self::guide(
        h1: 'Картинка нот на пианино',
        keywords: 'картинка нот на пианино, ноты на пианино схема',
        lead: 'Схема нот — это стан напротив клавиш: знак на линейке и белая или чёрная клавиша. Кнопка открывает живую схему, а не плакат: нота загорается, клавишу нужно нажать.',
        facts: ['нотный стан', 'клавиатура', 'подписи'],
        sections: [
          ['h2' => 'Что смотреть на схеме', 'paragraphs' => [
            $digits,
            'Добавочные линейки ниже стана — более низкие ноты, выше — более высокие.',
          ]],
        ],
        faqs: [self::faqPlay('Можно ли распечатать схему?', 'Отдельного плаката нет. Стан и клавиатура открываются в браузере.')],
        practicePath: $trainer,
        relatedIds: ['gamma-do', 'raspevka', 'fur-elise'],
      ),
      'krasivye' => self::guide(
        h1: 'Красивые песни на пианино — ноты',
        keywords: 'красивые песни на пианино ноты, красивые мелодии пианино',
        lead: 'Спокойные темы для пианино: «Лунная соната», Clair de Lune, Интерстеллар, «Прекрасное далёко». Кнопка включает Clair de Lune.',
        facts: ['медленные темы', 'для пианино', 'облегчённые фрагменты'],
        sections: [
          ['h2' => 'Это не полный нотный альбом', 'paragraphs' => [
            'У каждой ссылки свой короткий фрагмент. Полных концертных переложений на страницах нет.',
          ]],
        ],
        faqs: [self::faqPlay('Какая самая простая из красивых?', '«Прекрасное далёко» и колыбельная Брамса короче, чем «Лунная соната».')],
        practicePath: '/trenirovka/melodiya/clair-de-lune',
        relatedIds: ['clair-de-lune', 'moonlight', 'interstellar', 'prekrasnoe-daleko', 'kolybelnaya', 'comptine'],
      ),
      'bas' => self::guide(
        h1: 'Ноты на пианино в басовом ключе',
        keywords: 'ноты на пианино басовый ключ, басовый ключ для начинающих',
        lead: 'Басовый ключ читают левой рукой: ноты выглядят иначе, чем в скрипичном. Кнопка открывает гамму в басовом ключе.',
        facts: ['басовый ключ', 'левая рука', 'путь новичка'],
        sections: [
          ['h2' => 'Чем ключ отличается', 'paragraphs' => [
            'Та же клавиша в басовом ключе стоит на другой линейке. Поэтому «до» нельзя переносить со скрипичного стана на глаз.',
            'Дальше есть пошаговый путь новичка: сначала скрипичный ключ, затем бас.',
          ]],
        ],
        faqs: [self::faqPlay('Можно ли учить бас в первый день?', 'Можно, но песни из каталога почти все в скрипичном ключе. Бас лучше брать отдельным упражнением, не вместе с новой мелодией.')],
        practicePath: '/trenirovka/melodiya/roadmap-bass-scale',
        relatedIds: ['roadmap-bass-scale', 'roadmap-bass-walk', 'roadmap-grand-dialogue', 'gamma-do'],
      ),
      'onlayn' => self::guide(
        h1: 'Пианино онлайн на клавиатуре с нотами',
        keywords: 'пианино онлайн на клавиатуре с нотами, пианино онлайн ноты',
        lead: 'Пианино открывается в браузере: ноты на стане, клавиши на экране и на клавиатуре компьютера. Ставить программу не нужно. Кнопка сразу запускает чтение нот.',
        facts: ['онлайн', 'клавиатура', 'без установки'],
        sections: [
          ['h2' => 'Чем нажимать', 'paragraphs' => [
            'Подойдут мышь, сенсор, клавиши компьютера или MIDI-клавиатура. Подсказка показывает, какая нота следующая.',
          ]],
        ],
        faqs: [self::faqPlay('Это замена пианино?', 'Для разбора нот — да. Педаль, вес клавиши и две руки как на акустике здесь не повторяются.')],
        practicePath: $trainer,
        relatedIds: ['fur-elise', 'ode-to-joy', 'interstellar', 'happy-birthday'],
      ),
      'korol-i-shut' => self::guide(
        h1: 'Король и Шут — ноты на пианино',
        keywords: 'король и шут ноты на пианино, киш ноты на пианино',
        lead: 'Из «Короля и Шута» на пианино собраны две песни: «Лесник» и «Кукла колдуна». Кнопка включает «Лесника». Остального дискографии здесь нет.',
        facts: ['Лесник', 'Кукла колдуна', 'одна рука'],
        sections: [
          ['h2' => 'Что это за ноты', 'paragraphs' => [
            'Короткие мотивы, не гитарные табы и не вокал. «Распять» и другие песни группы отдельной партией не выложены.',
          ]],
        ],
        faqs: [self::faqPlay('Где вся группа?', 'Только две песни из списка. Запрос «Король и Шут ноты» чаще всего про них.')],
        practicePath: '/trenirovka/melodiya/lesnik',
        relatedIds: ['lesnik', 'kukla-kolduna', 'gruppa-krovi'],
      ),
      'shkola-tancev' => self::guide(
        h1: 'Школа бальных танцев — ноты на пианино',
        keywords: 'ноты на пианино школа бальных танцев, вальс ноты на пианино',
        lead: 'Отдельного сборника «школа бальных танцев» на сайте нет. Для размера вальса можно сыграть собачий вальс: кнопка открывает его. Это учебная пьеса, не турнирный танец.',
        facts: ['вальс', 'для начинающих', 'одна рука'],
        sections: [
          ['h2' => 'Что играть под три доли', 'paragraphs' => [
            'Собачий вальс держит чередование на 3/4. Конкурсные фокстрот и квикстеп сюда не входят.',
          ]],
        ],
        faqs: [self::faqPlay('Подойдёт ли для урока танцев?', 'Как пример вальсового движения — да. Как ноты конкретного танцевального трека — нет, такого файла здесь нет.')],
        practicePath: '/trenirovka/melodiya/chopsticks',
        relatedIds: ['chopsticks', 'kolybelnaya', 'old-doll'],
      ),
      'sumerki' => self::absent(
        h1: 'Сумерки — ноты на пианино',
        keywords: 'сумерки ноты на пианино, bella lullaby ноты',
        who: 'Тема из «Сумерек»',
        about: 'Колыбельную Беллы отдельной партией каталог не содержит. Кнопка «Сыграть ноты» открывает чтение нот, а ниже — спокойные пьесы, которые уже можно разобрать: Clair de Lune и «Лунная соната».',
        relatedIds: ['clair-de-lune', 'moonlight', 'kolybelnaya', 'comptine'],
      ),
      'masha' => self::absent(
        h1: 'Маша и Медведь — ноты на пианино',
        keywords: 'ноты маша и медведь на пианино',
        who: 'Маша и Медведь',
        about: 'Песни мультсериала в каталог не входят. Для детей уже есть ёлочка, «С днём рождения» и распевка. Кнопка открывает тренажёр нот, а не саундтрек.',
        relatedIds: ['v-lesu-elochka', 'happy-birthday', 'raspevka', 'twinkle'],
      ),
      'monetochka' => self::absent(
        h1: 'Монеточка — ноты на пианино',
        keywords: 'монополия ноты на пианино, монополия монеточка ноты на пианино, песни монеточки на пианино ноты',
        who: 'Монеточка',
        about: '«Монополия» и другие песни Монеточки отдельной партией не выложены. Кнопка открывает тренажёр. Из современных тем можно сыграть то, что есть в списке.',
        relatedIds: ['interstellar', 'billie-jean', 'titanic', 'undertale'],
      ),
      'washing-machine-heart' => self::absent(
        h1: 'Washing Machine Heart — ноты на пианино',
        keywords: 'washing machine heart ноты на пианино, мицки ноты',
        who: 'Mitski',
        about: 'Washing Machine Heart в каталоге нет. Кнопка открывает тренажёр нот, не эту песню.',
        relatedIds: ['interstellar', 'golden-brown', 'clair-de-lune'],
      ),
      'pismo-domoy' => self::absent(
        h1: 'Письмо домой — ноты на пианино',
        keywords: 'письмо домой на пианино ноты, гуф письмо домой на пианино ноты',
        who: 'Гуф',
        about: '«Письмо домой» Гуфа на пианино здесь нет: у трека нет короткой учебной мелодии в каталоге. Кнопка открывает тренажёр нот.',
        relatedIds: ['gruppa-krovi', 'billie-jean', 'interstellar'],
      ),
      'yara' => self::absent(
        h1: 'Яра яра — ноты на пианино',
        keywords: 'ноты яра яра яра на пианино, яра яра фонк ноты на пианино',
        who: 'Яра яра',
        about: 'Фонк-петля «яра яра» в каталог не входит. Кнопка открывает тренажёр, а не этот трек.',
        relatedIds: ['undertale', 'minecraft', 'seven-nation-army'],
      ),
      'tri-zhelaniya' => self::absent(
        h1: 'Три желания — ноты на пианино',
        keywords: 'три желания на пианино ноты',
        who: 'Три желания',
        about: 'Песня «Три желания» отдельной партией не собрана. Детские ноты, которые уже можно сыграть, — в списке. Кнопка открывает тренажёр.',
        relatedIds: ['katyusha', 'v-lesu-elochka', 'prekrasnoe-daleko', 'happy-birthday'],
      ),
      'disciplina' => self::absent(
        h1: 'Дисциплина — ноты на пианино',
        keywords: 'дисциплина мем ноты на пианино, песня дисциплина на пианино ноты, discipline ноты на пианино',
        who: 'Discipline',
        about: 'Под этим запросом ищут разные мем-треки, включая Discipline. Отдельной партии с таким названием в каталоге нет. Кнопка открывает тренажёр нот.',
        relatedIds: ['undertale', 'minecraft', 'seven-nation-army'],
      ),
      'rush-e' => self::absent(
        h1: 'Rush E — ноты на пианино',
        keywords: 'раш е ноты на пианино, rush e на пианино ноты',
        who: 'Rush E',
        about: 'Rush E — виртуозный мем, не песня для начинающих. Целиком в короткую учебную партию она не входит. Кнопка открывает тренажёр чтения нот.',
        relatedIds: ['undertale', 'turkish-march', 'fur-elise'],
      ),
      'supermarket' => self::absent(
        h1: 'Супермаркет — ноты на пианино',
        keywords: 'пошлая молли супермаркет на пианино ноты',
        who: 'Пошлая Молли',
        about: '«Супермаркет» группы «Пошлая Молли» в каталоге нет. Кнопка открывает тренажёр нот.',
        relatedIds: ['billie-jean', 'interstellar', 'titanic'],
      ),
      'vanya' => self::absent(
        h1: 'Ваня Дмитриенко — ноты на пианино',
        keywords: 'ваня дмитриенко на пианино ноты',
        who: 'Ваня Дмитриенко',
        about: 'Песни Вани Дмитриенко в каталог не входят: в запросе нет названия конкретной песни, и партии под них не собраны. Кнопка открывает тренажёр.',
        relatedIds: ['interstellar', 'titanic', 'prekrasnoe-daleko'],
      ),
      'upast' => self::absent(
        h1: 'Upast — ноты на пианино',
        keywords: 'upast ноты на пианино',
        who: 'Upast',
        about: 'Произведение с названием Upast в каталоге не найдено, поэтому отдельной партии нет. Кнопка открывает тренажёр нот.',
        relatedIds: ['fur-elise', 'moonlight', 'ode-to-joy'],
      ),
      'belaya-noch' => self::absent(
        h1: 'Белая ночь — ноты на пианино',
        keywords: 'белая ночь ноты на пианино',
        who: 'Белая ночь',
        about: '«Белая ночь» — название нескольких песен. Одну общую партию под все варианты выкладывать нельзя, в каталоге её нет. Рядом по названию есть «Седая ночь». Кнопка открывает тренажёр.',
        relatedIds: ['sedaya-noch', 'prekrasnoe-daleko', 'moonlight'],
      ),
      'severny-veter' => self::absent(
        h1: 'Северный ветер — ноты на пианино',
        keywords: 'северный ветер на пианино ноты',
        who: 'Северный ветер',
        about: 'Песня «Северный ветер» в каталог не входит. Кнопка открывает тренажёр нот.',
        relatedIds: ['kon', 'katyusha', 'gruppa-krovi'],
      ),
      'kamin' => self::absent(
        h1: 'Камин — ноты на пианино',
        keywords: 'ноты на пианино камин',
        who: 'Камин',
        about: 'Пьеса или песня «Камин» в каталоге не собрана. Кнопка открывает тренажёр.',
        relatedIds: ['clair-de-lune', 'kolybelnaya', 'moonlight'],
      ),
      'konets-byliny' => self::absent(
        h1: 'Конец былины — ноты на пианино',
        keywords: 'конец былины на пианино ноты',
        who: 'Конец былины',
        about: '«Конец былины» отдельной партией не выложен. Кнопка открывает тренажёр нот.',
        relatedIds: ['lesnik', 'kon', 'katyusha'],
      ),
      'sayonara' => self::absent(
        h1: 'Sayonara — ноты на пианино',
        keywords: 'sayonara ноты на пианино',
        who: 'Sayonara',
        about: 'Под названием Sayonara существует несколько треков. Одной партии в каталоге нет. Кнопка открывает тренажёр.',
        relatedIds: ['interstellar', 'undertale', 'golden-brown'],
      ),
      'kruglaya-planeta' => self::absent(
        h1: 'На круглой планете — ноты на пианино',
        keywords: 'на круглой планете ноты на пианино',
        who: 'На круглой планете',
        about: 'Детская песня «На круглой планете» в каталог пока не входит. Сыграть можно ёлочку, Катюшу и «С днём рождения». Кнопка открывает тренажёр.',
        relatedIds: ['v-lesu-elochka', 'katyusha', 'happy-birthday', 'prekrasnoe-daleko'],
      ),
      'granny' => self::absent(
        h1: 'Granny — ноты на пианино',
        keywords: 'ноты гренни на пианино, granny piano notes',
        who: 'Granny',
        about: 'Тема игры Granny в каталоге нет. Близкая по запросу короткая тема Old Doll из другой игры — в списке. Кнопка открывает тренажёр, не Granny.',
        relatedIds: ['old-doll', 'undertale', 'minecraft'],
      ),
      'raspjat' => self::absent(
        h1: 'Распять — ноты на пианино',
        keywords: 'распять на пианино ноты',
        who: 'Распять',
        about: 'Песня «Распять» отдельной партией не собрана. Из «Короля и Шута» есть «Лесник» и «Кукла колдуна». Кнопка открывает тренажёр.',
        relatedIds: ['lesnik', 'kukla-kolduna', 'gruppa-krovi'],
      ),
      'noir' => self::absent(
        h1: 'Noir — ноты на пианино',
        keywords: 'noir на пианино ноты',
        who: 'Noir',
        about: 'Название Noir носят разные пьесы, одной партии в каталоге нет. Кнопка открывает тренажёр.',
        relatedIds: ['old-doll', 'clair-de-lune', 'moonlight'],
      ),
      'bumer' => self::absent(
        h1: 'Бумер — ноты на пианино',
        keywords: 'ноты бумер на пианино',
        who: 'Бумер',
        about: '«Бумер» в каталог не входит. Кнопка открывает тренажёр нот.',
        relatedIds: ['billie-jean', 'gruppa-krovi', 'seven-nation-army'],
      ),
      'vals-smerti' => self::absent(
        h1: 'Вальс смерти — ноты на пианино',
        keywords: 'вальс смерти на пианино ноты',
        who: 'Вальс смерти',
        about: 'Шуточный неиграбельный «вальс смерти» в учебный каталог не входит. Обычный вальс для начинающих — собачий. Кнопка открывает тренажёр, не ту партитуру.',
        relatedIds: ['chopsticks', 'old-doll', 'kolybelnaya'],
      ),
      'viski' => self::absent(
        h1: 'Виски с молоком — ноты на пианино',
        keywords: 'виски с молоком ноты на пианино',
        who: 'Виски с молоком',
        about: 'Песня «Виски с молоком» в каталоге нет. Кнопка открывает тренажёр нот.',
        relatedIds: ['murka', 'gruppa-krovi', 'kon'],
      ),
      'matushka' => self::absent(
        h1: 'Матушка-земля — ноты на пианино',
        keywords: 'матушка земля ноты на пианино',
        who: 'Матушка-земля',
        about: '«Матушка-земля» отдельной партией не выложена. Из похожих по складу песен есть «Конь» и «Катюша». Кнопка открывает тренажёр.',
        relatedIds: ['kon', 'katyusha', 'gimn-rossii'],
      ),
    ];
  }

  /**
   * @param list<array{h2: string, paragraphs: list<string>}> $sections
   * @param list<array{q: string, a: string}> $faqs
   * @param list<string> $relatedIds
   * @return array{
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   h1: string,
   *   lead: string,
   *   facts: list<string>,
   *   sections: list<array{h2: string, paragraphs: list<string>}>,
   *   faqs: list<array{q: string, a: string}>,
   *   practicePath: string,
   *   relatedIds: list<string>,
   *   relatedHeading: string
   * }
   */
  private static function guide(
    string $h1,
    string $keywords,
    string $lead,
    array $facts,
    array $sections,
    array $faqs,
    string $practicePath,
    array $relatedIds,
  ): array {
    return [
      'title' => $h1 . ' | Piano Bro',
      'description' => $lead,
      'keywords' => $keywords,
      'h1' => $h1,
      'lead' => $lead,
      'facts' => $facts,
      'sections' => $sections,
      'faqs' => $faqs,
      'practicePath' => $practicePath,
      'relatedIds' => $relatedIds,
      'relatedHeading' => 'Ноты, которые можно сыграть',
    ];
  }

  /**
   * @param list<string> $relatedIds
   * @return array{
   *   title: string,
   *   description: string,
   *   keywords: string,
   *   h1: string,
   *   lead: string,
   *   facts: list<string>,
   *   sections: list<array{h2: string, paragraphs: list<string>}>,
   *   faqs: list<array{q: string, a: string}>,
   *   practicePath: string,
   *   relatedIds: list<string>,
   *   relatedHeading: string
   * }
   */
  private static function absent(string $h1, string $keywords, string $who, string $about, array $relatedIds): array
  {
    return self::guide(
      h1: $h1,
      keywords: $keywords,
      lead: $about,
      facts: [$who, 'партии в каталоге нет', 'тренажёр нот'],
      sections: [
        [
          'h2' => 'Что открывает кнопка',
          'paragraphs' => [
            '«Сыграть ноты» запускает тренажёр: стан и клавиатура. Это не скрытая партия названной песни.',
            'Готовые мелодии перечислены ниже — у каждой своя страница и своя кнопка.',
          ],
        ],
      ],
      faqs: [
        self::faqPlay('Почему нет файла с нотами?', 'Отдельного PDF нет и на остальных страницах. Если мелодии нет в списке, её нельзя честно выдать под этим заголовком.'),
      ],
      practicePath: '/trenirovka/noty',
      relatedIds: $relatedIds,
    );
  }

  /** @return array{q: string, a: string} */
  private static function faqPlay(string $q, string $a): array
  {
    return ['q' => $q, 'a' => $a];
  }
}
