<?php

namespace BlueprintExtensions\Auditing\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Models\Audit;
use BlueprintExtensions\Auditing\Events\ModelRewound;
use BlueprintExtensions\Auditing\Exceptions\RewindException;

trait RewindableTrait
{
    /**
     * Rewind the model to a specific audit.
     *
     * @param string|int $auditId The audit ID to rewind to
     * @param bool $save Whether to save the model after rewind
     * @return bool True if successful, false otherwise
     * @throws RewindException
     */
    public function rewindTo($auditId, bool $save = true): bool
    {
        $audit = $this->getAuditById($auditId);
        
        if (!$audit) {
            throw new RewindException("Audit with ID {$auditId} not found for this model.");
        }

        return $this->performRewind($audit, $save);
    }

    /**
     * Rewind the model to the state it was in at a specific date.
     *
     * @param Carbon|string $date The date to rewind to
     * @param bool $save Whether to save the model after rewind
     * @return bool True if successful, false otherwise
     * @throws RewindException
     */
    public function rewindToDate($date, bool $save = true): bool
    {
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }

        $audit = $this->getAuditAtDate($date);
        
        if (!$audit) {
            throw new RewindException("No audit found for date {$date->toDateTimeString()}.");
        }

        return $this->performRewind($audit, $save);
    }

    /**
     * Rewind the model by a specific number of steps.
     *
     * @param int $steps Number of steps to rewind
     * @param bool $save Whether to save the model after rewind
     * @return bool True if successful, false otherwise
     * @throws RewindException
     */
    public function rewindSteps(int $steps, bool $save = true): bool
    {
        if ($steps < 1) {
            throw new RewindException("Steps must be a positive integer.");
        }

        $maxSteps = $this->getMaxRewindSteps();
        if ($maxSteps && $steps > $maxSteps) {
            throw new RewindException("Cannot rewind more than {$maxSteps} steps.");
        }

        $audits = $this->getRewindableAudits()->take($steps);
        
        if ($audits->isEmpty()) {
            throw new RewindException("No audits available for rewinding.");
        }

        $targetAudit = $audits->last();
        
        return $this->performRewind($targetAudit, $save);
    }

    /**
     * Get audits that can be used for rewinding.
     *
     * @return Collection
     */
    public function getRewindableAudits(): Collection
    {
        return $this->audits()
            ->whereIn('event', ['created', 'updated'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the previous state of the model (one step back).
     *
     * @param bool $save Whether to save the model after rewind
     * @return bool True if successful, false otherwise
     */
    public function rewindToPrevious(bool $save = true): bool
    {
        return $this->rewindSteps(1, $save);
    }

    /**
     * Get a preview of what the model would look like after rewind.
     *
     * @param string|int $auditId The audit ID to preview
     * @return array The model attributes after rewind
     * @throws RewindException
     */
    public function previewRewind($auditId): array
    {
        $audit = $this->getAuditById($auditId);
        
        if (!$audit) {
            throw new RewindException("Audit with ID {$auditId} not found for this model.");
        }

        return $this->calculateRewindState($audit);
    }

    /**
     * Check if the model can be rewound to a specific audit.
     *
     * @param string|int $auditId The audit ID to check
     * @return bool True if rewind is possible, false otherwise
     */
    public function canRewindTo($auditId): bool
    {
        try {
            $audit = $this->getAuditById($auditId);
            return $audit !== null && $this->isRewindAllowed($audit);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the difference between current state and a specific audit.
     *
     * @param string|int $auditId The audit ID to compare with
     * @return array The differences
     */
    public function getRewindDiff($auditId): array
    {
        $audit = $this->getAuditById($auditId);
        
        if (!$audit) {
            return [];
        }

        $targetState = $this->calculateRewindState($audit);
        $currentState = $this->getRewindableAttributes();

        $diff = [];
        foreach ($targetState as $key => $value) {
            if (!array_key_exists($key, $currentState) || $currentState[$key] !== $value) {
                $diff[$key] = [
                    'current' => $currentState[$key] ?? null,
                    'target' => $value,
                ];
            }
        }

        return $diff;
    }

    /**
     * Perform the actual rewind operation.
     *
     * @param Audit $audit The audit to rewind to
     * @param bool $save Whether to save the model after rewind
     * @return bool True if successful, false otherwise
     */
    protected function performRewind(Audit $audit, bool $save = true): bool
    {
        if (!$this->isRewindAllowed($audit)) {
            return false;
        }

        // Backup current state if configured
        if ($this->shouldBackupBeforeRewind()) {
            $this->backupCurrentState();
        }

        // Calculate the target state
        $targetState = $this->calculateRewindState($audit);

        // Apply the rewind
        $this->fill($targetState);

        // Fire rewind event
        $this->fireRewindEvent($audit);

        // Save if requested
        if ($save) {
            return $this->save();
        }

        return true;
    }

    /**
     * Calculate what the model state should be after rewind.
     *
     * @param Audit $audit The audit to rewind to
     * @return array The target state
     */
    protected function calculateRewindState(Audit $audit): array
    {
        // Start with old values from the audit
        $targetState = $audit->old_values ?? [];

        // Get all audits from the target audit to current
        $subsequentAudits = $this->audits()
            ->where('created_at', '>=', $audit->created_at)
            ->where('id', '!=', $audit->id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Apply changes in reverse to get the state at the target audit
        foreach ($subsequentAudits->reverse() as $subsequentAudit) {
            $oldValues = $subsequentAudit->old_values ?? [];
            foreach ($oldValues as $key => $value) {
                if ($this->isAttributeRewindable($key)) {
                    $targetState[$key] = $value;
                }
            }
        }

        // Filter only rewindable attributes
        return array_filter($targetState, function ($key) {
            return $this->isAttributeRewindable($key);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Get an audit by its ID.
     *
     * @param string|int $auditId The audit ID
     * @return Audit|null The audit or null if not found
     */
    protected function getAuditById($auditId): ?Audit
    {
        return $this->audits()->find($auditId);
    }

    /**
     * Get the audit closest to a specific date.
     *
     * @param Carbon $date The target date
     * @return Audit|null The audit or null if not found
     */
    protected function getAuditAtDate(Carbon $date): ?Audit
    {
        return $this->audits()
            ->where('created_at', '<=', $date)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if rewind is allowed for a specific audit.
     *
     * @param Audit $audit The audit to check
     * @return bool True if allowed, false otherwise
     */
    protected function isRewindAllowed(Audit $audit): bool
    {
        // Check if the audit belongs to this model
        if ($audit->auditable_type !== get_class($this) || $audit->auditable_id !== $this->getKey()) {
            return false;
        }

        // Check if rewind is enabled for this model
        if (!$this->isRewindEnabled()) {
            return false;
        }

        // Check if validation is required and passes
        if ($this->shouldValidateRewind()) {
            return $this->validateRewind($audit);
        }

        return true;
    }

    /**
     * Check if an attribute can be rewound.
     *
     * @param string $attribute The attribute name
     * @return bool True if rewindable, false otherwise
     */
    protected function isAttributeRewindable(string $attribute): bool
    {
        $rewindConfig = $this->getRewindConfiguration();

        // Check include list first (overrides exclude)
        if (isset($rewindConfig['include'])) {
            return in_array($attribute, $rewindConfig['include']);
        }

        // Check exclude list
        if (isset($rewindConfig['exclude'])) {
            return !in_array($attribute, $rewindConfig['exclude']);
        }

        // Check if it's a fillable attribute
        return $this->isFillable($attribute);
    }

    /**
     * Get the current rewindable attributes.
     *
     * @return array The current attribute values
     */
    protected function getRewindableAttributes(): array
    {
        $attributes = $this->getAttributes();
        
        return array_filter($attributes, function ($key) {
            return $this->isAttributeRewindable($key);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Fire the rewind event.
     *
     * @param Audit $audit The audit being rewound to
     */
    protected function fireRewindEvent(Audit $audit): void
    {
        $rewindConfig = $this->getRewindConfiguration();
        
        if (isset($rewindConfig['events']) && in_array('rewound', $rewindConfig['events'])) {
            event(new ModelRewound($this, $audit));
        }
    }

    /**
     * Backup the current state before rewind.
     */
    protected function backupCurrentState(): void
    {
        // Create a backup audit entry
        $this->auditEvent = 'backup_before_rewind';
        $this->isCustomEvent = true;
        $this->save();
    }

    /**
     * Check if rewind is enabled for this model.
     *
     * @return bool True if enabled, false otherwise
     */
    protected function isRewindEnabled(): bool
    {
        $rewindConfig = $this->getRewindConfiguration();
        return $rewindConfig['enabled'] ?? false;
    }

    /**
     * Check if validation is required before rewind.
     *
     * @return bool True if validation required, false otherwise
     */
    protected function shouldValidateRewind(): bool
    {
        $rewindConfig = $this->getRewindConfiguration();
        return $rewindConfig['validate'] ?? false;
    }

    /**
     * Check if backup is required before rewind.
     *
     * @return bool True if backup required, false otherwise
     */
    protected function shouldBackupBeforeRewind(): bool
    {
        $rewindConfig = $this->getRewindConfiguration();
        return $rewindConfig['backup_before_rewind'] ?? false;
    }

    /**
     * Get the maximum number of rewind steps allowed.
     *
     * @return int|null The maximum steps or null if unlimited
     */
    protected function getMaxRewindSteps(): ?int
    {
        $rewindConfig = $this->getRewindConfiguration();
        return $rewindConfig['max_steps'] ?? null;
    }

    /**
     * Validate if a rewind operation is allowed.
     *
     * @param Audit $audit The audit to validate
     * @return bool True if valid, false otherwise
     */
    protected function validateRewind(Audit $audit): bool
    {
        // Override this method in your model for custom validation logic
        return true;
    }

    /**
     * Get the rewind configuration for this model.
     *
     * @return array The rewind configuration
     */
    protected function getRewindConfiguration(): array
    {
        // This will be populated by the generator
        return $this->rewindConfig ?? [];
    }
} 