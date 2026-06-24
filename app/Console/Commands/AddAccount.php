<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCredentialEntities;
use App\Models\Account;
use Illuminate\Console\Command;

class AddAccount extends Command
{
    use ResolvesCredentialEntities;

    protected $signature = 'wb:account:add
                            {company : ID, slug или название компании}
                            {name : Название аккаунта}
                            {--external-id= : Внешний идентификатор}
                            {--inactive : Создать неактивным}';

    protected $description = 'Добавить аккаунт компании';

    public function handle(): int
    {
        try {
            $company = $this->findCompany($this->argument('company'));
        } catch (\Throwable $e) {
            $this->error('Компания не найдена: '.$this->argument('company'));

            return self::FAILURE;
        }

        $name = trim($this->argument('name'));

        if (Account::query()->where('company_id', $company->id)->where('name', $name)->exists()) {
            $this->error("Аккаунт «{$name}» уже существует у компании «{$company->name}».");

            return self::FAILURE;
        }

        $account = Account::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'external_id' => $this->option('external-id'),
            'is_active' => ! $this->option('inactive'),
        ]);

        $this->info("Аккаунт создан: #{$account->id} «{$account->name}» (компания: {$company->name})");

        return self::SUCCESS;
    }
}
