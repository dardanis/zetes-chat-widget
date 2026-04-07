<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Project;
use App\Models\ProjectDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $tenantIds = $request->user()->tenants()->pluck('tenants.id');

        $totalTenants = $tenantIds->count();

        $totalProjects = Project::query()
            ->whereIn('tenant_id', $tenantIds)
            ->count();

        $totalDocuments = ProjectDocument::query()
            ->whereIn('tenant_id', $tenantIds)
            ->count();

        $totalChats = ChatSession::query()
            ->whereIn('tenant_id', $tenantIds)
            ->count();

        $recentProjects = Project::query()
            ->whereIn('tenant_id', $tenantIds)
            ->with('tenant:id,name')
            ->latest('created_at')
            ->limit(5)
            ->get();

        $documentsByStatus = ProjectDocument::query()
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw("status, count(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'data' => [
                'total_tenants' => $totalTenants,
                'total_projects' => $totalProjects,
                'total_documents' => $totalDocuments,
                'total_chats' => $totalChats,
                'documents_by_status' => $documentsByStatus,
                'recent_projects' => $recentProjects,
            ],
        ]);
    }
}

