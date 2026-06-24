<?php

namespace App\Console\Commands;

use App\Models\TokenType;
use Illuminate\Console\Command;
use InvalidArgumentException;

class AddTokenType extends Command
{
    protected $signature = 'wb:token-type:add
                            {code : Код типа (bearer, api_key, …)}
                            {name : Отображаемое название}
                            {--description= : Описание}
                            {--schema= : JSON-схема credentials (поля и их типы)}
                            {--schema-file= : Путь к JSON-файлу со схемой (удобно в PowerShell)}';

    protected $description = 'Добавить тип токена';

    public function handle(): int
    {
        $code = strtolower(trim($this->argument('code')));

        if (! preg_match('/^[a-z0-9_]+$/', $code)) {
            $this->error('Код может содержать только a-z, 0-9 и _.');

            return self::FAILURE;
        }

        if (TokenType::query()->where('code', $code)->exists()) {
            $this->error("Тип токена «{$code}» уже существует.");

            return self::FAILURE;
        }

        $schema = null;

        if ($schemaFile = $this->option('schema-file')) {
            if (! is_readable($schemaFile)) {
                $this->error("Файл схемы не найден или недоступен: {$schemaFile}");

                return self::FAILURE;
            }

            try {
                $schema = $this->parseSchema((string) file_get_contents($schemaFile));
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        } elseif ($schemaJson = $this->option('schema')) {
            try {
                $schema = $this->parseSchema($schemaJson);
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $type = TokenType::query()->create([
            'code' => $code,
            'name' => trim($this->argument('name')),
            'description' => $this->option('description'),
            'credentials_schema' => $schema,
        ]);

        $this->info("Тип токена создан: #{$type->id} {$type->code} ({$type->name})");

        return self::SUCCESS;
    }

    private function parseSchema(string $json): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                'Некорректный JSON в --schema. Получено: '.json_encode($json).'. Ошибка: '.json_last_error_msg()
            );
        }

        return $decoded;
    }
}
