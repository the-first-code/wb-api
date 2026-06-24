<?php



namespace App\Services;



use App\Models\WbIncome;

use App\Models\WbOrder;

use App\Models\WbSale;

use App\Models\WbStock;

use Carbon\Carbon;

use Carbon\CarbonPeriod;

use Illuminate\Console\OutputStyle;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Arr;

use Illuminate\Support\Collection;



class WbSyncService

{

    public function __construct(

        private readonly WbApiClient $client,

        private readonly WbAccountCredentialResolver $credentials,

        private readonly WbSyncDateResolver $dateResolver,

    ) {}



    /**

     * @param  list<string>  $only

     */

    public function syncAccounts(

        Collection $contexts,

        array $only,

        string $dateTo,

        ?OutputStyle $output = null,

        bool $freshOnly = false,

        ?string $explicitDateFrom = null,

    ): array {

        $stats = [];



        foreach ($contexts as $context) {

            $output?->newLine();

            $output?->info("Аккаунт: {$context->accountName}".($context->accountId ? " (#{$context->accountId})" : ''));



            if (in_array('orders', $only, true)) {

                $dateFrom = $this->dateResolver->resolveFrom($context, WbOrder::class, $freshOnly, $explicitDateFrom);

                $output?->info("Заказы… (date ≥ {$dateFrom})");

                $stats['orders'] = ($stats['orders'] ?? 0) + $this->syncDateRange(

                    'orders',

                    $dateFrom,

                    $dateTo,

                    WbOrder::class,

                    $context,

                    $output,

                    freshOnly: $freshOnly,

                );

            }



            if (in_array('sales', $only, true)) {

                $dateFrom = $this->dateResolver->resolveFrom($context, WbSale::class, $freshOnly, $explicitDateFrom);

                $output?->info("Продажи… (date ≥ {$dateFrom})");

                $stats['sales'] = ($stats['sales'] ?? 0) + $this->syncDateRange(

                    'sales',

                    $dateFrom,

                    $dateTo,

                    WbSale::class,

                    $context,

                    $output,

                    freshOnly: $freshOnly,

                );

            }



            if (in_array('incomes', $only, true)) {

                $dateFrom = $this->dateResolver->resolveFrom($context, WbIncome::class, $freshOnly, $explicitDateFrom);

                $output?->info("Доходы… (date ≥ {$dateFrom})");

                $stats['incomes'] = ($stats['incomes'] ?? 0) + $this->syncDateRange(

                    'incomes',

                    $dateFrom,

                    $dateTo,

                    WbIncome::class,

                    $context,

                    $output,

                    requiresDateTo: true,

                    freshOnly: $freshOnly,

                );

            }



            if (in_array('stocks', $only, true)) {

                $output?->info('Склады…');

                $stats['stocks'] = ($stats['stocks'] ?? 0) + $this->syncStocks($dateTo, $context, $output);

            }

        }



        return $stats;

    }



    public function syncDateRange(

        string $endpoint,

        string $dateFrom,

        string $dateTo,

        string $modelClass,

        WbApiContext $context,

        ?OutputStyle $output = null,

        bool $requiresDateTo = false,

        bool $freshOnly = false,

    ): int {

        if (Carbon::parse($dateFrom)->gt(Carbon::parse($dateTo))) {

            $output?->writeln('  пропуск: в БД уже актуальные данные');



            return 0;

        }



        $total = 0;

        $period = CarbonPeriod::create(

            Carbon::parse($dateFrom)->startOfMonth(),

            '1 month',

            Carbon::parse($dateTo)->endOfMonth()

        );



        foreach ($period as $monthStart) {

            $chunkFrom = $monthStart->copy()->startOfMonth();

            $chunkTo = $monthStart->copy()->endOfMonth();



            if ($chunkFrom->lt(Carbon::parse($dateFrom))) {

                $chunkFrom = Carbon::parse($dateFrom);

            }

            if ($chunkTo->gt(Carbon::parse($dateTo))) {

                $chunkTo = Carbon::parse($dateTo);

            }



            $query = [

                'dateFrom' => $chunkFrom->toDateString(),

            ];



            if ($requiresDateTo || in_array($endpoint, ['orders', 'sales'], true)) {

                $query['dateTo'] = $chunkTo->toDateString();

            }



            $output?->writeln("  [{$endpoint}] {$query['dateFrom']} — ".($query['dateTo'] ?? '…'));



            $saved = 0;

            $this->client->eachPaginatedPage(
                $endpoint,
                $query,
                function (array $items) use (
                    &$saved,
                    $modelClass,
                    $endpoint,
                    $context,
                    $freshOnly,
                    $dateFrom,
                ): void {
                    $saved += $this->persistRows(
                        $modelClass,
                        $items,
                        $this->uniqueKeys($endpoint, $context),
                        $context,
                        $freshOnly ? $dateFrom : null,
                    );
                },
                $context,
            );

            $total += $saved;

            $output?->writeln("    сохранено: {$saved}");

        }



        return $total;

    }



    public function syncStocks(string $date, WbApiContext $context, ?OutputStyle $output = null): int

    {

        $output?->writeln("  [stocks] {$date}");



        $saved = 0;

        $this->client->eachPaginatedPage(
            'stocks',
            [
                'dateFrom' => $date,
            ],
            function (array $items) use (&$saved, $context): void {
                $saved += $this->persistRows(
                    WbStock::class,
                    $items,
                    $this->uniqueKeys('stocks', $context),
                    $context,
                );
            },
            $context,
        );

        $output?->writeln("    сохранено: {$saved}");



        return $saved;

    }



    private function persistRows(

        string $modelClass,

        array $rows,

        array $uniqueBy,

        WbApiContext $context,

        ?string $minDate = null,

    ): int {

        if ($rows === []) {

            return 0;

        }



        if (! $context->hasAccount()) {

            throw new \RuntimeException(

                'Синхронизация без account_id отключена. Добавьте токен аккаунта в БД: php artisan wb:account-token:add …'

            );

        }



        /** @var Model $model */

        $model = new $modelClass;

        $fillable = $model->getFillable();

        $now = now();

        $batch = [];



        foreach ($rows as $row) {

            $data = Arr::only(WbDataNormalizer::fromApiRow($row), $fillable);



            if ($minDate !== null && ! $this->dateResolver->rowIsFreshEnough($data['date'] ?? null, $minDate)) {

                continue;

            }



            $data['account_id'] = $context->accountId;

            $data['updated_at'] = $now;

            $data['created_at'] = $now;

            $batch[] = $data;

        }



        if ($batch === []) {

            return 0;

        }



        $updateColumns = array_diff($fillable, $uniqueBy);

        $updateColumns[] = 'updated_at';



        foreach (array_chunk($batch, 200) as $chunk) {

            $modelClass::upsert($chunk, $uniqueBy, $updateColumns);

        }



        return count($batch);

    }



    private function uniqueKeys(string $endpoint, WbApiContext $context): array

    {

        if (! $context->hasAccount()) {

            throw new \RuntimeException('uniqueKeys требует account_id в контексте.');

        }



        return match ($endpoint) {

            'orders' => ['account_id', 'g_number', 'odid', 'barcode', 'last_change_date'],

            'sales' => ['account_id', 'sale_id'],

            'stocks' => ['account_id', 'date', 'nm_id', 'barcode', 'warehouse_name', 'sc_code', 'tech_size'],

            'incomes' => ['account_id', 'income_id', 'barcode', 'tech_size', 'supplier_article'],

            default => throw new \InvalidArgumentException("Unknown endpoint: {$endpoint}"),

        };

    }

}


