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

## Требования

- PHP 8.2+, расширения: `pdo_mysql`, `mbstring`, `curl`
- Composer
- Docker (для MySQL) или локальный MySQL 8
