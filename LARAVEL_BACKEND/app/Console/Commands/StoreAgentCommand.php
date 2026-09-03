<?php

namespace App\Console\Commands;

use App\Services\Store\AgentStoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class StoreAgentCommand extends Command
{
    protected $signature = 'store:agent
                            {action=stores : Action: stores, list, add, update, remove}
                            {--store= : Store ID or slug}
                            {--name= : Product name}
                            {--price= : Product price}
                            {--stock= : Product stock}
                            {--category= : Product category}
                            {--description= : Product description}
                            {--status= : Product status (active/inactive)}
                            {--force : Force permanent deletion rather than archive}
                            {--remote= : Remote base URL (e.g. https://relayiq.app)}
                            {--json : Output raw JSON}';

    protected $description = 'Autonomous Agent Store Gateway — manage products and stores on local or remote servers';

    public function handle(AgentStoreService $storeService): int
    {
        $action    = strtolower((string) $this->argument('action'));
        $remoteUrl = (string) $this->option('remote');

        if (! empty($remoteUrl)) {
            return $this->handleRemote($action, $remoteUrl);
        }

        return $this->handleLocal($action, $storeService);
    }

    private function handleLocal(string $action, AgentStoreService $storeService): int
    {
        try {
            switch ($action) {
                case 'stores':
                    $stores = $storeService->listStores();
                    if ($this->option('json')) {
                        $this->line(json_encode($stores, JSON_PRETTY_PRINT));
                        return self::SUCCESS;
                    }
                    $this->info('🏪 Registered Stores: ' . count($stores));
                    $this->table(['ID', 'Name', 'Slug', 'Status', 'Products'], array_map(fn ($s) => [
                        $s['id'], $s['name'], $s['store_slug'], $s['status'], $s['products_count'],
                    ], $stores));
                    return self::SUCCESS;

                case 'list':
                    $store = $this->requireStoreOption($storeService);
                    if (! $store) return self::FAILURE;

                    $products = $storeService->listProducts($store, [
                        'category' => $this->option('category'),
                        'status'   => $this->option('status'),
                    ]);

                    if ($this->option('json')) {
                        $this->line(json_encode($products, JSON_PRETTY_PRINT));
                        return self::SUCCESS;
                    }

                    $this->info("📦 Products for Store: [{$store->name}] (ID: {$store->id})");
                    $this->table(['ID', 'Name', 'Price', 'Stock', 'Category', 'Status'], array_map(fn ($p) => [
                        $p['id'], $p['name'], '$' . number_format($p['price'], 2), $p['stock'], $p['category'] ?? '-', $p['status'],
                    ], $products));
                    return self::SUCCESS;

                case 'add':
                case 'create':
                    $store = $this->requireStoreOption($storeService);
                    if (! $store) return self::FAILURE;

                    $name = $this->option('name');
                    $price = $this->option('price');

                    if (empty($name) || $price === null) {
                        $this->error('Both --name and --price are required to add a product.');
                        return self::FAILURE;
                    }

                    $res = $storeService->createProduct($store, [
                        'name'        => $name,
                        'price'       => $price,
                        'stock'       => $this->option('stock') ?? 0,
                        'category'    => $this->option('category'),
                        'description' => $this->option('description'),
                        'status'      => $this->option('status') ?? 'active',
                    ]);

                    if ($this->option('json')) {
                        $this->line(json_encode($res, JSON_PRETTY_PRINT));
                        return self::SUCCESS;
                    }

                    $this->info("✅ {$res['message']}");
                    $this->line("   ID: {$res['product']['id']} | Slug: {$res['product']['slug']} | Price: \${$res['product']['price']}");
                    return self::SUCCESS;

                case 'update':
                    $store = $this->requireStoreOption($storeService);
                    if (! $store) return self::FAILURE;

                    $name = $this->option('name');
                    if (empty($name)) {
                        $this->error('Provide --name (or ID) of the product to update.');
                        return self::FAILURE;
                    }

                    $updates = array_filter([
                        'price'       => $this->option('price'),
                        'stock'       => $this->option('stock'),
                        'category'    => $this->option('category'),
                        'description' => $this->option('description'),
                        'status'      => $this->option('status'),
                    ], fn ($v) => $v !== null);

                    $res = $storeService->updateProduct($store, $name, $updates);

                    if ($this->option('json')) {
                        $this->line(json_encode($res, JSON_PRETTY_PRINT));
                        return self::SUCCESS;
                    }

                    $this->info("✅ {$res['message']}");
                    return self::SUCCESS;

                case 'remove':
                case 'delete':
                    $store = $this->requireStoreOption($storeService);
                    if (! $store) return self::FAILURE;

                    $name = $this->option('name');
                    if (empty($name)) {
                        $this->error('Provide --name (or ID) of the product to remove.');
                        return self::FAILURE;
                    }

                    $res = $storeService->deleteProduct($store, $name, (bool) $this->option('force'));

                    if ($this->option('json')) {
                        $this->line(json_encode($res, JSON_PRETTY_PRINT));
                        return self::SUCCESS;
                    }

                    $this->info("✅ {$res['message']}");
                    return self::SUCCESS;

                default:
                    $this->error("Unknown action '{$action}'. Valid: stores, list, add, update, remove.");
                    return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function handleRemote(string $action, string $remoteUrl): int
    {
        $key = env('DEPLOY_AGENT_KEY', env('DEPLOY_SECRET'));
        if (empty($key)) {
            $this->error('Missing DEPLOY_AGENT_KEY / DEPLOY_SECRET in environment for remote call.');
            return self::FAILURE;
        }

        $endpoint = rtrim($remoteUrl, '/') . '/api/agent/store';

        $payload = [
            'action'      => $action === 'stores' ? 'list_stores' : ($action === 'list' ? 'list_products' : $action),
            'store'       => $this->option('store'),
            'name'        => $this->option('name'),
            'price'       => $this->option('price'),
            'stock'       => $this->option('stock'),
            'category'    => $this->option('category'),
            'description' => $this->option('description'),
            'status'      => $this->option('status'),
            'force'       => (bool) $this->option('force'),
        ];

        try {
            $res = Http::withHeaders([
                'X-Deploy-Agent-Key' => $key,
                'Content-Type'       => 'application/json',
            ])->post($endpoint, array_filter($payload, fn ($v) => $v !== null));

            if ($this->option('json')) {
                $this->line($res->body());
                return $res->successful() ? self::SUCCESS : self::FAILURE;
            }

            $data = $res->json();
            if (! $res->successful()) {
                $this->error('Remote Error: ' . ($data['message'] ?? $res->body()));
                return self::FAILURE;
            }

            $this->info("🌐 Remote [{$remoteUrl}] Response:");
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Remote Request Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function requireStoreOption(AgentStoreService $storeService)
    {
        $storeId = $this->option('store');
        if (empty($storeId)) {
            $this->error('Missing --store option (company ID or store slug).');
            return null;
        }

        $store = $storeService->resolveCompany($storeId);
        if (! $store) {
            $this->error("Store '{$storeId}' not found.");
            return null;
        }

        return $store;
    }
}
