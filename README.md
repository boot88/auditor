# Website Auditor — аудит страницы с помощью AI

Прототип веб-сервиса на Laravel: пользователь задаёт URL, приложение открывает страницу в Playwright, извлекает её содержимое и запрашивает рекомендации через OpenAI API.

**Статус:** прототип с серверной очередью задач. Для полного сценария нужны настроенный API-ключ, браузерное окружение и запущенный обработчик очереди.

## Как устроен сценарий

1. Форма создаёт запись аудита со статусом `pending`.
2. Фоновая задача проверяет исходный URL и запускает браузерный рендер.
3. Из HTML извлекаются данные страницы для анализа.
4. Внешний API возвращает рекомендации; результат и статус сохраняются в базе.
5. Интерфейс получает обновления через маршрут проверки статуса.

В отчёт запрашиваются разделы SEO, контента, UX, маркетинга и наблюдаемых признаков безопасности. Это рекомендации по данным страницы; полноценное сканирование уязвимостей сервис не выполняет.

## Технологии

PHP 8.2+, Laravel 12, Eloquent, очереди Laravel, Node.js, Playwright, Chromium, HTTP API, Blade и Vite.

## Навигация по реализации

| Часть | Файл |
| --- | --- |
| Создание аудита и выдача статуса | [SiteAuditController](app/Http/Controllers/SiteAuditController.php) |
| Выполнение фоновой задачи | [RunSiteAuditJob](app/Jobs/RunSiteAuditJob.php) |
| Проверка исходного URL | [SsrfGuard](app/Services/Audit/SsrfGuard.php) |
| Запуск браузерного процесса | [PlaywrightRenderService](app/Services/Audit/PlaywrightRenderService.php) |
| Рендер страницы | [scripts/render.mjs](scripts/render.mjs) |
| Извлечение содержимого | [PageExtractService](app/Services/Audit/PageExtractService.php) |
| Запрос к языковой модели | [OpenAiAuditService](app/Services/Audit/OpenAiAuditService.php) |

## Локальный запуск

```bash
git clone https://github.com/boot88/auditor.git
cd auditor
composer install
cp .env.example .env
php artisan key:generate
npm ci
npx playwright install chromium
npm run build
```

Установите системные зависимости браузера для своей ОС. Настройте отдельную локальную БД; при использовании SQLite создайте `database/database.sqlite`.

Добавьте в `.env`:

```dotenv
QUEUE_CONNECTION=database
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4.1-mini
OPENAI_API_BASE=https://api.openai.com/v1
NODE_BINARY=node
```

API-ключ заполняется локально. В текущем коде сервис анализа читает эти значения непосредственно из окружения, поэтому для этого локального сценария оставьте конфигурацию без `config:cache`.

```bash
php artisan migrate
php artisan serve
```

В другом терминале:

```bash
php artisan queue:work
```

Форма: `http://127.0.0.1:8000/audit`. Страница аудита показывает состояние задачи и полученный отчёт.

## Особенности

- Браузерный рендер позволяет получать содержимое JavaScript-страниц.
- В базе сохраняются HTTP-статус, итоговый URL, время рендера и анализа.
- Проверка исходного URL ограничивает протоколы, порты и локальные адреса.
- Проверка ответа модели разбирает JSON и наличие обязательных полей.
- Результат зависит от доступности страницы, качества извлечённого содержимого и ответа внешнего API.
- Вызовы внешнего API требуют доступа к выбранной модели и могут тарифицироваться провайдером.

Команда существующего набора тестов: `php artisan test`. Сквозная проверка требует настроенных внешних зависимостей.
