<?php

namespace BlueprintExtensions\Auditing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditBranch extends Model
{
    protected $fillable = [
        'id',
        'name',
        'description',
        'parent_branch_id',
        'model_type',
        'model_id',
        'created_by',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent branch.
     */
    public function parentBranch(): BelongsTo
    {
        return $this->belongsTo(AuditBranch::class, 'parent_branch_id');
    }

    /**
     * Get the child branches.
     */
    public function childBranches(): HasMany
    {
        return $this->hasMany(AuditBranch::class, 'parent_branch_id');
    }

    /**
     * Get the commits for this branch.
     */
    public function commits(): HasMany
    {
        return $this->hasMany(AuditCommit::class, 'branch_id')->orderBy('created_at', 'desc');
    }

    /**
     * Get the latest commit for this branch.
     */
    public function latestCommit(): BelongsTo
    {
        return $this->belongsTo(AuditCommit::class, 'latest_commit_id');
    }

    /**
     * Get the model that this branch belongs to.
     */
    public function model(): BelongsTo
    {
        return $this->belongsTo($this->model_type, 'model_id');
    }

    /**
     * Get the user who created this branch.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /**
     * Scope to get only active branches.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get branches for a specific model.
     */
    public function scopeForModel($query, $modelType, $modelId)
    {
        return $query->where('model_type', $modelType)
                    ->where('model_id', $modelId);
    }

    /**
     * Check if this branch has any commits.
     */
    public function hasCommits(): bool
    {
        return $this->commits()->exists();
    }

    /**
     * Get the commit count for this branch.
     */
    public function getCommitCount(): int
    {
        return $this->commits()->count();
    }

    /**
     * Get the branch path (parent -> child hierarchy).
     */
    public function getBranchPath(): string
    {
        $path = [$this->name];
        $parent = $this->parentBranch;

        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parentBranch;
        }

        return implode(' -> ', $path);
    }

    /**
     * Check if this branch is a descendant of another branch.
     */
    public function isDescendantOf(AuditBranch $branch): bool
    {
        $current = $this->parentBranch;

        while ($current) {
            if ($current->id === $branch->id) {
                return true;
            }
            $current = $current->parentBranch;
        }

        return false;
    }

    /**
     * Check if this branch is an ancestor of another branch.
     */
    public function isAncestorOf(AuditBranch $branch): bool
    {
        return $branch->isDescendantOf($this);
    }

    /**
     * Get the common ancestor with another branch.
     */
    public function getCommonAncestor(AuditBranch $branch): ?AuditBranch
    {
        $ancestors = $this->getAncestors();
        $branchAncestors = $branch->getAncestors();

        foreach ($ancestors as $ancestor) {
            if ($branchAncestors->contains($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Get all ancestors of this branch.
     */
    public function getAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();
        $current = $this->parentBranch;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parentBranch;
        }

        return $ancestors;
    }

    /**
     * Get all descendants of this branch.
     */
    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($this->childBranches as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }
} 