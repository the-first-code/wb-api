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

По умолчанию синхронизируются **все активные аккаунты** с токеном для API-сервиса `WB_API_SERVICE` (по умолчанию `wb_test`). Токены задаются через `wb:account-token:add` (см. ниже).

Один аккаунт:

```bash
php artisan wb:sync --account=1
php artisan wb:sync --company=romashka --account-name="Кабинет WB"
```

Данные сохраняются с привязкой к `account_id` — записи разных аккаунтов не смешиваются.

**Только свежие данные** (по полю `date` в БД — для каждого аккаунта `dateFrom = MAX(date) − overlap`):

```bash
php artisan wb:sync --fresh
```

При первой загрузке аккаунта (нет данных в БД) берётся окно `WB_SYNC_FRESH_INITIAL_DAYS` (по умолчанию 31 день). Явный `--from` переопределяет расчёт.

Только склады за сегодня:

```bash
php artisan wb:sync --only=stocks
```

Свой период:

```bash
php artisan wb:sync --from=2024-01-01 --to=2024-12-31
```

## Автосинхронизация (2 раза в день)

По расписанию выполняется `wb:sync --fresh` — загружаются только записи с `date` новее последней сохранённой по каждому аккаунту. Время задаётся в `.env`:

```env
APP_TIMEZONE=Europe/Moscow
WB_SYNC_SCHEDULE_HOUR_1=8    # первый запуск, часы 0–23
WB_SYNC_SCHEDULE_HOUR_2=20   # второй запуск
WB_SYNC_FRESH_OVERLAP_DAYS=1 # перекрытие для догрузки изменений
WB_SYNC_FRESH_INITIAL_DAYS=31 # первая синхронизация аккаунта без истории
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

### Данные WB (с привязкой к аккаунту)

Во всех таблицах данных есть поле `account_id` (FK → `accounts`). Уникальные индексы включают `account_id`, поэтому одинаковые записи из разных аккаунтов **не затирают** друг друга при upsert.

| Таблица | Unique-ключ (с account_id) |
|---------|----------------------------|
| `wb_orders` | account_id + g_number + odid + barcode + last_change_date |
| `wb_sales` | account_id + sale_id |
| `wb_stocks` | account_id + date + nm_id + barcode + warehouse_name + sc_code + tech_size |
| `wb_incomes` | account_id + income_id + barcode + tech_size + supplier_article |

Миграция: `2026_06_22_000002_add_account_id_to_wb_tables.php`

```bash
php artisan migrate
```

### Справочники credentials

- `companies`, `accounts`, `api_services`, `token_types`, `api_service_token_type`, `account_tokens`

## Консольные команды (компании и токены)

Перед использованием выполните миграции и сидер справочников:

```bash
php artisan migrate
php artisan db:seed --class=CredentialsSeeder
```

### Компания

```bash
php artisan wb:company:add "ООО Ромашка"
php artisan wb:company:add "ООО Ромашка" --slug=romashka
php artisan wb:company:add "Архивная" --inactive
```

### Аккаунт

`{company}` — ID, slug или название компании.

```bash
php artisan wb:account:add romashka "Кабинет WB"
php artisan wb:account:add 1 "Основной" --external-id=wb-12345
```

### Тип токена

Команда: `wb:token-type:add {code} {name}`

| Аргумент / опция | Описание |
|------------------|----------|
| `code` | Код типа: `bearer`, `api_key`, `query_key`, `oauth2`, … |
| `name` | Отображаемое название |
| `--description` | Описание (необязательно) |
| `--schema` | JSON-схема credentials прямо в командной строке |
| `--schema-file` | Путь к JSON-файлу со схемой (**рекомендуется в Windows / Docker**) |

Справочник `CredentialsSeeder` уже создаёт типы `bearer`, `api_key`, `query_key`, `login_password`, `basic_auth`. Новый тип нужен только для нестандартной схемы credentials.

#### 1. Создать JSON-файл схемы в `storage/app/`

Файлы в `storage/app/` доступны и на хосте, и в контейнере (каталог проекта смонтирован в `/var/www/html`).

**Пример:** `storage/app/oauth2-schema.json`

```json
{
    "access_token": {
        "type": "string",
        "required": true
    }
}
```

Каждый ключ верхнего уровня — поле в `credentials` токена. Для каждого поля:

- `type` — тип (`string`)
- `required` — обязательно ли поле (`true` / `false`)

**Создание файла в Cursor / VS Code:** `File → New File` → сохранить как `storage/app/oauth2-schema.json` → вставить JSON выше.

**PowerShell (Windows):**

```powershell
@'
{
    "access_token": {
        "type": "string",
        "required": true
    }
}
'@ | Set-Content -Path storage/app/oauth2-schema.json -Encoding utf8NoBOM
```

**Git Bash / Linux / macOS:**

```bash
cat > storage/app/oauth2-schema.json << 'EOF'
{
    "access_token": {
        "type": "string",
        "required": true
    }
}
EOF
```

В репозитории уже есть пример: `storage/app/oauth2-schema.json`.

#### 2. Добавить тип токена (команды)

**Docker + PowerShell (Windows) — через файл:**

```powershell
docker compose exec php php artisan wb:token-type:add oauth2 "OAuth 2.0" `
  --description="Access token" `
  --schema-file=storage/app/oauth2-schema.json
```

**Docker + Git Bash / Linux:**

```bash
docker compose exec php php artisan wb:token-type:add oauth2 "OAuth 2.0" \
  --description="Access token" \
  --schema-file=storage/app/oauth2-schema.json
```

**Локально (PHP на хосте, без Docker):**

```bash
php artisan wb:token-type:add oauth2 "OAuth 2.0" \
  --description="Access token" \
  --schema-file=storage/app/oauth2-schema.json
```

**JSON прямо в команде** — только в Bash / Git Bash (не PowerShell):

```bash
php artisan wb:token-type:add oauth2 "OAuth 2.0" \
  --description="Access token" \
  --schema='{"access_token":{"type":"string","required":true}}'
```

**Без схемы** (если credentials задаются только через `--credentials` JSON при добавлении токена):

```bash
docker compose exec php php artisan wb:token-type:add custom "Custom type" \
  --description="Произвольный тип"
```

#### 3. Почему в PowerShell не работает `--schema='{...}'`

PowerShell и цепочка `docker compose exec` «ломают» кавычки в JSON. В PHP может прийти `{access_token:{type:string,required:true}}` — это не JSON.  
**Решение:** всегда использовать `--schema-file=storage/app/....json` в Windows.

Успешный ответ команды:

```
Тип токена создан: #7 oauth2 (OAuth 2.0)
```

### API-сервис

**Bash / Git Bash:**

```bash
php artisan wb:api-service:add my_api "My API" \
  --base-url=https://api.example.com \
  --token-types=query_key,bearer
```

**Docker + PowerShell** — одной строкой (не копируйте строки, начинающиеся с `--`, без команды):

```powershell
docker compose exec php php artisan wb:api-service:add my_api "My API" --base-url=https://api.example.com --token-types=query_key,bearer
```

Сервисы `wb_test` и `wildberries` уже создаются через `CredentialsSeeder`. Повторный `wb:api-service:add` с тем же `code` вернёт «API-сервис уже существует».

### Токен аккаунта

Один токен на аккаунт для каждого API-сервиса. Тип токена должен быть разрешён для выбранного сервиса.

| Опция | Описание |
|-------|----------|
| `--account` | ID аккаунта |
| `--company` + `--account-name` | Компания и имя аккаунта (альтернатива `--account`) |
| `--api-service` | Code или ID API-сервиса |
| `--token-type` | Code или ID типа токена |
| `--credentials-file` | Путь к JSON с учётными данными (**рекомендуется в Windows / Docker**) |
| `--credentials` | JSON в командной строке (только Bash / Git Bash) |
| `--param`, `--value`, `--token`, … | Отдельные поля для стандартных типов |

#### 1. Файл credentials в `storage/app/`

Шаблоны лежат в репозитории: `storage/app/private/credentials/`.

**Query key** (тестовый WB API) — скопируйте шаблон и подставьте ключ:

```powershell
Copy-Item storage/app/private/credentials/query-key.example.json storage/app/wb-test-credentials.json
```

Содержимое `storage/app/wb-test-credentials.json`:

```json
{
    "param": "key",
    "value": "E6kUTYrYwZq2tN4QEtyzsbEBk3ie"
}
```

**OAuth 2.0:**

```powershell
Copy-Item storage/app/private/credentials/oauth2.example.json storage/app/oauth2-credentials.json
```

```json
{
    "access_token": "ваш-access-token"
}
```

Файлы в `storage/app/` (кроме `private/` и `public/`) не попадают в git — секреты остаются локально.

#### 2. Добавить токен (команды)

**Query key — Docker + PowerShell:**

```powershell
docker compose exec php php artisan wb:account-token:add `
  --company=romashka `
  --account-name="Кабинет WB" `
  --api-service=wb_test `
  --token-type=query_key `
  --credentials-file=storage/app/wb-test-credentials.json
```

**OAuth 2.0 — Docker + PowerShell:**

```powershell
docker compose exec php php artisan wb:account-token:add `
  --company=romashka `
  --account-name="Кабинет WB" `
  --api-service=wildberries `
  --token-type=oauth2 `
  --credentials-file=storage/app/oauth2-credentials.json
```

**Отдельные поля** (без JSON-файла, для `query_key` / `bearer`):

```powershell
docker compose exec php php artisan wb:account-token:add `
  --company=romashka `
  --account-name="Кабинет WB" `
  --api-service=wb_test `
  --token-type=query_key `
  --param=key `
  --value=E6kUTYrYwZq2tN4QEtyzsbEBk3ie
```

**JSON в командной строке** — только Bash / Git Bash:

```bash
php artisan wb:account-token:add \
  --account=1 \
  --api-service=wb_test \
  --token-type=query_key \
  --credentials='{"param":"key","value":"secret"}'
```

Дополнительные опции: `--label`, `--expires-at="2026-12-31"`, `--inactive`.

Без `--credentials-file`, `--credentials` и полей `--param`/`--token`/… команда запросит значения интерактивно (пароли — через скрытый ввод).

В PowerShell не используйте `--credentials='{...}'` — кавычки ломаются так же, как у `--schema`. Используйте `--credentials-file`.

В Docker:

```bash
docker compose exec php php artisan wb:company:add "ООО Ромашка"
```

## Переменные окружения

```env
WB_API_BASE_URL=http://109.73.206.144:6969
WB_API_SERVICE=wb_test       # code API-сервиса для токенов из account_tokens
WB_SYNC_DATE_FROM=2020-01-01 # начало периода при полной синхронизации (без --fresh)
WB_SYNC_DATE_TO=          # пусто = сегодня
WB_SYNC_FRESH_ONLY=false  # true = режим --fresh по умолчанию
WB_SYNC_FRESH_OVERLAP_DAYS=1
WB_SYNC_FRESH_INITIAL_DAYS=31
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

В Docker (переменные `DB_*` из `docker-compose.yml` не мешают — тесты принудительно используют SQLite `:memory:`):

```bash
docker compose exec php php artisan test
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

Флаг `-v` (рекомендуется для разового запуска):

```powershell
docker compose exec php php artisan wb:sync -v
```

Постоянно через `.env` (строка должна быть **сохранена** в файле на диске):

```env
WB_DEBUG=true
```

После изменения `.env` перезапуск контейнера не нужен — Laravel читает файл при каждом `artisan`. Проверка:

```powershell
docker compose exec php php artisan tinker --execute="var_export(config('wb.debug'));"
```

Должно вывести `true`.

В консоль выводятся HTTP-запросы (ключ скрыт), статусы ответов, пагинация, паузы throttle и повторы при rate limit. При автосинхронизации отладка попадает в `storage/logs/wb-sync-schedule.log`.

## Требования

- PHP 8.2+, расширения: `pdo_mysql`, `mbstring`, `curl`
- Composer
- Docker (для MySQL) или локальный MySQL 8
