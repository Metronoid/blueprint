<?php

namespace BlueprintExtensions\Auditing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditCommit extends Model
{
    protected $fillable = [
        'id',
        'branch_id',
        'message',
        'metadata',
        'state',
        'parent_commit_id',
        'created_by',
        'commit_hash',
        'is_merge_commit',
        'merge_source_branch_id',
        'merge_strategy',
    ];

    protected $casts = [
        'metadata' => 'array',
        'state' => 'array',
        'is_merge_commit' => 'boolean',
    ];

    /**
     * Get the branch that this commit belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(AuditBranch::class, 'branch_id');
    }

    /**
     * Get the parent commit.
     */
    public function parentCommit(): BelongsTo
    {
        return $this->belongsTo(AuditCommit::class, 'parent_commit_id');
    }

    /**
     * Get the child commits.
     */
    public function childCommits(): HasMany
    {
        return $this->hasMany(AuditCommit::class, 'parent_commit_id');
    }

    /**
     * Get the user who created this commit.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /**
     * Get the source branch for merge commits.
     */
    public function mergeSourceBranch(): BelongsTo
    {
        return $this->belongsTo(AuditBranch::class, 'merge_source_branch_id');
    }

    /**
     * Get the tags for this commit.
     */
    public function tags(): HasMany
    {
        return $this->hasMany(AuditTag::class, 'commit_id');
    }

    /**
     * Scope to get only merge commits.
     */
    public function scopeMergeCommits($query)
    {
        return $query->where('is_merge_commit', true);
    }

    /**
     * Scope to get commits for a specific branch.
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope to get commits by author.
     */
    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('created_by', $authorId);
    }

    /**
     * Get the short commit hash (first 8 characters).
     */
    public function getShortHash(): string
    {
        return substr($this->commit_hash, 0, 8);
    }

    /**
     * Get the commit message summary (first line).
     */
    public function getMessageSummary(): string
    {
        $lines = explode("\n", $this->message);
        return trim($lines[0]);
    }

    /**
     * Get the commit message body (everything after the first line).
     */
    public function getMessageBody(): string
    {
        $lines = explode("\n", $this->message);
        array_shift($lines); // Remove first line
        return trim(implode("\n", $lines));
    }

    /**
     * Check if this commit has a parent.
     */
    public function hasParent(): bool
    {
        return !is_null($this->parent_commit_id);
    }

    /**
     * Check if this commit has children.
     */
    public function hasChildren(): bool
    {
        return $this->childCommits()->exists();
    }

    /**
     * Get the number of child commits.
     */
    public function getChildCount(): int
    {
        return $this->childCommits()->count();
    }

    /**
     * Get the commit tree (all descendants).
     */
    public function getCommitTree(): \Illuminate\Support\Collection
    {
        $tree = collect([$this]);

        foreach ($this->childCommits as $child) {
            $tree = $tree->merge($child->getCommitTree());
        }

        return $tree;
    }

    /**
     * Get the commit path to root (all ancestors).
     */
    public function getCommitPath(): \Illuminate\Support\Collection
    {
        $path = collect([$this]);
        $current = $this->parentCommit;

        while ($current) {
            $path->prepend($current);
            $current = $current->parentCommit;
        }

        return $path;
    }

    /**
     * Get the diff with the parent commit.
     */
    public function getDiffWithParent(): array
    {
        if (!$this->hasParent()) {
            return [];
        }

        $parentState = $this->parentCommit->state ?? [];
        $currentState = $this->state ?? [];

        return $this->calculateDiff($parentState, $currentState);
    }

    /**
     * Get the diff with a specific commit.
     */
    public function getDiffWith(AuditCommit $commit): array
    {
        $commitState = $commit->state ?? [];
        $currentState = $this->state ?? [];

        return $this->calculateDiff($commitState, $currentState);
    }

    /**
     * Calculate diff between two states.
     */
    protected function calculateDiff(array $state1, array $state2): array
    {
        $diff = [];
        
        $allKeys = array_unique(array_merge(array_keys($state1), array_keys($state2)));
        
        foreach ($allKeys as $key) {
            $value1 = $state1[$key] ?? null;
            $value2 = $state2[$key] ?? null;
            
            if ($value1 !== $value2) {
                $diff[$key] = [
                    'old' => $value1,
                    'new' => $value2,
                ];
            }
        }
        
        return $diff;
    }

    /**
     * Check if this commit is a descendant of another commit.
     */
    public function isDescendantOf(AuditCommit $commit): bool
    {
        $current = $this->parentCommit;

        while ($current) {
            if ($current->id === $commit->id) {
                return true;
            }
            $current = $current->parentCommit;
        }

        return false;
    }

    /**
     * Check if this commit is an ancestor of another commit.
     */
    public function isAncestorOf(AuditCommit $commit): bool
    {
        return $commit->isDescendantOf($this);
    }

    /**
     * Get the common ancestor with another commit.
     */
    public function getCommonAncestor(AuditCommit $commit): ?AuditCommit
    {
        $ancestors = $this->getCommitPath();
        $commitAncestors = $commit->getCommitPath();

        foreach ($ancestors as $ancestor) {
            if ($commitAncestors->contains($ancestor)) {
                return $ancestor;
            }
        }

        return null;
    }

    /**
     * Get the number of commits between this commit and another.
     */
    public function getDistanceTo(AuditCommit $commit): int
    {
        $commonAncestor = $this->getCommonAncestor($commit);
        
        if (!$commonAncestor) {
            return -1; // No common ancestor
        }

        $distance1 = $this->getCommitPath()->search($commonAncestor);
        $distance2 = $commit->getCommitPath()->search($commonAncestor);

        return $distance1 + $distance2;
    }

    /**
     * Check if this commit is a merge commit.
     */
    public function isMerge(): bool
    {
        return $this->is_merge_commit;
    }

    /**
     * Get the merge information for merge commits.
     */
    public function getMergeInfo(): ?array
    {
        if (!$this->isMerge()) {
            return null;
        }

        return [
            'source_branch' => $this->mergeSourceBranch,
            'strategy' => $this->merge_strategy,
        ];
    }
} 