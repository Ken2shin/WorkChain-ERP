<?php

namespace App\Http\Controllers\Api;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesController extends ApiController
{
    /**
     * Get all sales orders
     */
    public function getOrders(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;
            $perPage = $request->input('per_page', 20);
            $status = $request->input('status');

            $query = SalesOrder::where('tenant_id', $tenantId)
                ->with('customer')
                ->orderBy('created_at', 'desc');

            if ($status) {
                $query->where('status', $status);
            }

            $orders = $query->paginate($perPage);

            return $this->success($orders, 'Sales orders retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching sales orders', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch sales orders', null, 500);
        }
    }

    /**
     * Get all customers
     */
    public function getCustomers(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;
            $perPage = $request->input('per_page', 20);

            $customers = Customer::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->withCount('orders')
                ->paginate($perPage);

            return $this->success($customers, 'Customers retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching customers', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch customers', null, 500);
        }
    }

    /**
     * Create new sales order
     */
    public function createOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|integer|exists:customers,id',
                'order_date' => 'required|date',
                'due_date' => 'required|date|after:order_date',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.unit_price' => 'required|numeric|min:0',
            ]);

            $tenantId = $request->user()->tenant_id;

            // Verify customer belongs to tenant
            $customer = Customer::where('id', $validated['customer_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$customer) {
                return $this->error('Customer not found', null, 404);
            }

            DB::beginTransaction();

            try {
                $totalAmount = 0;
                foreach ($validated['items'] as $item) {
                    $totalAmount += $item['quantity'] * $item['unit_price'];
                }

                $order = SalesOrder::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $validated['customer_id'],
                    'order_date' => $validated['order_date'],
                    'due_date' => $validated['due_date'],
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'created_by' => $request->user()->id,
                ]);

                // Add order items
                foreach ($validated['items'] as $item) {
                    DB::table('sales_order_items')->insert([
                        'sales_order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_price' => $item['quantity'] * $item['unit_price'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::commit();

                return $this->success($order, 'Sales order created successfully', 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error creating sales order', ['error' => $e->getMessage()]);
            return $this->error('Failed to create sales order', null, 500);
        }
    }

    /**
     * Get sales statistics
     */
    public function getStats(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;

            $totalRevenue = SalesOrder::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->sum('total_amount');

            $pendingOrders = SalesOrder::where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->count();

            $totalCustomers = Customer::where('tenant_id', $tenantId)
                ->count();

            $stats = [
                'total_revenue' => $totalRevenue ?? 0,
                'pending_orders' => $pendingOrders,
                'total_customers' => $totalCustomers,
            ];

            return $this->success($stats, 'Sales statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching sales stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }
}
