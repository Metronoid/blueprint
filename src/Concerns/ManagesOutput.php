<?php

namespace Blueprint\Concerns;

trait ManagesOutput
{
    /**
     * The output array tracking created, updated, and skipped files.
     */
    protected array $output = [
        'created' => [],
        'updated' => [],
        'skipped' => []
    ];

    /**
     * Add a file to the created list.
     */
    protected function addCreated(string $path): void
    {
        $this->output['created'][] = $path;
    }

    /**
     * Add a file to the updated list.
     */
    protected function addUpdated(string $path): void
    {
        $this->output['updated'][] = $path;
    }

    /**
     * Add a file to the skipped list.
     */
    protected function addSkipped(string $path): void
    {
        $this->output['skipped'][] = $path;
    }

    /**
     * Merge another output array into this one.
     */
    protected function mergeOutput(array $otherOutput): void
    {
        $this->output['created'] = array_merge($this->output['created'], $otherOutput['created'] ?? []);
        $this->output['updated'] = array_merge($this->output['updated'], $otherOutput['updated'] ?? []);
        $this->output['skipped'] = array_merge($this->output['skipped'], $otherOutput['skipped'] ?? []);
    }

    /**
     * Get the current output array.
     */
    protected function getOutput(): array
    {
        $sorted = $this->output;
        foreach (['created', 'updated', 'skipped'] as $key) {
            sort($sorted[$key]);
        }
        return $sorted;
    }

    /**
     * Reset the output array.
     */
    protected function resetOutput(): void
    {
        $this->output = [
            'created' => [],
            'updated' => [],
            'skipped' => []
        ];
    }

    /**
     * Create a file and track it in the output.
     */
    protected function createFile(string $path, string $content): void
    {
        $this->filesystem->put($path, $content);
        $this->addCreated($path);
    }

    /**
     * Update a file and track it in the output.
     */
    protected function updateFile(string $path, string $content): void
    {
        $this->filesystem->put($path, $content);
        $this->addUpdated($path);
    }

    /**
     * Skip a file and track it in the output.
     */
    protected function skipFile(string $path): void
    {
        $this->addSkipped($path);
    }
} 