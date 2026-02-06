<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogisticsController extends ApiController
{
    public function getShipments(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $shipments = DB::table('shipments')
                ->where('tenant_id', $tenantId)
                ->select('id', 'tracking_number', 'origin', 'destination', 'status', 'shipped_date', 'expected_delivery')
                ->orderBy('shipped_date', 'desc')
                ->paginate(20);

            return $this->success($shipments, 'Shipments retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching shipments', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch shipments', null, 500);
        }
    }

    public function getTracking(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $trackingNumber = $request->query('tracking_number');
            if (!$trackingNumber) {
                return $this->error('Tracking number is required', null, 400);
            }

            $tracking = DB::table('shipment_tracking')
                ->where('tracking_number', $trackingNumber)
                ->select('id', 'tracking_number', 'location', 'status', 'notes', 'updated_at')
                ->orderBy('updated_at', 'desc')
                ->get();

            return $this->success($tracking, 'Tracking information retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tracking', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch tracking information', null, 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $totalShipments = DB::table('shipments')
                ->where('tenant_id', $tenantId)
                ->count();

            $inTransit = DB::table('shipments')
                ->where('tenant_id', $tenantId)
                ->where('status', 'in_transit')
                ->count();

            $delivered = DB::table('shipments')
                ->where('tenant_id', $tenantId)
                ->where('status', 'delivered')
                ->count();

            $avgDeliveryTime = DB::table('shipments')
                ->where('tenant_id', $tenantId)
                ->where('status', 'delivered')
                ->selectRaw('AVG(DATEDIFF(delivered_date, shipped_date)) as avg_days')
                ->value('avg_days') ?? 0;

            return $this->success([
                'total_shipments' => $totalShipments,
                'in_transit' => $inTransit,
                'delivered' => $delivered,
                'avg_delivery_time' => floatval($avgDeliveryTime),
            ], 'Logistics statistics retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching logistics stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }

    public function createShipment(Request $request)
    {
        try {
            $validated = $request->validate([
                'origin' => 'required|string|max:255',
                'destination' => 'required|string|max:255',
                'items' => 'required|array|min:1',
                'carrier' => 'nullable|string|max:255',
            ]);

            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $shipmentId = DB::table('shipments')->insertGetId([
                'tenant_id' => $tenantId,
                'tracking_number' => 'TR-' . time(),
                'origin' => $validated['origin'],
                'destination' => $validated['destination'],
                'status' => 'pending',
                'shipped_date' => now(),
                'carrier' => $validated['carrier'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success(['id' => $shipmentId], 'Shipment created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error creating shipment', ['error' => $e->getMessage()]);
            return $this->error('Failed to create shipment', null, 500);
        }
    }
}
