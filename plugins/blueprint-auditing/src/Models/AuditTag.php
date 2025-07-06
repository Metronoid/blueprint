<?php

namespace BlueprintExtensions\Auditing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditTag extends Model
{
    protected $fillable = [
        'id',
        'name',
        'message',
        'commit_id',
        'created_by',
        'tag_type', // 'lightweight' or 'annotated'
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the commit that this tag points to.
     */
    public function commit(): BelongsTo
    {
        return $this->belongsTo(AuditCommit::class, 'commit_id');
    }

    /**
     * Get the user who created this tag.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /**
     * Scope to get only lightweight tags.
     */
    public function scopeLightweight($query)
    {
        return $query->where('tag_type', 'lightweight');
    }

    /**
     * Scope to get only annotated tags.
     */
    public function scopeAnnotated($query)
    {
        return $query->where('tag_type', 'annotated');
    }

    /**
     * Check if this is a lightweight tag.
     */
    public function isLightweight(): bool
    {
        return $this->tag_type === 'lightweight';
    }

    /**
     * Check if this is an annotated tag.
     */
    public function isAnnotated(): bool
    {
        return $this->tag_type === 'annotated';
    }

    /**
     * Get the tag reference (refs/tags/name).
     */
    public function getRef(): string
    {
        return "refs/tags/{$this->name}";
    }

    /**
     * Get the short tag name (without refs/tags/ prefix).
     */
    public function getShortName(): string
    {
        return $this->name;
    }

    /**
     * Check if this tag points to a specific commit.
     */
    public function pointsTo(AuditCommit $commit): bool
    {
        return $this->commit_id === $commit->id;
    }

    /**
     * Get the commit hash that this tag points to.
     */
    public function getCommitHash(): string
    {
        return $this->commit->commit_hash ?? '';
    }

    /**
     * Get the short commit hash that this tag points to.
     */
    public function getShortCommitHash(): string
    {
        return $this->commit->getShortHash() ?? '';
    }
} 