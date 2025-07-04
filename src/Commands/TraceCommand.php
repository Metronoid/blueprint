<?php

namespace Blueprint\Commands;

use Blueprint\Blueprint;
use Blueprint\Tracer;
use Blueprint\Exceptions\BlueprintException;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class TraceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blueprint:trace
                            {--path=* : List of paths to search in }
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create definitions for existing models to reference in new drafts';

    protected Filesystem $filesystem;

    private Tracer $tracer;

    public function __construct(Filesystem $filesystem, Tracer $tracer)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
        $this->tracer = $tracer;
    }

    public function handle(): int
    {
        try {
            $blueprint = resolve(Blueprint::class);
            $path = $this->option('path');
            $definitions = $this->tracer->execute($blueprint, $this->filesystem, $path);

            if (empty($definitions)) {
                $this->error('No models found');
                $this->line('');
                $this->line('Suggestions:');
                $this->line('  • Check that your models directory exists and contains model files');
                $this->line('  • Verify that model files follow Laravel naming conventions');
                $this->line('  • Ensure model classes extend Illuminate\\Database\\Eloquent\\Model');
                $this->line('  • Use --path option to specify custom model directories');
            } else {
                $this->info('Traced ' . count($definitions) . ' ' . Str::plural('model', count($definitions)));
            }

            return 0;
        } catch (BlueprintException $e) {
            $this->error('Blueprint Error: ' . $e->getMessage());
            
            // Display suggestions if available
            $suggestions = $e->getSuggestions();
            if (!empty($suggestions)) {
                $this->line('');
                $this->line('Suggestions:');
                foreach ($suggestions as $suggestion) {
                    $this->line("  • {$suggestion}");
                }
            }
            
            return 1;
        } catch (\Exception $e) {
            $this->error('Unexpected Error: ' . $e->getMessage());
            $this->line('');
            $this->line('This appears to be an unexpected error. Please consider:');
            $this->line('  • Checking file permissions in your models directory');
            $this->line('  • Verifying that PHP can read your model files');
            $this->line('  • Reporting this issue if it persists');
            
            if ($this->option('verbose')) {
                $this->line('');
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            return 1;
        }
    }
}
