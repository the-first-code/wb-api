<?php

namespace App\Console\Commands\Concerns;

use App\Models\Account;
use App\Models\ApiService;
use App\Models\Company;
use App\Models\TokenType;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesCredentialEntities
{
    protected function findCompany(string $value): Company
    {
        if (ctype_digit($value)) {
            return Company::query()->findOrFail($value);
        }

        $company = Company::query()
            ->where('slug', $value)
            ->orWhere('name', $value)
            ->first();

        if ($company === null) {
            throw (new ModelNotFoundException)->setModel(Company::class, [$value]);
        }

        return $company;
    }

    protected function findAccount(string $account, ?string $company = null): Account
    {
        if (ctype_digit($account)) {
            return Account::query()->findOrFail($account);
        }

        if ($company === null) {
            throw new \InvalidArgumentException('Укажите --company для поиска аккаунта по имени.');
        }

        $companyModel = $this->findCompany($company);

        $model = Account::query()
            ->where('company_id', $companyModel->id)
            ->where('name', $account)
            ->first();

        if ($model === null) {
            throw (new ModelNotFoundException)->setModel(Account::class, [$account]);
        }

        return $model;
    }

    protected function findApiService(string $value): ApiService
    {
        if (ctype_digit($value)) {
            return ApiService::query()->findOrFail($value);
        }

        return ApiService::query()->where('code', $value)->firstOrFail();
    }

    protected function findTokenType(string $value): TokenType
    {
        if (ctype_digit($value)) {
            return TokenType::query()->findOrFail($value);
        }

        return TokenType::query()->where('code', $value)->firstOrFail();
    }

    /** @return list<TokenType> */
    protected function findTokenTypes(string $codes): array
    {
        $items = array_filter(array_map('trim', explode(',', $codes)));

        if ($items === []) {
            return [];
        }

        $types = [];

        foreach ($items as $item) {
            $types[] = $this->findTokenType($item);
        }

        return $types;
    }
}
