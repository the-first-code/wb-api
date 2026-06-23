# WB API → MySQL (Laravel)

Сервис загружает данные из тестового [WB API](https://github.com/cy322666/wb-api/blob/master/README.md) и сохраняет их в MySQL.

## Эндпоинты

| Сущность | Метод | Путь | Параметры |
|----------|-------|------|-----------|
| Продажи | GET | `/api/sales` | `dateFrom`, `dateTo`, `page`, `limit`, `key` |
| Заказы | GET | `/api/orders` | `dateFrom`, `dateTo`, `page`, `limit`, `key` |
| Склады | GET | `/api/stocks` | `dateFrom`, `page`, `limit`, `key` (только текущий день) |
| Доходы | GET | `/api/incomes` | `dateFrom`, `dateTo`, `page`, `limit`, `key` |

Документация и примеры: [Postman](https://www.postman.com/cy322666/workspace/app-api-test/overview).

## БД развернута на бесплатном хостинге Aiven (из-за того, что хостинг бесплатный, возможны перебои в подключениях к бд. Поэтому рекомендуется развернуть бд локально и уже потом тестировать проект для надежности). Данные для доступа к БД можно посмотреть в .env файле проекта (таблицы wb_stocks, wb_incomes, wb_sales, wb_orders).

## Доступы к БД (MySQL)

| Параметр | Значение |
|----------|----------|
| Хост | `mysql-2a756a8-vitaliyrybalka91-5802.c.aivencloud.com` |
| Порт | `24097` |
| База | `defaultdb` |
| Пользователь | `avnadmin` |
| Пароль | `AVNS_rCmTCjKKzGND6OZ76fr` |


## Доступы к WB API

| Параметр | Значение |
|----------|----------|
| URL | `http://109.73.206.144:6969` |
| Ключ (`key`) | `E6kUTYrYwZq2tN4QEtyzsbEBk3ie` |

## Быстрый старт

### 1. Docker (MySQL + PHP)

```bash
cd wb-sync
docker compose up -d --build
```

Приложение: http://localhost:8000

Миграции и синхронизация внутри контейнера:

```bash
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
docker compose exec php php artisan wb:sync
```

Только MySQL (без PHP):

```bash
docker compose up -d mysql
```

### 2. Зависимости и миграции

```bash
composer install
cp .env.example .env   # если .env ещё нет
php artisan key:generate
php artisan migrate
```

### 3. Синхронизация

```bash
php artisan wb:sync
```

Только склады за сегодня:

```bash
php artisan wb:sync --only=stocks
```

Свой период:

```bash
php artisan wb:sync --from=2024-01-01 --to=2024-12-31
```

## Автосинхронизация (2 раза в день)

По расписанию выполняется `wb:sync` за последние N дней (по умолчанию 31). Время задаётся в `.env`:

```env
APP_TIMEZONE=Europe/Moscow
WB_SYNC_SCHEDULE_HOUR_1=8    # первый запуск, часы 0–23
WB_SYNC_SCHEDULE_HOUR_2=20   # второй запуск
WB_SYNC_SCHEDULE_LOOKBACK_DAYS=31
```

**Docker** — сервис `scheduler` запускает `php artisan schedule:work` вместе с остальными контейнерами:

```bash
docker compose up -d --build
```

Логи: `storage/logs/wb-sync-schedule.log`

**Без Docker** — добавьте в cron одну строку (каждую минуту Laravel сам решает, что запускать):

```cron
* * * * * cd /path/to/wb-sync && php artisan schedule:run >> /dev/null 2>&1
```

Или в отдельном процессе: `php artisan schedule:work`.

Проверка расписания: `php artisan schedule:list`

## Таблицы

- `wb_orders` — заказы
- `wb_sales` — продажи
- `wb_stocks` — остатки на складах
- `wb_incomes` — доходы (поставки)

## Переменные окружения

```env
WB_API_BASE_URL=http://109.73.206.144:6969
WB_API_KEY=ваш_ключ
WB_SYNC_DATE_FROM=2020-01-01
WB_SYNC_DATE_TO=          # пусто = сегодня
```

При ошибке **Too Many Requests** (HTTP 429 или аналогичное сообщение в ответе) клиент `App\Services\WbApiClient` автоматически ждёт и повторяет запрос.

### Поведение при 429

| Ситуация | Действие |
|----------|----------|
| HTTP `429` | Повтор запроса после паузы |
| Тело ответа содержит `too many requests`, `rate limit` | То же (даже при другом коде, например `503`) |
| Заголовок `Retry-After` | Пауза ровно на указанное число секунд |
| Заголовки `X-RateLimit-Retry`, `X-Ratelimit-Retry` | Альтернативный источник времени ожидания |
| Заголовков нет | Экспоненциальный backoff: `WB_RETRY_BASE_SECONDS × 2^(попытка−1)` |
| Исчерпаны попытки | `RuntimeException` с текстом ошибки HTTP |
| Успешный ответ после лимита | Дополнительная пауза `WB_RATE_LIMIT_PENALTY_MS` перед следующим запросом |

Параметры в `.env`:

```env
WB_RETRY_ATTEMPTS=5
WB_RETRY_BASE_SECONDS=2
WB_RETRY_MAX_SECONDS=60
WB_RATE_LIMIT_PENALTY_MS=1000
WB_REQUEST_DELAY_MS=350
```

События rate limit также пишутся в `storage/logs/laravel.log` (уровень `warning`).

### Тесты обработки 429

Файл: `tests/Unit/WbApiClientTest.php`

Тесты используют `Http::fake()` — реальные запросы к WB API **не выполняются**. Паузы `sleep()` подменяются callback-ом, поэтому тесты проходят мгновенно.

#### Запуск

Все тесты проекта:

```bash
php artisan test
```

Только тесты клиента WB API (включая 429):

```bash
php artisan test --filter=WbApiClientTest
```

В Docker:

```bash
docker compose exec php php artisan test --filter=WbApiClientTest
```

#### Список тест-кейсов

| Тест | Что проверяет |
|------|----------------|
| `test_retries_on_http_429_and_returns_successful_response` | При первом ответе `429` с заголовком `Retry-After: 3` клиент ждёт 3 секунды, повторяет запрос и возвращает данные из успешного ответа `200`. Отправлено ровно 2 HTTP-запроса. |
| `test_retries_when_response_body_contains_too_many_requests` | При ответе `503` с JSON `{"message": "Too many requests"}` клиент распознаёт лимит без кода `429`, ждёт 2 секунды (первая попытка backoff: `WB_RETRY_BASE_SECONDS`) и успешно повторяет запрос. |
| `test_throws_after_max_retry_attempts_on_rate_limit` | Если API стабильно отвечает `429`, после `WB_RETRY_ATTEMPTS` (в тесте — 3) попыток выбрасывается `RuntimeException` с текстом `HTTP 429`. |
| `test_writes_debug_lines_when_enabled` | При включённом `WbConsoleDebug` в консоль пишутся строки `[debug]` с методом, endpoint и статусом (не относится к 429, но находится в том же файле). |

#### Настройки в `setUp()` тестов

В каждом тесте через `Config::set()` задано:

```php
wb.retry_attempts = 3
wb.retry_base_seconds = 2
wb.retry_max_seconds = 60
wb.request_delay_ms = 0        // throttle отключён, чтобы не замедлять тесты
wb.rate_limit_penalty_ms = 0
```

#### Пример сценария `test_retries_on_http_429_and_returns_successful_response`

1. Запрос `GET /api/orders?...`
2. Ответ: `429`, тело `{"message":"Too Many Requests"}`, заголовок `Retry-After: 3`
3. Клиент фиксирует паузу 3 с и повторяет запрос
4. Ответ: `200`, тело `{"data":[{"id":1}],"meta":{"last_page":1}}`
5. Утверждения: `data` содержит одну запись, массив пауз `=[3]`, отправлено 2 запроса

#### Пример сценария `test_throws_after_max_retry_attempts_on_rate_limit`

1. API на каждый запрос отвечает `429`
2. Клиент делает 3 попытки (`wb.retry_attempts = 3`)
3. На 3-й попытке выбрасывается исключение — синхронизация прерывается с понятной ошибкой

### Отладочный вывод

```bash
php artisan wb:sync -v
```

или постоянно через `.env`:

```env
WB_DEBUG=true
```

В консоль выводятся HTTP-запросы (ключ скрыт), статусы ответов, пагинация, паузы throttle и повторы при rate limit. При автосинхронизации отладка попадает в `storage/logs/wb-sync-schedule.log`.

## Требования

- PHP 8.2+, расширения: `pdo_mysql`, `mbstring`, `curl`
- Composer
- Docker (для MySQL) или локальный MySQL 8
