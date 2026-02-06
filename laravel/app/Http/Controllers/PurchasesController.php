<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchasesController extends ApiController
{
    public function getOrders(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $orders = DB::table('purchase_orders')
                ->where('tenant_id', $tenantId)
                ->select('id', 'order_number', 'supplier_id', 'total_amount', 'status', 'order_date', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->success($orders, 'Purchase orders retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching purchase orders', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch purchase orders', null, 500);
        }
    }

    public function getSuppliers(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $suppliers = DB::table('suppliers')
                ->where('tenant_id', $tenantId)
                ->select('id', 'name', 'email', 'phone', 'city', 'is_active')
                ->orderBy('name')
                ->get();

            return $this->success($suppliers, 'Suppliers retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching suppliers', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch suppliers', null, 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $totalOrders = DB::table('purchase_orders')
                ->where('tenant_id', $tenantId)
                ->count();

            $pendingOrders = DB::table('purchase_orders')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();

            $totalSuppliers = DB::table('suppliers')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count();

            $totalSpending = DB::table('purchase_orders')
                ->where('tenant_id', $tenantId)
                ->sum('total_amount') ?? 0;

            return $this->success([
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'active_suppliers' => $totalSuppliers,
                'total_spending' => floatval($totalSpending),
            ], 'Purchase statistics retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching purchase stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }

    public function createOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|integer|exists:suppliers,id',
                'items' => 'required|array|min:1',
                'notes' => 'nullable|string|max:500',
            ]);

            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $orderId = DB::table('purchase_orders')->insertGetId([
                'tenant_id' => $tenantId,
                'supplier_id' => $validated['supplier_id'],
                'order_number' => 'PO-' . time(),
                'status' => 'pending',
                'order_date' => now(),
                'total_amount' => 0,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success(['id' => $orderId], 'Purchase order created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error creating purchase order', ['error' => $e->getMessage()]);
            return $this->error('Failed to create purchase order', null, 500);
        }
    }
}
