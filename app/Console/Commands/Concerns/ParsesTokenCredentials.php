<?php

namespace App\Console\Commands\Concerns;

use App\Models\TokenType;
use InvalidArgumentException;

trait ParsesTokenCredentials
{
    protected function resolveCredentialsJson(): ?string
    {
        if ($file = $this->option('credentials-file')) {
            if (! is_readable($file)) {
                throw new InvalidArgumentException("Файл credentials не найден или недоступен: {$file}");
            }

            return (string) file_get_contents($file);
        }

        $json = $this->option('credentials');

        return ($json !== null && $json !== '') ? $json : null;
    }

    protected function parseCredentials(TokenType $type, ?string $json): array
    {
        if ($json !== null && $json !== '') {
            $decoded = json_decode($json, true);

            if (! is_array($decoded)) {
                throw new InvalidArgumentException(
                    'Некорректный JSON в --credentials. Получено: '.json_encode($json).'. Ошибка: '.json_last_error_msg()
                );
            }

            return $decoded;
        }

        return match ($type->code) {
            TokenType::BEARER => $this->credentialsForBearer(),
            TokenType::API_KEY => $this->credentialsForApiKey(),
            TokenType::QUERY_KEY => $this->credentialsForQueryKey(),
            TokenType::LOGIN_PASSWORD => $this->credentialsForLoginPassword(),
            TokenType::BASIC_AUTH => $this->credentialsForBasicAuth(),
            default => $this->credentialsFromSchema($type),
        };
    }

    private function credentialsForBearer(): array
    {
        $token = $this->option('token') ?? $this->secret('Bearer token');

        if ($token === null || $token === '') {
            throw new InvalidArgumentException('Укажите --token или введите значение интерактивно.');
        }

        return ['token' => $token];
    }

    private function credentialsForApiKey(): array
    {
        $header = $this->option('header') ?? $this->ask('HTTP-заголовок', 'X-Api-Key');
        $value = $this->option('value') ?? $this->secret('Значение API key');

        if ($header === null || $header === '' || $value === null || $value === '') {
            throw new InvalidArgumentException('Укажите --header и --value.');
        }

        return ['header' => $header, 'value' => $value];
    }

    private function credentialsForQueryKey(): array
    {
        $param = $this->option('param') ?? $this->ask('Query-параметр', 'key');
        $value = $this->option('value') ?? $this->secret('Значение ключа');

        if ($param === null || $param === '' || $value === null || $value === '') {
            throw new InvalidArgumentException('Укажите --param и --value.');
        }

        return ['param' => $param, 'value' => $value];
    }

    private function credentialsForLoginPassword(): array
    {
        $login = $this->option('login') ?? $this->ask('Логин');
        $password = $this->option('password') ?? $this->secret('Пароль');

        if ($login === null || $login === '' || $password === null || $password === '') {
            throw new InvalidArgumentException('Укажите --login и --password.');
        }

        return ['login' => $login, 'password' => $password];
    }

    private function credentialsForBasicAuth(): array
    {
        $username = $this->option('username') ?? $this->ask('Username');
        $password = $this->option('password') ?? $this->secret('Password');

        if ($username === null || $username === '' || $password === null || $password === '') {
            throw new InvalidArgumentException('Укажите --username и --password.');
        }

        return ['username' => $username, 'password' => $password];
    }

    private function credentialsFromSchema(TokenType $type): array
    {
        $schema = $type->credentials_schema ?? [];

        if ($schema === []) {
            throw new InvalidArgumentException(
                "Для типа «{$type->code}» передайте --credentials-file, --credentials JSON или задайте credentials_schema."
            );
        }

        $credentials = [];

        foreach ($schema as $field => $rules) {
            $required = (bool) ($rules['required'] ?? false);
            $isSecret = str_contains($field, 'password') || str_contains($field, 'secret') || str_contains($field, 'token');
            $value = $this->option($field);

            if ($value === null) {
                $prompt = $field.($required ? '' : ' (необязательно)');
                $value = $isSecret ? $this->secret($prompt) : $this->ask($prompt);
            }

            if ($required && ($value === null || $value === '')) {
                throw new InvalidArgumentException("Поле «{$field}» обязательно.");
            }

            if ($value !== null && $value !== '') {
                $credentials[$field] = $value;
            }
        }

        return $credentials;
    }
}
