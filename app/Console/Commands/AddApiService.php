<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCredentialEntities;
use App\Models\ApiService;
use Illuminate\Console\Command;

class AddApiService extends Command
{
    use ResolvesCredentialEntities;

    protected $signature = 'wb:api-service:add
                            {code : Код сервиса (wb_test, wildberries, …)}
                            {name : Название}
                            {--base-url= : Базовый URL API}
                            {--description= : Описание}
                            {--token-types= : Разрешённые типы токенов через запятую (code или ID)}
                            {--inactive : Создать неактивным}';

    protected $description = 'Добавить API-сервис';

    public function handle(): int
    {
        $code = strtolower(trim($this->argument('code')));

        if (! preg_match('/^[a-z0-9_]+$/', $code)) {
            $this->error('Код может содержать только a-z, 0-9 и _.');

            return self::FAILURE;
        }

        if (ApiService::query()->where('code', $code)->exists()) {
            $this->error("API-сервис «{$code}» уже существует.");

            return self::FAILURE;
        }

        $service = ApiService::query()->create([
            'code' => $code,
            'name' => trim($this->argument('name')),
            'base_url' => $this->option('base-url'),
            'description' => $this->option('description'),
            'is_active' => ! $this->option('inactive'),
        ]);

        if ($tokenTypesOption = $this->option('token-types')) {
            try {
                $types = $this->findTokenTypes($tokenTypesOption);
            } catch (\Throwable $e) {
                $service->delete();
                $this->error('Не удалось привязать типы токенов: '.$e->getMessage());

                return self::FAILURE;
            }

            $service->tokenTypes()->sync(collect($types)->pluck('id'));

            $this->line('Типы токенов: '.collect($types)->pluck('code')->implode(', '));
        }

        $this->info("API-сервис создан: #{$service->id} {$service->code} ({$service->name})");

        return self::SUCCESS;
    }
}
