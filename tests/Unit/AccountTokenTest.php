<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\ApiService;
use App\Models\Company;
use App\Models\TokenType;
use Database\Seeders\CredentialsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AccountTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CredentialsSeeder::class);
    }

    public function test_account_can_store_one_token_per_api_service(): void
    {
        $company = Company::query()->create(['name' => 'ООО Ромашка']);
        $account = Account::query()->create([
            'company_id' => $company->id,
            'name' => 'Основной кабинет',
        ]);

        $service = ApiService::query()->where('code', 'wb_test')->firstOrFail();
        $type = TokenType::query()->where('code', TokenType::QUERY_KEY)->firstOrFail();

        $token = AccountToken::query()->create([
            'account_id' => $account->id,
            'api_service_id' => $service->id,
            'token_type_id' => $type->id,
            'credentials' => [
                'param' => 'key',
                'value' => 'secret-token',
            ],
        ]);

        $this->assertSame('secret-token', $token->fresh()->credentials['value']);
        $this->assertCount(1, $account->fresh()->tokens);
    }

    public function test_rejects_token_type_not_allowed_for_service(): void
    {
        $company = Company::query()->create(['name' => 'ООО Ромашка']);
        $account = Account::query()->create([
            'company_id' => $company->id,
            'name' => 'Основной кабинет',
        ]);

        $service = ApiService::query()->where('code', 'wb_test')->firstOrFail();
        $type = TokenType::query()->where('code', TokenType::BEARER)->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        AccountToken::query()->create([
            'account_id' => $account->id,
            'api_service_id' => $service->id,
            'token_type_id' => $type->id,
            'credentials' => ['token' => 'x'],
        ]);
    }
}
