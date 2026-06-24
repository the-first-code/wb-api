<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ParsesTokenCredentials;
use App\Console\Commands\Concerns\ResolvesCredentialEntities;
use App\Models\AccountToken;
use Carbon\Carbon;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AddAccountToken extends Command
{
    use ParsesTokenCredentials;
    use ResolvesCredentialEntities;

    protected $signature = 'wb:account-token:add
                            {--account= : ID аккаунта}
                            {--company= : ID, slug или название компании}
                            {--account-name= : Имя аккаунта (если --account не задан)}
                            {--api-service= : Code или ID API-сервиса}
                            {--token-type= : Code или ID типа токена}
                            {--credentials= : JSON с учётными данными}
                            {--credentials-file= : Путь к JSON-файлу с учётными данными (удобно в PowerShell)}
                            {--token= : Bearer token}
                            {--header= : HTTP-заголовок (api_key)}
                            {--value= : Значение ключа}
                            {--param= : Query-параметр (query_key)}
                            {--login= : Логин}
                            {--password= : Пароль}
                            {--username= : Username (basic_auth)}
                            {--label= : Метка токена}
                            {--expires-at= : Дата истечения (Y-m-d или Y-m-d H:i:s)}
                            {--inactive : Создать неактивным}';

    protected $description = 'Добавить токен аккаунта для API-сервиса';

    public function handle(): int
    {
        try {
            $account = $this->resolveAccount();
            $service = $this->resolveApiService();
            $type = $this->resolveTokenType();
            $credentials = $this->parseCredentials($type, $this->resolveCredentialsJson());
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (AccountToken::query()
            ->where('account_id', $account->id)
            ->where('api_service_id', $service->id)
            ->exists()) {
            $this->error("У аккаунта «{$account->name}» уже есть токен для API «{$service->code}».");

            return self::FAILURE;
        }

        $expiresAt = null;

        if ($expires = $this->option('expires-at')) {
            try {
                $expiresAt = Carbon::parse($expires);
            } catch (\Throwable) {
                $this->error('Некорректная дата в --expires-at. Формат: Y-m-d или Y-m-d H:i:s');

                return self::FAILURE;
            }
        }

        try {
            $token = AccountToken::query()->create([
                'account_id' => $account->id,
                'api_service_id' => $service->id,
                'token_type_id' => $type->id,
                'credentials' => $credentials,
                'label' => $this->option('label'),
                'expires_at' => $expiresAt,
                'is_active' => ! $this->option('inactive'),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Токен создан: #{$token->id} (аккаунт: {$account->name}, API: {$service->code}, тип: {$type->code})");

        return self::SUCCESS;
    }

    private function resolveAccount()
    {
        if ($accountId = $this->option('account')) {
            return $this->findAccount((string) $accountId);
        }

        $company = $this->option('company');
        $name = $this->option('account-name');

        if ($company === null || $name === null) {
            throw new InvalidArgumentException('Укажите --account или пару --company и --account-name.');
        }

        return $this->findAccount($name, $company);
    }

    private function resolveApiService()
    {
        $value = $this->option('api-service') ?? $this->ask('API-сервис (code или ID)');

        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Укажите --api-service.');
        }

        return $this->findApiService($value);
    }

    private function resolveTokenType()
    {
        $value = $this->option('token-type') ?? $this->ask('Тип токена (code или ID)');

        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Укажите --token-type.');
        }

        return $this->findTokenType($value);
    }
}
