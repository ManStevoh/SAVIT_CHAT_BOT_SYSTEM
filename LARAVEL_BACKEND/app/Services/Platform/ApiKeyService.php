<?php

namespace App\Services\Platform;

use App\Models\Company;
use App\Models\CompanyApiKey;
use App\Models\User;
use Illuminate\Support\Str;

final class ApiKeyService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * @param  list<string>  $scopes
     * @return array{key: CompanyApiKey, plain_text: string}
     */
    public function create(Company $company, User $creator, string $name, array $scopes = ['read']): array
    {
        $plain = 'savit_'.Str::random(40);
        $prefix = substr($plain, 0, 12);

        $record = CompanyApiKey::create([
            'company_id' => $company->id,
            'name' => mb_substr($name, 0, 80),
            'key_prefix' => $prefix,
            'key_hash' => hash('sha256', $plain),
            'scopes' => $scopes,
            'created_by' => $creator->id,
        ]);

        $this->audit->log(
            'api_key.created',
            CompanyApiKey::class,
            $record->id,
            null,
            ['name' => $name, 'scopes' => $scopes],
            $company->id,
            $creator,
        );

        return ['key' => $record, 'plain_text' => $plain];
    }

    public function revoke(CompanyApiKey $key, User $user): CompanyApiKey
    {
        $key->update(['revoked_at' => now()]);

        $this->audit->log(
            'api_key.revoked',
            CompanyApiKey::class,
            $key->id,
            null,
            ['revoked_at' => now()->toIso8601String()],
            $key->company_id,
            $user,
        );

        return $key->fresh();
    }

    public function authenticate(string $plainKey): ?CompanyApiKey
    {
        if (! str_starts_with($plainKey, 'savit_')) {
            return null;
        }

        $prefix = substr($plainKey, 0, 12);
        $hash = hash('sha256', $plainKey);

        $key = CompanyApiKey::where('key_prefix', $prefix)
            ->where('key_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if ($key) {
            $key->update(['last_used_at' => now()]);
        }

        return $key;
    }
}
