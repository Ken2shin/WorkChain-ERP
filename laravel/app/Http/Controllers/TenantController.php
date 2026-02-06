<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HRController extends ApiController
{
    /**
     * Get all employees
     */
    public function getEmployees(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;
            $perPage = $request->input('per_page', 20);

            $employees = Employee::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->with('department')
                ->paginate($perPage);

            return $this->success($employees, 'Employees retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching employees', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch employees', null, 500);
        }
    }

    /**
     * Get departments
     */
    public function getDepartments(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;

            $departments = Department::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->withCount('employees')
                ->get();

            return $this->success($departments, 'Departments retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching departments', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch departments', null, 500);
        }
    }

    /**
     * Create new employee
     */
    public function createEmployee(Request $request)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|unique:employees,email',
                'phone' => 'required|string|max:20',
                'department_id' => 'required|integer|exists:departments,id',
                'position' => 'required|string|max:100',
                'salary' => 'required|numeric|min:0',
                'hire_date' => 'required|date',
            ]);

            $tenantId = $request->user()->tenant_id;

            // Verify department belongs to tenant
            $department = Department::where('id', $validated['department_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$department) {
                return $this->error('Department not found', null, 404);
            }

            $employee = Employee::create([
                'tenant_id' => $tenantId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'department_id' => $validated['department_id'],
                'position' => $validated['position'],
                'salary' => $validated['salary'],
                'hire_date' => $validated['hire_date'],
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            return $this->success($employee, 'Employee created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error creating employee', ['error' => $e->getMessage()]);
            return $this->error('Failed to create employee', null, 500);
        }
    }

    /**
     * Record attendance
     */
    public function recordAttendance(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|integer|exists:employees,id',
                'date' => 'required|date',
                'status' => 'required|string|in:present,absent,late,excused',
                'notes' => 'nullable|string',
            ]);

            $tenantId = $request->user()->tenant_id;

            // Verify employee belongs to tenant
            $employee = Employee::where('id', $validated['employee_id'])
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$employee) {
                return $this->error('Employee not found', null, 404);
            }

            $attendance = Attendance::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'date' => $validated['date'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'status' => $validated['status'],
                    'notes' => $validated['notes'],
                ]
            );

            return $this->success($attendance, 'Attendance recorded successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('Validation failed', $e->errors(), 422);
        } catch (\Exception $e) {
            Log::error('Error recording attendance', ['error' => $e->getMessage()]);
            return $this->error('Failed to record attendance', null, 500);
        }
    }

    /**
     * Get HR statistics
     */
    public function getStats(Request $request)
    {
        try {
            $tenantId = $request->user()->tenant_id;

            $totalEmployees = Employee::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count();

            $departments = Department::where('tenant_id', $tenantId)
                ->count();

            $todayAttendance = Attendance::where('tenant_id', $tenantId)
                ->whereDate('date', today())
                ->where('status', 'present')
                ->count();

            $stats = [
                'total_employees' => $totalEmployees,
                'total_departments' => $departments,
                'today_attendance' => $todayAttendance,
            ];

            return $this->success($stats, 'HR statistics retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching HR stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }
}
