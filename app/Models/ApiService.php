<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApiService extends Model
{
    protected $fillable = [
        'code',
        'name',
        'base_url',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tokenTypes(): BelongsToMany
    {
        return $this->belongsToMany(TokenType::class);
    }

    public function accountTokens(): HasMany
    {
        return $this->hasMany(AccountToken::class);
    }

    public function supportsTokenType(TokenType|string $tokenType): bool
    {
        $code = $tokenType instanceof TokenType ? $tokenType->code : $tokenType;

        return $this->tokenTypes()->where('code', $code)->exists();
    }
}
