<?php

namespace App\Console\Commands;

use App\Services\WbConsoleDebug;
use App\Services\WbSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SyncWbData extends Command
{
    protected $signature = 'wb:sync
                            {--from= : Дата начала (Y-m-d)}
                            {--to= : Дата окончания (Y-m-d)}
                            {--only= : Только сущности: orders,sales,stocks,incomes}';

    protected $description = 'Загрузить данные из WB API и сохранить в MySQL';

    public function handle(WbSyncService $sync, WbConsoleDebug $debug): int
    {
        if ($this->option('verbose') || config('wb.debug')) {
            $debug->enable($this->output);
        }

        if (! $this->applyDateOptions()) {
            return self::FAILURE;
        }

        if (! config('wb.key')) {
            $this->error('Укажите WB_API_KEY в .env');

            return self::FAILURE;
        }

        $only = $this->option('only')
            ? array_map('trim', explode(',', $this->option('only')))
            : ['orders', 'sales', 'incomes', 'stocks'];

        $allowed = ['orders', 'sales', 'incomes', 'stocks'];
        $invalid = array_diff($only, $allowed);
        if ($invalid !== []) {
            $this->error('Неизвестные сущности: '.implode(', ', $invalid));
            $this->line('Допустимо: '.implode(', ', $allowed));

            return self::FAILURE;
        }

        $dateFrom = config('wb.date_from');
        $dateTo = config('wb.date_to') ?: now()->toDateString();

        if (Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {
            $this->error("Дата --from ({$dateFrom}) позже даты --to ({$dateTo})");

            return self::FAILURE;
        }

        $this->info('Старт синхронизации WB API → MySQL');
        $this->line("Период: {$dateFrom} — {$dateTo}");

        $stats = [];

        if (in_array('orders', $only, true)) {
            $this->info('Заказы…');
            $stats['orders'] = $sync->syncDateRange(
                'orders',
                $dateFrom,
                $dateTo,
                \App\Models\WbOrder::class,
                $this->output
            );
        }

        if (in_array('sales', $only, true)) {
            $this->info('Продажи…');
            $stats['sales'] = $sync->syncDateRange(
                'sales',
                $dateFrom,
                $dateTo,
                \App\Models\WbSale::class,
                $this->output
            );
        }

        if (in_array('incomes', $only, true)) {
            $this->info('Доходы…');
            $stats['incomes'] = $sync->syncDateRange(
                'incomes',
                $dateFrom,
                $dateTo,
                \App\Models\WbIncome::class,
                $this->output,
                requiresDateTo: true
            );
        }

        if (in_array('stocks', $only, true)) {
            $this->info('Склады…');
            $stats['stocks'] = $sync->syncStocks($dateTo, $this->output);
        }

        $this->newLine();
        $this->table(['Сущность', 'Записей'], collect($stats)->map(fn ($v, $k) => [$k, $v]));

        $this->info('Готово.');

        return self::SUCCESS;
    }

    private function applyDateOptions(): bool
    {
        if ($from = $this->option('from')) {
            $parsed = $this->parseDateOption('from', $from);
            if ($parsed === null) {
                return false;
            }
            config(['wb.date_from' => $parsed]);
        }

        if ($to = $this->option('to')) {
            $parsed = $this->parseDateOption('to', $to);
            if ($parsed === null) {
                return false;
            }
            config(['wb.date_to' => $parsed]);
        }

        return true;
    }

    private function parseDateOption(string $option, string $value): ?string
    {
        $value = trim($value);

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (InvalidArgumentException) {
            $date = false;
        }

        if ($date === false || $date->format('Y-m-d') !== $value) {
            $this->error("Некорректная дата --{$option}: «{$value}»");
            $this->line('Формат: Y-m-d, например: 2025-05-01');

            return null;
        }

        return $date->toDateString();
    }
}
