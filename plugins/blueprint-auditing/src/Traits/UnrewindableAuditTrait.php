<?php

namespace BlueprintExtensions\Auditing\Traits;

use OwenIt\Auditing\Models\Audit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait UnrewindableAuditTrait
{
    /**
     * Mark an audit as unrewindable.
     *
     * @param int $auditId
     * @param string|null $reason
     * @param array $metadata
     * @return bool
     */
    public function markAuditAsUnrewindable(int $auditId, ?string $reason = null, array $metadata = []): bool
    {
        try {
            $audit = Audit::find($auditId);
            
            if (!$audit) {
                Log::warning('Attempted to mark non-existent audit as unrewindable', [
                    'audit_id' => $auditId,
                    'user_id' => Auth::id(),
                    'reason' => $reason
                ]);
                return false;
            }

            // Check if the audit belongs to this model
            if ($audit->auditable_type !== get_class($this) || $audit->auditable_id !== $this->id) {
                Log::warning('Attempted to mark audit as unrewindable for different model', [
                    'audit_id' => $auditId,
                    'audit_model_type' => $audit->auditable_type,
                    'audit_model_id' => $audit->auditable_id,
                    'current_model_type' => get_class($this),
                    'current_model_id' => $this->id,
                    'user_id' => Auth::id()
                ]);
                return false;
            }

            // Update the audit
            $audit->update([
                'is_unrewindable' => true,
                'tags' => $this->addUnrewindableTag($audit->tags),
                'metadata' => array_merge($audit->metadata ?? [], [
                    'unrewindable_reason' => $reason,
                    'unrewindable_marked_by' => Auth::id(),
                    'unrewindable_marked_at' => now()->toISOString(),
                    'unrewindable_metadata' => $metadata
                ])
            ]);

            Log::info('Audit marked as unrewindable', [
                'audit_id' => $auditId,
                'model_type' => get_class($this),
                'model_id' => $this->id,
                'reason' => $reason,
                'user_id' => Auth::id()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark audit as unrewindable', [
                'audit_id' => $auditId,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            return false;
        }
    }

    /**
     * Mark multiple audits as unrewindable.
     *
     * @param array $auditIds
     * @param string|null $reason
     * @param array $metadata
     * @return array
     */
    public function markAuditsAsUnrewindable(array $auditIds, ?string $reason = null, array $metadata = []): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($auditIds as $auditId) {
            if ($this->markAuditAsUnrewindable($auditId, $reason, $metadata)) {
                $results['success'][] = $auditId;
            } else {
                $results['failed'][] = $auditId;
            }
        }

        return $results;
    }

    /**
     * Check if an audit can be rewound.
     *
     * @param int $auditId
     * @return bool
     */
    public function canRewindAudit(int $auditId): bool
    {
        $audit = Audit::find($auditId);
        
        if (!$audit) {
            return false;
        }

        // Check if the audit belongs to this model
        if ($audit->auditable_type !== get_class($this) || $audit->auditable_id !== $this->id) {
            return false;
        }

        return !$audit->is_unrewindable;
    }

    /**
     * Get all rewindable audits for this model.
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRewindableAudits(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->audits()
            ->where('is_unrewindable', false)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all unrewindable audits for this model.
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnrewindableAudits(int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $this->audits()
            ->where('is_unrewindable', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the reason why an audit is unrewindable.
     *
     * @param int $auditId
     * @return string|null
     */
    public function getUnrewindableReason(int $auditId): ?string
    {
        $audit = Audit::find($auditId);
        
        if (!$audit || !$audit->is_unrewindable) {
            return null;
        }

        return $audit->metadata['unrewindable_reason'] ?? null;
    }

    /**
     * Mark all audits in a date range as unrewindable.
     *
     * @param string $startDate
     * @param string $endDate
     * @param string|null $reason
     * @param array $metadata
     * @return array
     */
    public function markAuditsInRangeAsUnrewindable(string $startDate, string $endDate, ?string $reason = null, array $metadata = []): array
    {
        $audits = $this->audits()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_unrewindable', false)
            ->get();

        $auditIds = $audits->pluck('id')->toArray();
        
        return $this->markAuditsAsUnrewindable($auditIds, $reason, $metadata);
    }

    /**
     * Mark all audits of a specific event type as unrewindable.
     *
     * @param string $event
     * @param string|null $reason
     * @param array $metadata
     * @return array
     */
    public function markAuditsByEventAsUnrewindable(string $event, ?string $reason = null, array $metadata = []): array
    {
        $audits = $this->audits()
            ->where('event', $event)
            ->where('is_unrewindable', false)
            ->get();

        $auditIds = $audits->pluck('id')->toArray();
        
        return $this->markAuditsAsUnrewindable($auditIds, $reason, $metadata);
    }

    /**
     * Mark all audits by a specific user as unrewindable.
     *
     * @param int $userId
     * @param string|null $reason
     * @param array $metadata
     * @return array
     */
    public function markAuditsByUserAsUnrewindable(int $userId, ?string $reason = null, array $metadata = []): array
    {
        $audits = $this->audits()
            ->where('user_id', $userId)
            ->where('is_unrewindable', false)
            ->get();

        $auditIds = $audits->pluck('id')->toArray();
        
        return $this->markAuditsAsUnrewindable($auditIds, $reason, $metadata);
    }

    /**
     * Get statistics about rewindable vs unrewindable audits.
     *
     * @return array
     */
    public function getRewindableStatistics(): array
    {
        $totalAudits = $this->audits()->count();
        $rewindableAudits = $this->audits()->where('is_unrewindable', false)->count();
        $unrewindableAudits = $this->audits()->where('is_unrewindable', true)->count();

        return [
            'total' => $totalAudits,
            'rewindable' => $rewindableAudits,
            'unrewindable' => $unrewindableAudits,
            'rewindable_percentage' => $totalAudits > 0 ? round(($rewindableAudits / $totalAudits) * 100, 2) : 0,
            'unrewindable_percentage' => $totalAudits > 0 ? round(($unrewindableAudits / $totalAudits) * 100, 2) : 0
        ];
    }

    /**
     * Add unrewindable tag to existing tags.
     *
     * @param string|null $existingTags
     * @return string
     */
    protected function addUnrewindableTag(?string $existingTags): string
    {
        $tags = $existingTags ? explode(',', $existingTags) : [];
        
        if (!in_array('unrewindable', $tags)) {
            $tags[] = 'unrewindable';
        }
        
        return implode(',', $tags);
    }

    /**
     * Check if the current user has permission to mark audits as unrewindable.
     *
     * @return bool
     */
    protected function canMarkAuditsAsUnrewindable(): bool
    {
        // Override this method in your model to implement custom permission logic
        return Auth::check();
    }

    /**
     * Get the API endpoint for marking audits as unrewindable.
     *
     * @return string
     */
    public function getUnrewindableApiEndpoint(): string
    {
        $modelName = class_basename($this);
        return route("api.{$modelName}.audits.mark-unrewindable", $this->id);
    }
} 