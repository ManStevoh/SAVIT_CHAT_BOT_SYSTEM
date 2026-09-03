<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Deploy\DeployAuthService;
use App\Services\Store\AgentStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AgentStoreController extends Controller
{
    public function __construct(
        private readonly DeployAuthService $authService,
        private readonly AgentStoreService $storeService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $key = (string) (
            $request->header('X-Deploy-Agent-Key')
            ?: $request->bearerToken()
            ?: $request->input('key')
            ?: ''
        );

        if (! $this->authService->validateAgentKey($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid or missing agent deploy key.',
            ], 401);
        }

        $action = strtolower((string) ($request->input('action') ?: $request->query('action') ?: 'list_stores'));

        try {
            return match ($action) {
                'list_stores', 'stores' => $this->handleListStores($request),
                'list_products', 'products' => $this->handleListProducts($request),
                'add_product', 'create', 'add' => $this->handleAddProduct($request),
                'update_product', 'update' => $this->handleUpdateProduct($request),
                'remove_product', 'delete', 'archive' => $this->handleDeleteProduct($request),
                'bulk_import', 'bulk' => $this->handleBulkImport($request),
                default => response()->json([
                    'success' => false,
                    'message' => "Unknown action '{$action}'. Valid actions: list_stores, list_products, add_product, update_product, remove_product, bulk_import.",
                ], 400),
            };
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function handleListStores(Request $request): JsonResponse
    {
        $search = $request->input('search') ?: $request->query('search');
        $stores = $this->storeService->listStores($search);

        return response()->json([
            'success' => true,
            'count'   => count($stores),
            'stores'  => $stores,
        ]);
    }

    private function handleListProducts(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store') ?: $request->query('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $filters = [
            'status'   => $request->input('status') ?: $request->query('status'),
            'category' => $request->input('category') ?: $request->query('category'),
            'search'   => $request->input('search') ?: $request->query('search'),
            'limit'    => $request->input('limit') ?: $request->query('limit', 50),
        ];

        $products = $this->storeService->listProducts($company, $filters);

        return response()->json([
            'success'      => true,
            'company_id'   => $company->id,
            'company_name' => $company->name,
            'store_slug'   => $company->store_slug,
            'count'        => count($products),
            'products'     => $products,
        ]);
    }

    private function handleAddProduct(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $data = (array) ($request->input('product') ?: $request->all());

        $result = $this->storeService->createProduct($company, $data);

        return response()->json($result, 201);
    }

    private function handleUpdateProduct(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('id') ?: $request->input('name');
        if (empty($productId)) {
            return response()->json([
                'success' => false,
                'message' => 'Product identifier (product_id or name) is required.',
            ], 400);
        }

        $data = (array) ($request->input('updates') ?: $request->input('product') ?: $request->all());

        $result = $this->storeService->updateProduct($company, $productId, $data);

        return response()->json($result);
    }

    private function handleDeleteProduct(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $productId = $request->input('product_id') ?: $request->input('id') ?: $request->input('name');
        if (empty($productId)) {
            return response()->json([
                'success' => false,
                'message' => 'Product identifier (product_id or name) is required.',
            ], 400);
        }

        $force = (bool) ($request->input('force_delete') ?: $request->input('force') ?: false);

        $result = $this->storeService->deleteProduct($company, $productId, $force);

        return response()->json($result);
    }

    private function handleBulkImport(Request $request): JsonResponse
    {
        $storeId = $request->input('company_id') ?: $request->input('store');
        $company = $this->storeService->resolveCompany($storeId);

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => "Store '{$storeId}' not found. Specify a valid company_id or store_slug.",
            ], 404);
        }

        $items = (array) ($request->input('items') ?: $request->input('products') ?: []);

        $result = $this->storeService->bulkImport($company, $items);

        return response()->json($result, $result['success'] ? 200 : 207);
    }
}
