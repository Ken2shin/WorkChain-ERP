<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceController extends ApiController
{
    public function getAccounts(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $accounts = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->select('id', 'account_number', 'account_name', 'account_type', 'balance')
                ->orderBy('account_number')
                ->paginate(20);

            return $this->success($accounts, 'Accounts retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching accounts', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch accounts', null, 500);
        }
    }

    public function getReports(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $reportType = $request->query('type', 'income');
            $reports = DB::table('financial_reports')
                ->where('tenant_id', $tenantId)
                ->where('report_type', $reportType)
                ->select('id', 'report_name', 'report_type', 'period_start', 'period_end', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->success($reports, 'Financial reports retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching reports', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch reports', null, 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $cashPosition = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->where('account_type', 'asset')
                ->sum('balance') ?? 0;

            $accountsPayable = DB::table('chart_of_accounts')
                ->where('tenant_id', $tenantId)
                ->where('account_type', 'liability')
                ->sum('balance') ?? 0;

            $accountsReceivable = DB::table('invoices')
                ->where('tenant_id', $tenantId)
                ->where('status', 'pending')
                ->sum('total_amount') ?? 0;

            $totalIncome = DB::table('invoices')
                ->where('tenant_id', $tenantId)
                ->where('status', 'paid')
                ->sum('total_amount') ?? 0;

            $profitMargin = $totalIncome > 0 ? (($totalIncome - $accountsPayable) / $totalIncome * 100) : 0;

            return $this->success([
                'cash_position' => floatval($cashPosition),
                'accounts_payable' => floatval($accountsPayable),
                'accounts_receivable' => floatval($accountsReceivable),
                'profit_margin' => round($profitMargin, 2),
            ], 'Finance statistics retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching finance stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }

    public function createExpense(Request $request)
    {
        try {
            $validated = $request->validate([
                'description' => 'required|string|max:500',
                'amount' => 'required|numeric|min:0.01',
                'category' => 'required|string|max:100',
                'expense_date' => 'required|date',
            ]);

            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $expenseId = DB::table('expenses')->insertGetId([
                'tenant_id' => $tenantId,
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'category' => $validated['category'],
                'expense_date' => $validated['expense_date'],
                'created_by' => auth('jwt')->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success(['id' => $expenseId], 'Expense created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error creating expense', ['error' => $e->getMessage()]);
            return $this->error('Failed to create expense', null, 500);
        }
    }
}
