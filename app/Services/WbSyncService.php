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

class WbSyncService
{
    public function __construct(
        private readonly WbApiClient $client,
    ) {}

    public function syncAll(?OutputStyle $output = null): array
    {
        $stats = [];

        $dateFrom = config('wb.date_from');
        $dateTo = config('wb.date_to') ?: Carbon::today()->toDateString();

        $output?->info("Синхронизация за период {$dateFrom} — {$dateTo}");

        $stats['orders'] = $this->syncDateRange('orders', $dateFrom, $dateTo, WbOrder::class, $output);
        $stats['sales'] = $this->syncDateRange('sales', $dateFrom, $dateTo, WbSale::class, $output);
        $stats['incomes'] = $this->syncDateRange('incomes', $dateFrom, $dateTo, WbIncome::class, $output, requiresDateTo: true);
        $stats['stocks'] = $this->syncStocks($dateTo, $output);

        return $stats;
    }

    public function syncDateRange(
        string $endpoint,
        string $dateFrom,
        string $dateTo,
        string $modelClass,
        ?OutputStyle $output = null,
        bool $requiresDateTo = false,
    ): int {
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

            $rows = $this->client->fetchPaginated($endpoint, $query);
            $saved = $this->persistRows($modelClass, $rows, $this->uniqueKeys($endpoint));

            $total += $saved;
            $output?->writeln("    сохранено: {$saved}");
        }

        return $total;
    }

    public function syncStocks(string $date, ?OutputStyle $output = null): int
    {
        $output?->writeln("  [stocks] {$date}");

        $rows = $this->client->fetchPaginated('stocks', [
            'dateFrom' => $date,
        ]);

        $saved = $this->persistRows(WbStock::class, $rows, $this->uniqueKeys('stocks'));
        $output?->writeln("    сохранено: {$saved}");

        return $saved;
    }

    private function persistRows(string $modelClass, array $rows, array $uniqueBy): int
    {
        if ($rows === []) {
            return 0;
        }

        /** @var Model $model */
        $model = new $modelClass;
        $fillable = $model->getFillable();
        $now = now();
        $batch = [];

        foreach ($rows as $row) {
            $data = Arr::only(WbDataNormalizer::fromApiRow($row), $fillable);
            $data['updated_at'] = $now;
            $data['created_at'] = $now;
            $batch[] = $data;
        }

        $updateColumns = array_diff($fillable, $uniqueBy);
        $updateColumns[] = 'updated_at';

        foreach (array_chunk($batch, 200) as $chunk) {
            $modelClass::upsert($chunk, $uniqueBy, $updateColumns);
        }

        return count($batch);
    }

    private function uniqueKeys(string $endpoint): array
    {
        return match ($endpoint) {
            'orders' => ['g_number', 'odid', 'barcode', 'last_change_date'],
            'sales' => ['sale_id'],
            'stocks' => ['date', 'nm_id', 'barcode', 'warehouse_name', 'sc_code', 'tech_size'],
            'incomes' => ['income_id', 'barcode', 'tech_size', 'supplier_article'],
            default => throw new \InvalidArgumentException("Unknown endpoint: {$endpoint}"),
        };
    }
}
