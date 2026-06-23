<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TokenType extends Model
{
    public const BEARER = 'bearer';

    public const API_KEY = 'api_key';

    public const QUERY_KEY = 'query_key';

    public const LOGIN_PASSWORD = 'login_password';

    public const BASIC_AUTH = 'basic_auth';

    protected $fillable = [
        'code',
        'name',
        'description',
        'credentials_schema',
    ];

    protected function casts(): array
    {
        return [
            'credentials_schema' => 'array',
        ];
    }

    public function apiServices(): BelongsToMany
    {
        return $this->belongsToMany(ApiService::class);
    }
}
