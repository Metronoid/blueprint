<?php

namespace Blueprint\Generators;

use Blueprint\Blueprint;
use Blueprint\Contracts\Model;
use Blueprint\Exceptions\GenerationException;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;

class AbstractClassGenerator
{
    public const INDENT = '        ';

    protected Filesystem $filesystem;

    protected Tree $tree;

    protected array $output = [];

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function types(): array
    {
        return $this->types;
    }

    protected function getPath(Model $model): string
    {
        $path = str_replace('\\', '/', Blueprint::relativeNamespace($model->fullyQualifiedClassName()));

        return sprintf('%s/%s.php', $this->basePath ?? Blueprint::appPath(), $path);
    }

    protected function create(string $path, $content): void
    {
        try {
            $directory = dirname($path);
            
            if (!$this->filesystem->exists($directory)) {
                $this->filesystem->makeDirectory($directory, 0755, true);
            }

            $result = $this->filesystem->put($path, $content);
            if ($result === false) {
                throw GenerationException::fileWriteError($path, 'Failed to write file content');
            }

            $this->output['created'][] = $path;
        } catch (GenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GenerationException::fileWriteError($path, $e->getMessage());
        }
    }

    /**
     * Create or update a file with conflict detection.
     */
    protected function createOrUpdate(string $path, $content, bool $overwrite = false): void
    {
        try {
            if ($this->filesystem->exists($path) && !$overwrite) {
                throw GenerationException::conflictingFile($path, 'creation');
            }

            $directory = dirname($path);
            
            if (!$this->filesystem->exists($directory)) {
                $this->filesystem->makeDirectory($directory, 0755, true);
            }

            $result = $this->filesystem->put($path, $content);
            if ($result === false) {
                throw GenerationException::fileWriteError($path, 'Failed to write file content');
            }

            if ($this->filesystem->exists($path)) {
                $this->output['updated'][] = $path;
            } else {
                $this->output['created'][] = $path;
            }
        } catch (GenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw GenerationException::fileWriteError($path, $e->getMessage());
        }
    }

    /**
     * Validate that a namespace is valid.
     */
    protected function validateNamespace(string $namespace): void
    {
        if (empty($namespace)) {
            throw GenerationException::invalidNamespace($namespace, 'Namespace cannot be empty');
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_\\\\]*$/', $namespace)) {
            throw GenerationException::invalidNamespace($namespace, 'Namespace contains invalid characters');
        }

        if (str_contains($namespace, '\\\\')) {
            throw GenerationException::invalidNamespace($namespace, 'Namespace contains double backslashes');
        }
    }

    /**
     * Safely load a stub file with error handling.
     */
    protected function loadStub(string $stubName): string
    {
        $stubPaths = [
            CUSTOM_STUBS_PATH . '/' . $stubName,
            STUBS_PATH . '/' . $stubName,
        ];

        foreach ($stubPaths as $stubPath) {
            if ($this->filesystem->exists($stubPath)) {
                try {
                    return $this->filesystem->get($stubPath);
                } catch (\Exception $e) {
                    throw GenerationException::invalidStubContent($stubPath, $e->getMessage());
                }
            }
        }

        throw GenerationException::templateNotFound($stubName, $stubPaths);
    }
}
