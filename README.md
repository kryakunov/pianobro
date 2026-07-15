# ПианоТренажёр

Веб-приложение для обучения игре на цифровом пианино. Подключает MIDI-пианино по USB, показывает виртуальную клавиатуру и проверяет, правильно ли вы нажимаете ноты мелодии.

## Возможности

- Чтение нажатий с цифрового пианино через **Web MIDI API** (USB-кабель)
- Виртуальная клавиатура **88 клавиш** (A0–C8) — как на настоящем пианино, с диезами
- Пошаговый режим: играйте ноту за нотой
- Запасной ввод с клавиатуры компьютера (клавиши A–K, Z–M)
- Несколько встроенных мелодий (классика, поп, кино, Ludovico Einaudi)
- Статистика: прогресс, верные/неверные нажатия, точность

## Требования

- PHP 8.1+
- Браузер **Chrome** или **Edge** (для Web MIDI API)
- Цифровое пианино с USB-MIDI

## Установка и запуск

```bash
cd piano
composer install
php -S localhost:8080 -t public
```

Откройте в браузере: http://localhost:8080

## Вход через соцсети

Скопируйте `.env.example` в `.env` и укажите ключи OAuth-приложений. Кнопки появятся в окне входа только для настроенных провайдеров.

```bash
cp .env.example .env
```

| Провайдер | Где получить ключи | Redirect URI |
|-----------|-------------------|--------------|
| Google | [Google Cloud Console](https://console.cloud.google.com/apis/credentials) | `http://localhost:8080/api/auth/oauth/google/callback` |
| Яндекс | [OAuth Яндекса](https://oauth.yandex.ru/client/new) | `http://localhost:8080/api/auth/oauth/yandex/callback` |
| ВКонтакте | [VK ID](https://id.vk.com/about/business/go/docs/ru/vkid/latest/vk-id/connection/create-application) | `http://localhost:8080/api/auth/oauth/vk/callback` |

На продакшене замените `APP_URL` и redirect URI на ваш домен.

1. Подключите пианино к компьютеру по USB
2. Нажмите **«Подключить пианино»** и разрешите доступ к MIDI
3. Выберите урок слева
4. Нажмите **«Начать»** и играйте подсвеченные ноты

## Как это работает

```
Пианино (USB MIDI) → Браузер (Web MIDI API) → JavaScript тренажёр
                                                        ↓
PHP-сервер ← REST API ← загрузка уроков (JSON)    Виртуальная клавиатура
```

PHP отдаёт веб-интерфейс и API с мелодиями. Реальное время обрабатывается в браузере через Web MIDI — это стандартный способ работы с USB-MIDI в веб-приложениях.

## Добавление своих мелодий

Создайте файл в `data/lessons/my-song.json`:

```json
{
  "id": "my-song",
  "title": "Моя мелодия",
  "composer": "Автор",
  "difficulty": "beginner",
  "tempo": 100,
  "notes": [
    {"midi": 60, "duration": 400},
    {"midi": 62, "duration": 400}
  ]
}
```

Номера MIDI: C4 = 60, D4 = 62, E4 = 64 и т.д.

## Структура проекта

```
piano/
├── public/           # Точка входа и статика
├── src/              # PHP-классы (роутер, уроки)
├── templates/        # HTML-шаблон
├── data/lessons/     # Мелодии в JSON
└── composer.json
```

## Клавиши компьютера (без пианино)

| Клавиши | Ноты |
|---------|------|
| A W S E D F T G Y H U J K | C4 – C5 |
| Z X C V B N M | C3 – B3 |
