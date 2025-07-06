<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create audit branches table
        Schema::create('audit_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->uuid('parent_branch_id')->nullable();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->uuid('latest_commit_id')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index(['parent_branch_id']);
            $table->index(['created_by']);
            $table->index(['is_active']);
            $table->unique(['name', 'model_type', 'model_id']);
        });

        // Create audit commits table
        Schema::create('audit_commits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->json('state');
            $table->uuid('parent_commit_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('commit_hash', 40)->unique();
            $table->boolean('is_merge_commit')->default(false);
            $table->uuid('merge_source_branch_id')->nullable();
            $table->string('merge_strategy')->nullable();
            $table->timestamps();

            $table->index(['branch_id']);
            $table->index(['parent_commit_id']);
            $table->index(['created_by']);
            $table->index(['commit_hash']);
            $table->index(['is_merge_commit']);
            $table->index(['created_at']);
        });

        // Create audit tags table
        Schema::create('audit_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('message')->nullable();
            $table->uuid('commit_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->enum('tag_type', ['lightweight', 'annotated'])->default('lightweight');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['commit_id']);
            $table->index(['created_by']);
            $table->index(['tag_type']);
            $table->unique(['name', 'commit_id']);
        });

        // Create model version tracking table
        Schema::create('model_version_tracking', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->uuid('current_branch_id')->nullable();
            $table->uuid('current_commit_id')->nullable();
            $table->json('staged_changes')->nullable();
            $table->timestamps();

            $table->unique(['model_type', 'model_id']);
            $table->index(['current_branch_id']);
            $table->index(['current_commit_id']);
        });

        // Add foreign key constraints
        Schema::table('audit_branches', function (Blueprint $table) {
            $table->foreign('parent_branch_id')->references('id')->on('audit_branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('audit_commits', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('audit_branches')->onDelete('cascade');
            $table->foreign('parent_commit_id')->references('id')->on('audit_commits')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('merge_source_branch_id')->references('id')->on('audit_branches')->onDelete('set null');
        });

        Schema::table('audit_tags', function (Blueprint $table) {
            $table->foreign('commit_id')->references('id')->on('audit_commits')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('model_version_tracking', function (Blueprint $table) {
            $table->foreign('current_branch_id')->references('id')->on('audit_branches')->onDelete('set null');
            $table->foreign('current_commit_id')->references('id')->on('audit_commits')->onDelete('set null');
        });

        // Update audit_branches to reference latest_commit_id
        Schema::table('audit_branches', function (Blueprint $table) {
            $table->foreign('latest_commit_id')->references('id')->on('audit_commits')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key constraints first
        Schema::table('audit_branches', function (Blueprint $table) {
            $table->dropForeign(['latest_commit_id']);
            $table->dropForeign(['parent_branch_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::table('audit_commits', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['parent_commit_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['merge_source_branch_id']);
        });

        Schema::table('audit_tags', function (Blueprint $table) {
            $table->dropForeign(['commit_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::table('model_version_tracking', function (Blueprint $table) {
            $table->dropForeign(['current_branch_id']);
            $table->dropForeign(['current_commit_id']);
        });

        // Drop tables
        Schema::dropIfExists('model_version_tracking');
        Schema::dropIfExists('audit_tags');
        Schema::dropIfExists('audit_commits');
        Schema::dropIfExists('audit_branches');
    }
}; 