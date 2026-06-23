<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class AccountToken extends Model
{
    protected $fillable = [
        'account_id',
        'api_service_id',
        'token_type_id',
        'credentials',
        'label',
        'expires_at',
        'is_active',
    ];

    protected $hidden = [
        'credentials',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AccountToken $token): void {
            $token->assertTokenTypeAllowedForService();
        });
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function apiService(): BelongsTo
    {
        return $this->belongsTo(ApiService::class);
    }

    public function tokenType(): BelongsTo
    {
        return $this->belongsTo(TokenType::class);
    }

    public function assertTokenTypeAllowedForService(): void
    {
        $service = $this->apiService ?? ApiService::find($this->api_service_id);
        $type = $this->tokenType ?? TokenType::find($this->token_type_id);

        if ($service === null || $type === null) {
            return;
        }

        if (! $service->supportsTokenType($type)) {
            throw new InvalidArgumentException(
                "Token type «{$type->code}» is not allowed for API service «{$service->code}»."
            );
        }
    }
}
