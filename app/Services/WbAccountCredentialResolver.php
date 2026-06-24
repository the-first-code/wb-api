<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountToken;
use App\Models\Company;
use Illuminate\Support\Collection;
use RuntimeException;

class WbAccountCredentialResolver
{
    /** @return Collection<int, WbApiContext> */
    public function resolve(?int $accountId = null, ?string $company = null, ?string $accountName = null): Collection
    {
        if ($accountId !== null) {
            return collect([$this->contextForAccountId($accountId)]);
        }

        if ($company !== null && $accountName !== null) {
            return collect([$this->contextForAccountName($company, $accountName)]);
        }

        $contexts = $this->allActiveContexts();

        if ($contexts->isNotEmpty()) {
            return $contexts;
        }

        throw new RuntimeException(
            'Нет активных токенов аккаунтов для API «'.config('wb.api_service_code', 'wb_test').'». '.
            'Добавьте: php artisan wb:account-token:add …'
        );
    }

    private function contextForAccountId(int $accountId): WbApiContext
    {
        $token = $this->activeTokenQuery()
            ->where('account_id', $accountId)
            ->first();

        if ($token === null) {
            throw new RuntimeException("Для аккаунта #{$accountId} не найден активный токен API «".config('wb.api_service_code').'».');
        }

        return WbApiContext::fromAccountToken($token);
    }

    private function contextForAccountName(string $company, string $accountName): WbApiContext
    {
        $companyModel = Company::query()
            ->when(ctype_digit($company), fn ($q) => $q->where('id', $company))
            ->when(! ctype_digit($company), fn ($q) => $q->where('slug', $company)->orWhere('name', $company))
            ->first();

        if ($companyModel === null) {
            throw new RuntimeException("Компания не найдена: {$company}");
        }

        $account = Account::query()
            ->where('company_id', $companyModel->id)
            ->where('name', $accountName)
            ->first();

        if ($account === null) {
            throw new RuntimeException("Аккаунт «{$accountName}» не найден у компании «{$companyModel->name}».");
        }

        return $this->contextForAccountId($account->id);
    }

    /** @return Collection<int, WbApiContext> */
    private function allActiveContexts(): Collection
    {
        return $this->activeTokenQuery()
            ->orderBy('account_id')
            ->get()
            ->map(fn (AccountToken $token) => WbApiContext::fromAccountToken($token));
    }

    private function activeTokenQuery()
    {
        $serviceCode = config('wb.api_service_code', 'wb_test');

        return AccountToken::query()
            ->with(['account.company', 'apiService', 'tokenType'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('account', fn ($q) => $q->where('is_active', true))
            ->whereHas('account.company', fn ($q) => $q->where('is_active', true))
            ->whereHas('apiService', fn ($q) => $q
                ->where('code', $serviceCode)
                ->where('is_active', true));
    }
}
