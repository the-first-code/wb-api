<?php

namespace App\Console\Commands;

use App\Services\WbAccountCredentialResolver;
use App\Services\WbConsoleDebug;
use App\Services\WbSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class SyncWbData extends Command
{
    protected $signature = 'wb:sync
                            {--from= : Дата начала (Y-m-d), переопределяет расчёт --fresh}
                            {--to= : Дата окончания (Y-m-d)}
                            {--fresh : Загружать только свежие данные (dateFrom = MAX(date) в БД по аккаунту)}
                            {--only= : Только сущности: orders,sales,stocks,incomes}
                            {--account= : ID аккаунта (иначе все активные с токенами)}
                            {--company= : Компания для поиска аккаунта по имени}
                            {--account-name= : Имя аккаунта (вместе с --company)}';

    protected $description = 'Загрузить данные из WB API и сохранить в MySQL';

    public function handle(
        WbSyncService $sync,
        WbAccountCredentialResolver $credentials,
    ): int {
        if ($this->wantsDebugOutput() || config('wb.debug')) {
            app(WbConsoleDebug::class)->enable($this->output);
        }

        if (! $this->applyDateOptions()) {
            return self::FAILURE;
        }

        try {
            $contexts = $credentials->resolve(
                accountId: $this->option('account') ? (int) $this->option('account') : null,
                company: $this->option('company'),
                accountName: $this->option('account-name'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

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

        $freshOnly = $this->option('fresh') || config('wb.fresh_only');
        $explicitFrom = $this->option('from') ?: null;
        $dateTo = app(\App\Services\WbSyncDateResolver::class)->resolveTo($this->option('to'));

        if ($explicitFrom !== null && Carbon::parse($explicitFrom)->gt(Carbon::parse($dateTo))) {
            $this->error("Дата --from ({$explicitFrom}) позже даты --to ({$dateTo})");

            return self::FAILURE;
        }

        $this->info('Старт синхронизации WB API → MySQL');
        $this->line('Режим: '.($freshOnly ? 'только свежие данные (по полю date)' : 'полный период'));
        if ($explicitFrom !== null) {
            $this->line("Период from (явно): {$explicitFrom} — {$dateTo}");
        } else {
            $this->line("Период to: {$dateTo}".($freshOnly ? ', from — из MAX(date) по каждому аккаунту' : ', from — из WB_SYNC_DATE_FROM'));
        }
        $this->line('Аккаунтов: '.$contexts->count());

        $stats = $sync->syncAccounts(
            $contexts,
            $only,
            $dateTo,
            $this->output,
            $freshOnly,
            $explicitFrom,
        );

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

    private function wantsDebugOutput(): bool
    {
        return $this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE;
    }
}
