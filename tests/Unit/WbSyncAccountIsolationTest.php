<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\ApiService;
use App\Models\Company;
use App\Models\TokenType;
use App\Models\WbSale;
use App\Services\WbApiContext;
use App\Services\WbSyncService;
use Database\Seeders\CredentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WbSyncAccountIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CredentialsSeeder::class);
        Config::set('wb.api_service_code', 'wb_test');
    }

    public function test_same_sale_id_for_different_accounts_creates_separate_rows(): void
    {
        $accountOne = $this->createAccountWithToken('Компания A', 'Кабинет 1');
        $accountTwo = $this->createAccountWithToken('Компания B', 'Кабинет 2');

        $now = now();

        WbSale::query()->upsert([
            [
                'account_id' => $accountOne->id,
                'sale_id' => 'S-100',
                'total_price' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'account_id' => $accountTwo->id,
                'sale_id' => 'S-100',
                'total_price' => 200,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['account_id', 'sale_id'], ['total_price', 'updated_at']);

        $this->assertSame(2, WbSale::query()->where('sale_id', 'S-100')->count());
        $this->assertSame(
            100,
            (int) WbSale::query()->where('account_id', $accountOne->id)->value('total_price')
        );
        $this->assertSame(
            200,
            (int) WbSale::query()->where('account_id', $accountTwo->id)->value('total_price')
        );
    }

    public function test_upsert_updates_only_rows_of_same_account(): void
    {
        $accountOne = $this->createAccountWithToken('Компания A', 'Кабинет 1');
        $accountTwo = $this->createAccountWithToken('Компания B', 'Кабинет 2');
        $now = now();

        WbSale::query()->insert([
            [
                'account_id' => $accountOne->id,
                'sale_id' => 'S-100',
                'total_price' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'account_id' => $accountTwo->id,
                'sale_id' => 'S-100',
                'total_price' => 200,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        WbSale::query()->upsert([
            [
                'account_id' => $accountOne->id,
                'sale_id' => 'S-100',
                'total_price' => 150,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['account_id', 'sale_id'], ['total_price', 'updated_at']);

        $this->assertSame(150, (int) WbSale::query()->where('account_id', $accountOne->id)->value('total_price'));
        $this->assertSame(200, (int) WbSale::query()->where('account_id', $accountTwo->id)->value('total_price'));
    }

    public function test_sync_service_rejects_context_without_account(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('account_id');

        $context = new WbApiContext(
            accountId: null,
            accountName: '.env',
            baseUrl: 'http://wb.test',
            tokenTypeCode: TokenType::QUERY_KEY,
            credentials: ['param' => 'key', 'value' => 'x'],
        );

        $service = app(WbSyncService::class);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('persistRows');
        $method->setAccessible(true);
        $method->invoke($service, WbSale::class, [['sale_id' => 'S-1']], ['account_id', 'sale_id'], $context);
    }

    private function createAccountWithToken(string $companyName, string $accountName): Account
    {
        $company = Company::query()->create(['name' => $companyName, 'slug' => str($companyName)->slug()]);
        $account = Account::query()->create(['company_id' => $company->id, 'name' => $accountName]);
        $service = ApiService::query()->where('code', 'wb_test')->firstOrFail();
        $type = TokenType::query()->where('code', TokenType::QUERY_KEY)->firstOrFail();

        AccountToken::query()->create([
            'account_id' => $account->id,
            'api_service_id' => $service->id,
            'token_type_id' => $type->id,
            'credentials' => ['param' => 'key', 'value' => 'token'],
        ]);

        return $account;
    }
}
