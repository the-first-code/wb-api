<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\ApiService;
use App\Models\Company;
use App\Models\TokenType;
use App\Services\WbAccountCredentialResolver;
use Database\Seeders\CredentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WbAccountCredentialResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CredentialsSeeder::class);
        Config::set('wb.api_service_code', 'wb_test');
    }

    public function test_resolves_all_active_account_contexts(): void
    {
        $this->createAccountWithToken('Компания A', 'Кабинет 1', 'token-one');
        $this->createAccountWithToken('Компания B', 'Кабинет 2', 'token-two');

        $contexts = app(WbAccountCredentialResolver::class)->resolve();

        $this->assertCount(2, $contexts);
        $this->assertSame(['Кабинет 1', 'Кабинет 2'], $contexts->pluck('accountName')->all());
    }

    public function test_throws_when_no_db_tokens(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Нет активных токенов');

        app(WbAccountCredentialResolver::class)->resolve();
    }

    public function test_resolves_single_account_by_id(): void
    {
        $account = $this->createAccountWithToken('Компания A', 'Кабинет 1', 'token-one');

        $context = app(WbAccountCredentialResolver::class)->resolve(accountId: $account->id)->sole();

        $this->assertSame($account->id, $context->accountId);
        $this->assertSame('token-one', $context->credentials['value']);
    }

    private function createAccountWithToken(string $companyName, string $accountName, string $tokenValue): Account
    {
        $company = Company::query()->create(['name' => $companyName, 'slug' => str($companyName)->slug()]);
        $account = Account::query()->create(['company_id' => $company->id, 'name' => $accountName]);
        $service = ApiService::query()->where('code', 'wb_test')->firstOrFail();
        $type = TokenType::query()->where('code', TokenType::QUERY_KEY)->firstOrFail();

        AccountToken::query()->create([
            'account_id' => $account->id,
            'api_service_id' => $service->id,
            'token_type_id' => $type->id,
            'credentials' => ['param' => 'key', 'value' => $tokenValue],
        ]);

        return $account;
    }
}
