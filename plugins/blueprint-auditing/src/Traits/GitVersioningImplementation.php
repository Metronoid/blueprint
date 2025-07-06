<?php

namespace BlueprintExtensions\Auditing\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use BlueprintExtensions\Auditing\Models\AuditBranch;
use BlueprintExtensions\Auditing\Models\AuditCommit;
use BlueprintExtensions\Auditing\Models\AuditTag;

trait GitVersioningImplementation
{
    use GitLikeVersioningTrait;

    /**
     * Get the current branch ID for this model.
     */
    protected function getCurrentBranch(): ?string
    {
        $tracking = $this->getVersionTracking();
        return $tracking?->current_branch_id;
    }

    /**
     * Set the current branch ID for this model.
     */
    protected function setCurrentBranch(string $branchId): void
    {
        $tracking = $this->getOrCreateVersionTracking();
        $tracking->current_branch_id = $branchId;
        $tracking->save();
    }

    /**
     * Get a branch by ID.
     */
    protected function getBranch(string $branchId): ?array
    {
        $branch = AuditBranch::find($branchId);
        
        if (!$branch || $branch->model_type !== get_class($this) || $branch->model_id !== $this->getKey()) {
            return null;
        }

        return $branch->toArray();
    }

    /**
     * Get all branches for this model.
     */
    protected function getAllBranches(): Collection
    {
        return AuditBranch::forModel(get_class($this), $this->getKey())
            ->active()
            ->with(['latestCommit', 'creator'])
            ->get();
    }

    /**
     * Get commits for a branch.
     */
    protected function getCommits(string $branchId, int $limit = 50): Collection
    {
        return AuditCommit::forBranch($branchId)
            ->with(['author', 'parentCommit'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get a commit by ID.
     */
    protected function getCommit(string $commitId): ?array
    {
        $commit = AuditCommit::with(['branch', 'author', 'parentCommit'])->find($commitId);
        
        if (!$commit || $commit->branch->model_type !== get_class($this) || $commit->branch->model_id !== $this->getKey()) {
            return null;
        }

        return $commit->toArray();
    }

    /**
     * Get the latest commit for a branch.
     */
    protected function getLatestCommit(string $branchId): ?array
    {
        $commit = AuditCommit::forBranch($branchId)
            ->with(['author', 'parentCommit'])
            ->orderBy('created_at', 'desc')
            ->first();

        return $commit?->toArray();
    }

    /**
     * Store a commit in the database.
     */
    protected function storeCommit(array $commitData): void
    {
        // Generate commit hash
        $commitData['commit_hash'] = $this->generateCommitHash($commitData);
        
        // Create the commit
        $commit = AuditCommit::create($commitData);
        
        // Update the branch's latest commit
        $branch = AuditBranch::find($commitData['branch_id']);
        if ($branch) {
            $branch->latest_commit_id = $commit->id;
            $branch->save();
        }
        
        // Update model's current commit
        $tracking = $this->getOrCreateVersionTracking();
        $tracking->current_commit_id = $commit->id;
        $tracking->save();
    }

    /**
     * Delete a branch record.
     */
    protected function deleteBranchRecord(string $branchId): bool
    {
        $branch = AuditBranch::find($branchId);
        
        if (!$branch || $branch->model_type !== get_class($this) || $branch->model_id !== $this->getKey()) {
            return false;
        }

        // Check if this is the current branch
        if ($branchId === $this->getCurrentBranch()) {
            return false;
        }

        return $branch->delete();
    }

    /**
     * Check if a branch has unmerged changes.
     */
    protected function hasUnmergedChanges(string $branchId): bool
    {
        $branch = AuditBranch::find($branchId);
        
        if (!$branch) {
            return false;
        }

        // Check if the branch has commits that are not in the main branch
        $mainBranch = $this->getMainBranch();
        
        if (!$mainBranch) {
            return false;
        }

        $branchCommits = $this->getCommits($branchId);
        $mainBranchCommits = $this->getCommits($mainBranch->id);

        // Check if any commits in this branch are not in the main branch
        foreach ($branchCommits as $commit) {
            $found = false;
            foreach ($mainBranchCommits as $mainCommit) {
                if ($commit->id === $mainCommit->id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a tag record.
     */
    protected function createTagRecord(string $tagName, string $commitId, ?string $message): string
    {
        $tagId = Str::uuid()->toString();
        
        AuditTag::create([
            'id' => $tagId,
            'name' => $tagName,
            'message' => $message,
            'commit_id' => $commitId,
            'created_by' => auth()->id(),
            'tag_type' => $message ? 'annotated' : 'lightweight',
        ]);

        return $tagId;
    }

    /**
     * Set the current commit ID.
     */
    protected function setCurrentCommitId(string $commitId): void
    {
        $tracking = $this->getOrCreateVersionTracking();
        $tracking->current_commit_id = $commitId;
        $tracking->save();
    }

    /**
     * Get the changes since the last commit.
     */
    protected function getChanges(): array
    {
        $currentCommitId = $this->getCurrentCommitId();
        
        if (!$currentCommitId) {
            return $this->getCurrentState();
        }

        $currentCommit = $this->getCommit($currentCommitId);
        
        if (!$currentCommit) {
            return $this->getCurrentState();
        }

        $currentState = $this->getCurrentState();
        $commitState = $currentCommit['state'] ?? [];

        $changes = [];
        foreach ($currentState as $key => $value) {
            if (!array_key_exists($key, $commitState) || $commitState[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    /**
     * Get the main branch for this model.
     */
    protected function getMainBranch(): ?AuditBranch
    {
        return AuditBranch::forModel(get_class($this), $this->getKey())
            ->where('name', 'main')
            ->first();
    }

    /**
     * Initialize Git versioning for this model.
     */
    public function initializeGitVersioning(): void
    {
        // Create main branch if it doesn't exist
        $mainBranch = $this->getMainBranch();
        
        if (!$mainBranch) {
            $mainBranchId = $this->createBranch('main', null, 'Main branch');
            
            // Create initial commit
            $this->commit('Initial commit');
        }
    }

    /**
     * Get the version tracking record for this model.
     */
    protected function getVersionTracking()
    {
        return DB::table('model_version_tracking')
            ->where('model_type', get_class($this))
            ->where('model_id', $this->getKey())
            ->first();
    }

    /**
     * Get or create the version tracking record for this model.
     */
    protected function getOrCreateVersionTracking()
    {
        $tracking = $this->getVersionTracking();
        
        if (!$tracking) {
            $trackingId = Str::uuid()->toString();
            
            DB::table('model_version_tracking')->insert([
                'id' => $trackingId,
                'model_type' => get_class($this),
                'model_id' => $this->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $tracking = $this->getVersionTracking();
        }

        return $tracking;
    }

    /**
     * Generate a commit hash based on commit data.
     */
    protected function generateCommitHash(array $commitData): string
    {
        $data = [
            'branch_id' => $commitData['branch_id'],
            'message' => $commitData['message'],
            'state' => $commitData['state'],
            'parent_commit_id' => $commitData['parent_commit_id'] ?? null,
            'timestamp' => $commitData['created_at'] ?? now()->toISOString(),
        ];

        return sha1(json_encode($data));
    }

    /**
     * Get the current state of the model for versioning.
     */
    protected function getCurrentState(): array
    {
        return $this->getRewindableAttributes();
    }

    /**
     * Get rewindable attributes (to be implemented by the model).
     */
    protected function getRewindableAttributes(): array
    {
        // Default implementation - override in your model
        return $this->getFillable();
    }

    /**
     * Get the staged changes from the tracking record.
     */
    protected function getStagedChangesFromTracking(): array
    {
        $tracking = $this->getVersionTracking();
        return $tracking?->staged_changes ? json_decode($tracking->staged_changes, true) : [];
    }

    /**
     * Save staged changes to the tracking record.
     */
    protected function saveStagedChanges(array $changes): void
    {
        $tracking = $this->getOrCreateVersionTracking();
        
        DB::table('model_version_tracking')
            ->where('id', $tracking->id)
            ->update([
                'staged_changes' => json_encode($changes),
                'updated_at' => now(),
            ]);
    }

    /**
     * Clear staged changes in the tracking record.
     */
    protected function clearStagedChanges(): void
    {
        $tracking = $this->getVersionTracking();
        
        if ($tracking) {
            DB::table('model_version_tracking')
                ->where('id', $tracking->id)
                ->update([
                    'staged_changes' => null,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Override the stageChanges method to persist to database.
     */
    public function stageChanges(array $attributes): bool
    {
        $currentStaged = $this->getStagedChangesFromTracking();
        $newStaged = array_merge($currentStaged, $attributes);
        
        $this->saveStagedChanges($newStaged);
        
        return true;
    }

    /**
     * Override the unstageChanges method to clear from database.
     */
    public function unstageChanges(): bool
    {
        $this->clearStagedChanges();
        return true;
    }

    /**
     * Override the getStagedChanges method to get from database.
     */
    public function getStagedChanges(): array
    {
        return $this->getStagedChangesFromTracking();
    }
} 