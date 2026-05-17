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


## Доступы к WB API

| Параметр | Значение |
|----------|----------|
| URL | `http://109.73.206.144:6969` |
| Ключ (`key`) | `E6kUTYrYwZq2tN4QEtyzsbEBk3ie` |

## Быстрый старт

### 1. MySQL (Docker)

```bash
cd wb-sync
docker compose up -d
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
