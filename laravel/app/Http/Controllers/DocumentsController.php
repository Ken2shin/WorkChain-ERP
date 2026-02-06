<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentsController extends ApiController
{
    public function getDocuments(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $documents = DB::table('documents')
                ->where('tenant_id', $tenantId)
                ->select('id', 'title', 'file_name', 'file_type', 'file_size', 'uploaded_by', 'created_at')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return $this->success($documents, 'Documents retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching documents', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch documents', null, 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $totalDocuments = DB::table('documents')
                ->where('tenant_id', $tenantId)
                ->count();

            $storageUsed = DB::table('documents')
                ->where('tenant_id', $tenantId)
                ->sum('file_size') ?? 0;

            $complianceItems = DB::table('documents')
                ->where('tenant_id', $tenantId)
                ->where('category', 'compliance')
                ->count();

            $pendingApproval = DB::table('documents')
                ->where('tenant_id', $tenantId)
                ->where('approval_status', 'pending')
                ->count();

            return $this->success([
                'total_documents' => $totalDocuments,
                'storage_used_mb' => round($storageUsed / 1024 / 1024, 2),
                'compliance_items' => $complianceItems,
                'pending_approval' => $pendingApproval,
            ], 'Document statistics retrieved', 200);
        } catch (\Exception $e) {
            Log::error('Error fetching document stats', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch statistics', null, 500);
        }
    }

    public function uploadDocument(Request $request)
    {
        try {
            $validated = $request->validate([
                'file' => 'required|file|max:51200', // 50MB max
                'title' => 'required|string|max:255',
                'category' => 'nullable|string|max:100',
            ]);

            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $file = $validated['file'];
            $filename = time() . '_' . $file->getClientOriginalName();
            
            // Store file securely
            $path = Storage::disk('documents')->put("tenant_{$tenantId}", $file);

            $docId = DB::table('documents')->insertGetId([
                'tenant_id' => $tenantId,
                'title' => $validated['title'],
                'file_name' => $filename,
                'file_path' => $path,
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'category' => $validated['category'],
                'uploaded_by' => auth('jwt')->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->success(['id' => $docId], 'Document uploaded successfully', 201);
        } catch (\Exception $e) {
            Log::error('Error uploading document', ['error' => $e->getMessage()]);
            return $this->error('Failed to upload document', null, 500);
        }
    }

    public function deleteDocument(Request $request, $id)
    {
        try {
            $tenantId = auth('jwt')->user()?->tenant_id;
            if (!$tenantId) {
                return $this->error('Tenant ID is required', null, 400);
            }

            $document = DB::table('documents')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$document) {
                return $this->error('Document not found', null, 404);
            }

            // Delete file
            if ($document->file_path) {
                Storage::disk('documents')->delete($document->file_path);
            }

            // Delete record
            DB::table('documents')->where('id', $id)->delete();

            return $this->success(null, 'Document deleted successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error deleting document', ['error' => $e->getMessage()]);
            return $this->error('Failed to delete document', null, 500);
        }
    }
}
