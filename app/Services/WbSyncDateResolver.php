<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WbSyncDateResolver
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function resolveFrom(
        WbApiContext $context,
        string $modelClass,
        bool $freshOnly,
        ?string $explicitFrom = null,
    ): string {
        if ($explicitFrom !== null) {
            return $explicitFrom;
        }

        if (! $freshOnly) {
            return (string) config('wb.date_from');
        }

        $maxDate = $modelClass::query()
            ->where('account_id', $context->accountId)
            ->max('date');

        if ($maxDate === null) {
            $initialDays = (int) config('wb.fresh_initial_days', 31);

            return Carbon::today()->subDays(max(0, $initialDays - 1))->toDateString();
        }

        $overlapDays = (int) config('wb.fresh_overlap_days', 1);

        return Carbon::parse($maxDate)
            ->subDays(max(0, $overlapDays))
            ->toDateString();
    }

    public function resolveTo(?string $explicitTo = null): string
    {
        return $explicitTo ?: (config('wb.date_to') ?: Carbon::today()->toDateString());
    }

    public function rowIsFreshEnough(?string $rowDate, string $minDate): bool
    {
        if ($rowDate === null || $rowDate === '') {
            return true;
        }

        return Carbon::parse($rowDate)->startOfDay()->gte(Carbon::parse($minDate)->startOfDay());
    }
}
