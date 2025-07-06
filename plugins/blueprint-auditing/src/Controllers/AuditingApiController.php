<?php

namespace BlueprintExtensions\Auditing\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;
use Illuminate\Database\Eloquent\Model;

class AuditingApiController extends Controller
{
    /**
     * Mark a single audit as unrewindable.
     *
     * @param Request $request
     * @param Audit $audit
     * @return JsonResponse
     */
    public function markAuditAsUnrewindable(Request $request, Audit $audit): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $model = $this->getModelFromAudit($audit);
            
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Model not found'
                ], 404);
            }

            $success = $model->markAuditAsUnrewindable(
                $audit->id,
                $request->input('reason'),
                $request->input('metadata', [])
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Audit marked as unrewindable successfully',
                    'data' => [
                        'audit_id' => $audit->id,
                        'reason' => $request->input('reason'),
                        'marked_at' => now()->toISOString()
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark audit as unrewindable'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to mark audit as unrewindable via API', [
                'audit_id' => $audit->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark multiple audits as unrewindable.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function markAuditsAsUnrewindableBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'audit_ids' => 'required|array|min:1',
            'audit_ids.*' => 'integer|exists:audits,id',
            'reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $auditIds = $request->input('audit_ids');
            $reason = $request->input('reason');
            $metadata = $request->input('metadata', []);

            // Group audits by model to process them efficiently
            $audits = Audit::whereIn('id', $auditIds)->get();
            $results = [
                'success' => [],
                'failed' => [],
                'total' => count($auditIds)
            ];

            foreach ($audits as $audit) {
                $model = $this->getModelFromAudit($audit);
                
                if (!$model) {
                    $results['failed'][] = [
                        'audit_id' => $audit->id,
                        'reason' => 'Model not found'
                    ];
                    continue;
                }

                $success = $model->markAuditAsUnrewindable($audit->id, $reason, $metadata);
                
                if ($success) {
                    $results['success'][] = $audit->id;
                } else {
                    $results['failed'][] = [
                        'audit_id' => $audit->id,
                        'reason' => 'Failed to mark as unrewindable'
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk operation completed',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark audits as unrewindable via API (bulk)', [
                'audit_ids' => $request->input('audit_ids'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get rewindable audits for a model.
     *
     * @param Request $request
     * @param string $modelType
     * @param int $modelId
     * @return JsonResponse
     */
    public function getRewindableAudits(Request $request, string $modelType, int $modelId): JsonResponse
    {
        try {
            $model = $this->getModel($modelType, $modelId);
            
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Model not found'
                ], 404);
            }

            $limit = $request->input('limit', 50);
            $audits = $model->getRewindableAudits($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'audits' => $audits,
                    'count' => $audits->count(),
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get rewindable audits via API', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get unrewindable audits for a model.
     *
     * @param Request $request
     * @param string $modelType
     * @param int $modelId
     * @return JsonResponse
     */
    public function getUnrewindableAudits(Request $request, string $modelType, int $modelId): JsonResponse
    {
        try {
            $model = $this->getModel($modelType, $modelId);
            
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Model not found'
                ], 404);
            }

            $limit = $request->input('limit', 50);
            $audits = $model->getUnrewindableAudits($limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'audits' => $audits,
                    'count' => $audits->count(),
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get unrewindable audits via API', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get rewindable statistics for a model.
     *
     * @param Request $request
     * @param string $modelType
     * @param int $modelId
     * @return JsonResponse
     */
    public function getRewindableStatistics(Request $request, string $modelType, int $modelId): JsonResponse
    {
        try {
            $model = $this->getModel($modelType, $modelId);
            
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Model not found'
                ], 404);
            }

            $statistics = $model->getRewindableStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get rewindable statistics via API', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Mark audits by criteria as unrewindable.
     *
     * @param Request $request
     * @param string $modelType
     * @param int $modelId
     * @return JsonResponse
     */
    public function markAuditsByCriteriaAsUnrewindable(Request $request, string $modelType, int $modelId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'criteria' => 'required|string|in:date_range,event_type,user_id',
            'start_date' => 'required_if:criteria,date_range|date',
            'end_date' => 'required_if:criteria,date_range|date|after_or_equal:start_date',
            'event' => 'required_if:criteria,event_type|string|in:created,updated,deleted,restored',
            'user_id' => 'required_if:criteria,user_id|integer|exists:users,id',
            'reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $model = $this->getModel($modelType, $modelId);
            
            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'Model not found'
                ], 404);
            }

            $reason = $request->input('reason');
            $metadata = $request->input('metadata', []);
            $results = [];

            switch ($request->input('criteria')) {
                case 'date_range':
                    $results = $model->markAuditsInRangeAsUnrewindable(
                        $request->input('start_date'),
                        $request->input('end_date'),
                        $reason,
                        $metadata
                    );
                    break;

                case 'event_type':
                    $results = $model->markAuditsByEventAsUnrewindable(
                        $request->input('event'),
                        $reason,
                        $metadata
                    );
                    break;

                case 'user_id':
                    $results = $model->markAuditsByUserAsUnrewindable(
                        $request->input('user_id'),
                        $reason,
                        $metadata
                    );
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => 'Audits marked as unrewindable successfully',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark audits by criteria as unrewindable via API', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'criteria' => $request->input('criteria'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get a model instance from an audit.
     *
     * @param Audit $audit
     * @return Model|null
     */
    protected function getModelFromAudit(Audit $audit): ?Model
    {
        try {
            return $audit->auditable_type::find($audit->auditable_id);
        } catch (\Exception $e) {
            Log::warning('Failed to get model from audit', [
                'audit_id' => $audit->id,
                'auditable_type' => $audit->auditable_type,
                'auditable_id' => $audit->auditable_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get a model instance by type and ID.
     *
     * @param string $modelType
     * @param int $modelId
     * @return Model|null
     */
    protected function getModel(string $modelType, int $modelId): ?Model
    {
        try {
            return $modelType::find($modelId);
        } catch (\Exception $e) {
            Log::warning('Failed to get model', [
                'model_type' => $modelType,
                'model_id' => $modelId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
} 