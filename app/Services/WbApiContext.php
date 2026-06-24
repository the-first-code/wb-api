<?php

namespace App\Services;

use App\Models\AccountToken;
use App\Models\TokenType;

readonly class WbApiContext
{
    public function __construct(
        public ?int $accountId,
        public string $accountName,
        public string $baseUrl,
        public string $tokenTypeCode,
        public array $credentials,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            accountId: null,
            accountName: '.env',
            baseUrl: rtrim((string) config('wb.base_url'), '/'),
            tokenTypeCode: TokenType::QUERY_KEY,
            credentials: [
                'param' => 'key',
                'value' => (string) config('wb.key'),
            ],
        );
    }

    public static function fromAccountToken(AccountToken $token): self
    {
        $token->loadMissing(['account', 'apiService', 'tokenType']);

        $baseUrl = $token->apiService?->base_url ?: config('wb.base_url');

        return new self(
            accountId: $token->account_id,
            accountName: $token->account->name,
            baseUrl: rtrim((string) $baseUrl, '/'),
            tokenTypeCode: $token->tokenType->code,
            credentials: $token->credentials,
        );
    }

    public function hasAccount(): bool
    {
        return $this->accountId !== null;
    }
}
