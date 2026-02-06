<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectsController extends ApiController
{
    public function getProjects(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $projects = DB::table('projects')
                ->where('tenant_id', $tenantId)
                ->select('id', 'name', 'description', 'status', 'start_date', 'end_date', 'budget', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->success($projects, 'Projects retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching projects', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch projects', null, 500);
        }
    }

    public function getTasks(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $projectId = $request->query('project_id');
            $query = DB::table('tasks')
                ->where('tenant_id', $tenantId);

            if ($projectId) {
                $query->where('project_id', $projectId);
            }

            $tasks = $query
                ->select('id', 'project_id', 'title', 'status', 'assigned_to', 'due_date', 'priority')
                ->orderBy('due_date')
                ->paginate(20);

            return $this->success($tasks, 'Tasks retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch tasks', null, 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $totalProjects = DB::table('projects')
                ->where('tenant_id', $tenantId)
                ->count();

            $activeProjects = DB::table('projects')
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count();

            $onHoldProjects = DB::table('projects')
                ->where('tenant_id', $tenantId)
                ->where('status', 'on_hold')
                ->count();

            $totalTasks = DB::table('tasks')
                ->where('tenant_id', $tenantId)
                ->count();

            return $this->success([
                'total_projects' => $totalProjects,
                'active_projects' => $activeProjects,
                'on_hold_projects' => $onHoldProjects,
                'total_tasks' => $totalTasks,
            ], 'Project statistics retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching project stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }

    public function createProject(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'budget' => 'nullable|numeric|min:0',
            ]);

            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $projectId = DB::table('projects')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'description' => $validated['description'],
                'status' => 'active',
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'budget' => $validated['budget'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success(['id' => $projectId], 'Project created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error creating project', ['error' => $e->getMessage()]);
            return $this->error('Failed to create project', null, 500);
        }
    }
}
