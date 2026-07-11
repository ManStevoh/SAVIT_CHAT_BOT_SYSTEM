<?php

namespace App\Services\Agent;

use App\Models\CustomerMemory;

final class CustomerMemoryService
{
    public function getForPrompt(int $companyId, string $customerPhone): string
    {
        $phone = $this->normalizePhone($customerPhone);
        $memories = CustomerMemory::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $phone)
            ->orderByDesc('updated_at')
            ->limit((int) config('agent.customer_memory_limit', 20))
            ->get(['memory_key', 'memory_value', 'category']);

        if ($memories->isEmpty()) {
            return '';
        }

        $lines = ["Customer profile (persistent memory):"];
        foreach ($memories as $memory) {
            $lines[] = "- [{$memory->category}] {$memory->memory_key}: {$memory->memory_value}";
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array{key: string, value: string, category: string}>
     */
    public function list(int $companyId, string $customerPhone): array
    {
        $phone = $this->normalizePhone($customerPhone);

        return CustomerMemory::query()
            ->where('company_id', $companyId)
            ->where('customer_phone', $phone)
            ->orderByDesc('updated_at')
            ->limit((int) config('agent.customer_memory_limit', 20))
            ->get(['memory_key', 'memory_value', 'category'])
            ->map(fn (CustomerMemory $m) => [
                'key' => $m->memory_key,
                'value' => $m->memory_value,
                'category' => $m->category,
            ])
            ->all();
    }

    public function upsert(
        int $companyId,
        string $customerPhone,
        string $key,
        string $value,
        string $category = 'preference',
        string $source = 'agent',
        float $confidence = 0.85,
    ): CustomerMemory {
        $key = mb_substr(trim($key), 0, 120);
        $value = mb_substr(trim($value), 0, 2000);

        return CustomerMemory::updateOrCreate(
            [
                'company_id' => $companyId,
                'customer_phone' => $this->normalizePhone($customerPhone),
                'memory_key' => $key,
            ],
            [
                'memory_value' => $value,
                'category' => mb_substr($category, 0, 40),
                'confidence' => max(0.0, min(1.0, $confidence)),
                'source' => mb_substr($source, 0, 40),
            ],
        );
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? $phone;
    }
}
