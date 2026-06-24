<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesCredentialEntities;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AddCompany extends Command
{
    use ResolvesCredentialEntities;

    protected $signature = 'wb:company:add
                            {name : Название компании}
                            {--slug= : URL-slug (по умолчанию из названия)}
                            {--inactive : Создать неактивной}';

    protected $description = 'Добавить компанию';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $slug = $this->option('slug') ?: Str::slug($name);

        if ($slug === '') {
            $this->error('Не удалось сгенерировать slug. Укажите --slug вручную.');

            return self::FAILURE;
        }

        if (Company::query()->where('slug', $slug)->exists()) {
            $this->error("Компания со slug «{$slug}» уже существует.");

            return self::FAILURE;
        }

        $company = Company::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => ! $this->option('inactive'),
        ]);

        $this->info("Компания создана: #{$company->id} {$company->name} (slug: {$company->slug})");

        return self::SUCCESS;
    }
}
