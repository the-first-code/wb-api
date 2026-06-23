<?php

namespace Database\Seeders;

use App\Models\ApiService;
use App\Models\TokenType;
use Illuminate\Database\Seeder;

class CredentialsSeeder extends Seeder
{
    public function run(): void
    {
        $tokenTypes = [
            [
                'code' => TokenType::BEARER,
                'name' => 'Bearer token',
                'description' => 'Authorization: Bearer {token}',
                'credentials_schema' => [
                    'token' => ['type' => 'string', 'required' => true],
                ],
            ],
            [
                'code' => TokenType::API_KEY,
                'name' => 'API key (header)',
                'description' => 'Ключ в HTTP-заголовке',
                'credentials_schema' => [
                    'header' => ['type' => 'string', 'required' => true],
                    'value' => ['type' => 'string', 'required' => true],
                ],
            ],
            [
                'code' => TokenType::QUERY_KEY,
                'name' => 'API key (query)',
                'description' => 'Ключ в query-параметре запроса',
                'credentials_schema' => [
                    'param' => ['type' => 'string', 'required' => true],
                    'value' => ['type' => 'string', 'required' => true],
                ],
            ],
            [
                'code' => TokenType::LOGIN_PASSWORD,
                'name' => 'Login and password',
                'description' => 'Пара логин/пароль',
                'credentials_schema' => [
                    'login' => ['type' => 'string', 'required' => true],
                    'password' => ['type' => 'string', 'required' => true],
                ],
            ],
            [
                'code' => TokenType::BASIC_AUTH,
                'name' => 'Basic auth',
                'description' => 'HTTP Basic Authentication',
                'credentials_schema' => [
                    'username' => ['type' => 'string', 'required' => true],
                    'password' => ['type' => 'string', 'required' => true],
                ],
            ],
        ];

        foreach ($tokenTypes as $type) {
            TokenType::query()->updateOrCreate(
                ['code' => $type['code']],
                $type
            );
        }

        $wbTest = ApiService::query()->updateOrCreate(
            ['code' => 'wb_test'],
            [
                'name' => 'WB Test API',
                'base_url' => config('wb.base_url'),
                'description' => 'Тестовый WB API (ключ в query-параметре key)',
                'is_active' => true,
            ]
        );

        $wbTest->tokenTypes()->sync(
            TokenType::query()->whereIn('code', [TokenType::QUERY_KEY])->pluck('id')
        );

        $wildberries = ApiService::query()->updateOrCreate(
            ['code' => 'wildberries'],
            [
                'name' => 'Wildberries API',
                'base_url' => null,
                'description' => 'Официальный API Wildberries',
                'is_active' => true,
            ]
        );

        $wildberries->tokenTypes()->sync(
            TokenType::query()->whereIn('code', [
                TokenType::BEARER,
                TokenType::API_KEY,
            ])->pluck('id')
        );
    }
}
