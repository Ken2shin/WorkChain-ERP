<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\Warehouse;
use App\Models\InventoryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryController extends ApiController
{
    /**
     * Get all products with inventory levels
     */
    public function getProducts(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;
            $perPage = $request->input('per_page', 20);

            $products = Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('warehouses')
                ->paginate($perPage);

            return $this->success($products, 'Products retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching products', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch products', null, 500);
        }
    }

    /**
     * Get warehouse inventory details
     */
    public function getWarehouses(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;

            $warehouses = Warehouse::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('inventory')
                ->get();

            return $this->success($warehouses, 'Warehouses retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching warehouses', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch warehouses', null, 500);
        }
    }

    /**
     * Create new product
     */
    public function createProduct(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:products,name',
                'sku' => 'required|string|max:100|unique:products,sku',
                'description' => 'nullable|string',
                'category' => 'required|string|max:100',
                'unit_price' => 'required|numeric|min:0',
                'reorder_point' => 'required|integer|min:0',
            ]);

            $tenantId = $request->user()->tenant_id;

            $product = Product::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'sku' => $validated['sku'],
                'description' => $validated['description'],
                'category' => $validated['category'],
                'unit_price' => $validated['unit_price'],
                'reorder_point' => $validated['reorder_point'],
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            return $this->success($product, 'Product created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error creating product', ['error' => $e->getMessage()]);
            return $this->error('Failed to create product', null, 500);
        }
    }

    /**
     * Adjust stock level
     */
    public function adjustStock(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|integer|exists:products,id',
                'warehouse_id' => 'required|integer|exists:warehouses,id',
                'quantity' => 'required|integer',
                'reason' => 'required|string|in:purchase,sale,adjustment,damage,loss',
                'notes' => 'nullable|string',
            ]);

            $tenantId = $request->user()->tenant_id;

            // Verificar que el producto y almacén pertenecen al tenant
            $product = Product::where('id', $validated['product_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$product) {
                return $this->error('Product not found', null, 404);
            }

            $warehouse = Warehouse::where('id', $validated['warehouse_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$warehouse) {
                return $this->error('Warehouse not found', null, 404);
            }

            DB::beginTransaction();

            try {
                // Update inventory
                $inventory = DB::table('warehouse_inventory')
                    ->where('product_id', $validated['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();

                if (!$inventory) {
                    DB::table('warehouse_inventory')->insert([
                        'product_id' => $validated['product_id'],
                        'warehouse_id' => $validated['warehouse_id'],
                        'quantity' => max(0, $validated['quantity']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('warehouse_inventory')
                        ->where('product_id', $validated['product_id'])
                        ->where('warehouse_id', $validated['warehouse_id'])
                        ->update([
                            'quantity' => max(0, $inventory->quantity + $validated['quantity']),
                            'updated_at' => now(),
                        ]);
                }

                // Log the adjustment
                InventoryLog::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $validated['product_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'quantity_changed' => $validated['quantity'],
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'],
                    'logged_by' => $request->user()->id,
                ]);

                DB::commit();

                return $this->success(null, 'Stock adjusted successfully');
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error adjusting stock', ['error' => $e->getMessage()]);
            return $this->error('Failed to adjust stock', null, 500);
        }
    }

    /**
     * Get inventory statistics
     */
    public function getStats(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;

            $totalProducts = Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count();

            $lowStockItems = Product::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM warehouse_inventory WHERE product_id = products.id) <= reorder_point')
                ->count();

            $stats = [
                'total_products' => $totalProducts,
                'low_stock_items' => $lowStockItems,
                'total_warehouses' => Warehouse::where('tenant_id', $tenantId)->count(),
            ];

            return $this->success($stats, 'Inventory statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching inventory stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }
}
