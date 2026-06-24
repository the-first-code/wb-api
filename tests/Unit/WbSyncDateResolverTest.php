<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\ApiService;
use App\Models\Company;
use App\Models\TokenType;
use App\Models\WbSale;
use App\Services\WbApiContext;
use App\Services\WbSyncDateResolver;
use Carbon\Carbon;
use Database\Seeders\CredentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WbSyncDateResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CredentialsSeeder::class);
        Config::set('wb.fresh_overlap_days', 1);
        Config::set('wb.fresh_initial_days', 31);
        Config::set('wb.date_from', '2020-01-01');
    }

    public function test_resolve_from_uses_max_date_minus_overlap_in_fresh_mode(): void
    {
        $account = $this->createAccount();

        WbSale::query()->create([
            'account_id' => $account->id,
            'sale_id' => 'S-1',
            'date' => '2025-06-10 12:00:00',
        ]);

        $context = new WbApiContext($account->id, 'test', 'http://wb.test', TokenType::QUERY_KEY, []);

        $from = app(WbSyncDateResolver::class)->resolveFrom($context, WbSale::class, freshOnly: true);

        $this->assertSame('2025-06-09', $from);
    }

    public function test_resolve_from_uses_initial_days_when_no_data(): void
    {
        Carbon::setTestNow('2025-06-22');

        $account = $this->createAccount();
        $context = new WbApiContext($account->id, 'test', 'http://wb.test', TokenType::QUERY_KEY, []);

        $from = app(WbSyncDateResolver::class)->resolveFrom($context, WbSale::class, freshOnly: true);

        $this->assertSame('2025-05-23', $from);

        Carbon::setTestNow();
    }

    public function test_row_is_fresh_enough_filters_older_dates(): void
    {
        $resolver = app(WbSyncDateResolver::class);

        $this->assertTrue($resolver->rowIsFreshEnough('2025-06-10', '2025-06-09'));
        $this->assertFalse($resolver->rowIsFreshEnough('2025-06-08', '2025-06-09'));
        $this->assertTrue($resolver->rowIsFreshEnough(null, '2025-06-09'));
    }

    private function createAccount(): Account
    {
        $company = Company::query()->create(['name' => 'Co', 'slug' => 'co']);
        $account = Account::query()->create(['company_id' => $company->id, 'name' => 'Acc']);
        $service = ApiService::query()->where('code', 'wb_test')->firstOrFail();
        $type = TokenType::query()->where('code', TokenType::QUERY_KEY)->firstOrFail();

        AccountToken::query()->create([
            'account_id' => $account->id,
            'api_service_id' => $service->id,
            'token_type_id' => $type->id,
            'credentials' => ['param' => 'key', 'value' => 'x'],
        ]);

        return $account;
    }
}
