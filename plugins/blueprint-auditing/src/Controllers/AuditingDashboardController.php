<?php

namespace BlueprintExtensions\Auditing\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Models\Audit;
use BlueprintExtensions\Auditing\Models\AuditBranch;
use BlueprintExtensions\Auditing\Models\AuditCommit;
use BlueprintExtensions\Auditing\Models\AuditTag;
use Carbon\Carbon;

class AuditingDashboardController extends Controller
{
    /**
     * Show the main dashboard overview.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $recentAudits = $this->getRecentAudits();
        $topModels = $this->getTopAuditedModels();
        $originTypes = $this->getOriginTypeStats();
        $rewindStats = $this->getRewindStats();
        $gitStats = $this->getGitVersioningStats();

        return view('blueprint-auditing::dashboard.index', compact(
            'stats',
            'recentAudits',
            'topModels',
            'originTypes',
            'rewindStats',
            'gitStats'
        ));
    }

    /**
     * Show audit history with filtering and search.
     */
    public function auditHistory(Request $request)
    {
        $query = Audit::with(['user', 'auditable']);

        // Apply filters
        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('model_type')) {
            $query->where('auditable_type', $request->model_type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('origin_type')) {
            $query->where('origin_type', $request->origin_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('route_name', 'like', "%{$search}%")
                  ->orWhere('controller_action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $audits = $query->orderBy('created_at', 'desc')
                       ->paginate(50);

        $filters = $request->only(['event', 'model_type', 'user_id', 'origin_type', 'date_from', 'date_to', 'search']);

        return view('blueprint-auditing::dashboard.audit-history', compact('audits', 'filters'));
    }

    /**
     * Show detailed audit information.
     */
    public function showAudit(Audit $audit)
    {
        $audit->load(['user', 'auditable']);
        
        $relatedAudits = Audit::where('audit_group_id', $audit->audit_group_id)
                             ->where('id', '!=', $audit->id)
                             ->orderBy('created_at', 'desc')
                             ->get();

        $sideEffects = $audit->side_effects ? json_decode($audit->side_effects, true) : null;

        return view('blueprint-auditing::dashboard.audit-detail', compact('audit', 'relatedAudits', 'sideEffects'));
    }

    /**
     * Show rewind functionality interface.
     */
    public function rewindInterface(Request $request)
    {
        $modelType = $request->get('model_type');
        $modelId = $request->get('model_id');

        if ($modelType && $modelId) {
            $model = $modelType::find($modelId);
            if ($model && method_exists($model, 'getRewindableAudits')) {
                $rewindableAudits = $model->getRewindableAudits();
                return view('blueprint-auditing::dashboard.rewind-interface', compact('model', 'rewindableAudits'));
            }
        }

        $models = $this->getModelsWithRewind();
        return view('blueprint-auditing::dashboard.rewind-interface', compact('models'));
    }

    /**
     * Perform rewind operation.
     */
    public function performRewind(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'audit_id' => 'required|integer',
            'confirmation' => 'required|accepted'
        ]);

        $model = $request->model_type::find($request->model_id);
        $audit = Audit::find($request->audit_id);

        if (!$model || !$audit) {
            return back()->withErrors(['error' => 'Model or audit not found']);
        }

        try {
            if (method_exists($model, 'rewindTo')) {
                $model->rewindTo($audit->id);
                return back()->with('success', 'Model successfully rewound to previous state');
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Rewind failed: ' . $e->getMessage()]);
        }

        return back()->withErrors(['error' => 'Rewind functionality not available for this model']);
    }

    /**
     * Show origin tracking analysis.
     */
    public function originTracking(Request $request)
    {
        $timeframe = $request->get('timeframe', '7d');
        $originType = $request->get('origin_type');

        $query = Audit::select('origin_type', 'route_name', 'controller_action', DB::raw('count(*) as count'))
                     ->whereNotNull('origin_type');

        if ($originType) {
            $query->where('origin_type', $originType);
        }

        $query->where('created_at', '>=', $this->getTimeframeDate($timeframe))
              ->groupBy('origin_type', 'route_name', 'controller_action')
              ->orderBy('count', 'desc');

        $originData = $query->get();

        $originTypes = Audit::select('origin_type', DB::raw('count(*) as count'))
                           ->whereNotNull('origin_type')
                           ->groupBy('origin_type')
                           ->orderBy('count', 'desc')
                           ->get();

        return view('blueprint-auditing::dashboard.origin-tracking', compact('originData', 'originTypes', 'timeframe'));
    }

    /**
     * Show Git-like versioning interface.
     */
    public function gitVersioning(Request $request)
    {
        $modelType = $request->get('model_type');
        $modelId = $request->get('model_id');

        if ($modelType && $modelId) {
            $model = $modelType::find($modelId);
            if ($model && method_exists($model, 'listBranches')) {
                $branches = $model->listBranches();
                $currentBranch = $model->getCurrentBranchInfo();
                $commits = $model->getCommitHistory(null, 20);
                
                return view('blueprint-auditing::dashboard.git-versioning', compact('model', 'branches', 'currentBranch', 'commits'));
            }
        }

        $models = $this->getModelsWithGitVersioning();
        return view('blueprint-auditing::dashboard.git-versioning', compact('models'));
    }

    /**
     * Show analytics and reporting.
     */
    public function analytics(Request $request)
    {
        $timeframe = $request->get('timeframe', '30d');
        $startDate = $this->getTimeframeDate($timeframe);

        $dailyStats = Audit::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as total'),
            DB::raw('count(DISTINCT user_id) as unique_users'),
            DB::raw('count(DISTINCT auditable_type) as model_types')
        )
        ->where('created_at', '>=', $startDate)
        ->groupBy('date')
        ->orderBy('date')
        ->get();

        $modelStats = Audit::select(
            'auditable_type',
            DB::raw('count(*) as total'),
            DB::raw('count(DISTINCT user_id) as unique_users')
        )
        ->where('created_at', '>=', $startDate)
        ->groupBy('auditable_type')
        ->orderBy('total', 'desc')
        ->get();

        $userStats = Audit::select(
            'user_id',
            DB::raw('count(*) as total'),
            DB::raw('count(DISTINCT auditable_type) as model_types')
        )
        ->where('created_at', '>=', $startDate)
        ->whereNotNull('user_id')
        ->groupBy('user_id')
        ->orderBy('total', 'desc')
        ->limit(20)
        ->get();

        return view('blueprint-auditing::dashboard.analytics', compact('dailyStats', 'modelStats', 'userStats', 'timeframe'));
    }

    /**
     * Get dashboard statistics.
     */
    private function getDashboardStats()
    {
        return Cache::remember('auditing_dashboard_stats', 300, function () {
            $now = Carbon::now();
            $last24h = $now->copy()->subDay();
            $last7d = $now->copy()->subWeek();
            $last30d = $now->copy()->subMonth();

            return [
                'total_audits' => Audit::count(),
                'audits_24h' => Audit::where('created_at', '>=', $last24h)->count(),
                'audits_7d' => Audit::where('created_at', '>=', $last7d)->count(),
                'audits_30d' => Audit::where('created_at', '>=', $last30d)->count(),
                'unique_models' => Audit::distinct('auditable_type')->count(),
                'unique_users' => Audit::distinct('user_id')->whereNotNull('user_id')->count(),
                'total_branches' => AuditBranch::count(),
                'total_commits' => AuditCommit::count(),
                'total_tags' => AuditTag::count(),
            ];
        });
    }

    /**
     * Get recent audits.
     */
    private function getRecentAudits()
    {
        return Audit::with(['user', 'auditable'])
                   ->orderBy('created_at', 'desc')
                   ->limit(10)
                   ->get();
    }

    /**
     * Get top audited models.
     */
    private function getTopAuditedModels()
    {
        return Audit::select('auditable_type', DB::raw('count(*) as count'))
                   ->groupBy('auditable_type')
                   ->orderBy('count', 'desc')
                   ->limit(10)
                   ->get();
    }

    /**
     * Get origin type statistics.
     */
    private function getOriginTypeStats()
    {
        return Audit::select('origin_type', DB::raw('count(*) as count'))
                   ->whereNotNull('origin_type')
                   ->groupBy('origin_type')
                   ->orderBy('count', 'desc')
                   ->get();
    }

    /**
     * Get rewind statistics.
     */
    private function getRewindStats()
    {
        return [
            'models_with_rewind' => $this->getModelsWithRewind()->count(),
            'rewind_operations' => Audit::where('event', 'rewound')->count(),
            'unrewindable_audits' => Audit::where('is_unrewindable', true)->count(),
        ];
    }

    /**
     * Get Git versioning statistics.
     */
    private function getGitVersioningStats()
    {
        return [
            'total_branches' => AuditBranch::count(),
            'active_branches' => AuditBranch::where('is_active', true)->count(),
            'total_commits' => AuditCommit::count(),
            'merge_commits' => AuditCommit::where('is_merge_commit', true)->count(),
            'total_tags' => AuditTag::count(),
        ];
    }

    /**
     * Get models with rewind functionality.
     */
    private function getModelsWithRewind()
    {
        // This would need to be implemented based on your application's model discovery
        // For now, return a collection of common model types
        return collect([
            'App\Models\User',
            'App\Models\Post',
            'App\Models\Document',
            'App\Models\Order',
        ]);
    }

    /**
     * Get models with Git versioning.
     */
    private function getModelsWithGitVersioning()
    {
        // This would need to be implemented based on your application's model discovery
        return collect([
            'App\Models\Document',
            'App\Models\Configuration',
            'App\Models\Workflow',
        ]);
    }

    /**
     * Get date for timeframe.
     */
    private function getTimeframeDate($timeframe)
    {
        return match($timeframe) {
            '1d' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subWeek(),
            '30d' => Carbon::now()->subMonth(),
            '90d' => Carbon::now()->subMonths(3),
            '1y' => Carbon::now()->subYear(),
            default => Carbon::now()->subWeek(),
        };
    }
} 