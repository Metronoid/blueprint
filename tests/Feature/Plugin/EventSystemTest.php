<?php

namespace Tests\Feature\Plugin;

use Blueprint\Blueprint;
use Blueprint\Tree;
use Blueprint\Events\GenerationStarted;
use Blueprint\Events\GenerationCompleted;
use Blueprint\Events\GeneratorExecuting;
use Blueprint\Events\GeneratorExecuted;
use Blueprint\Contracts\Generator;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventSystemTest extends TestCase
{
    private Blueprint $blueprint;
    private Dispatcher $events;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = \Mockery::mock(Dispatcher::class);
        $this->blueprint = new Blueprint();
        $this->blueprint->setEventDispatcher($this->events);
    }

    #[Test]
    public function it_fires_generation_started_event(): void
    {
        $tree = new Tree(['models' => [], 'controllers' => []]);
        $only = ['models'];
        $skip = ['controllers'];

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $only, $skip) {
                return $event instanceof GenerationStarted &&
                       $event->tree === $tree &&
                       $event->only === $only &&
                       $event->skip === $skip;
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GenerationCompleted::class));

        $this->blueprint->generate($tree, $only, $skip);
    }

    #[Test]
    public function it_fires_generation_completed_event(): void
    {
        $tree = new Tree(['models' => [], 'controllers' => []]);
        $generator = $this->createMockGenerator(['models'], ['created' => ['Model.php']]);
        
        $this->blueprint->registerGenerator($generator);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GenerationStarted::class));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GeneratorExecuting::class));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GeneratorExecuted::class));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree) {
                return $event instanceof GenerationCompleted &&
                       $event->tree === $tree &&
                       isset($event->generated['created']) &&
                       in_array('Model.php', $event->generated['created']);
            }));

        $result = $this->blueprint->generate($tree);
        
        $this->assertArrayHasKey('created', $result);
        $this->assertContains('Model.php', $result['created']);
    }

    #[Test]
    public function it_fires_generator_executing_and_executed_events(): void
    {
        $tree = new Tree(['models' => [], 'controllers' => []]);
        $generator = $this->createMockGenerator(['models'], ['created' => ['Model.php']]);
        
        $this->blueprint->registerGenerator($generator);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GenerationStarted::class));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $generator) {
                return $event instanceof GeneratorExecuting &&
                       $event->tree === $tree &&
                       $event->generator === $generator;
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $generator) {
                return $event instanceof GeneratorExecuted &&
                       $event->tree === $tree &&
                       $event->generator === $generator &&
                       isset($event->output['created']) &&
                       in_array('Model.php', $event->output['created']);
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GenerationCompleted::class));

        $this->blueprint->generate($tree);
    }

    #[Test]
    public function it_does_not_fire_events_when_no_dispatcher_is_set(): void
    {
        $blueprint = new Blueprint();
        // No event dispatcher set
        
        $tree = new Tree(['models' => [], 'controllers' => []]);
        $generator = $this->createMockGenerator(['models'], ['created' => ['Model.php']]);
        
        $blueprint->registerGenerator($generator);

        // Should not throw any errors even without event dispatcher
        $result = $blueprint->generate($tree);
        
        $this->assertArrayHasKey('created', $result);
        $this->assertContains('Model.php', $result['created']);
    }

    #[Test]
    public function it_respects_generator_filtering_in_events(): void
    {
        $tree = new Tree(['models' => [], 'controllers' => []]);
        $modelGenerator = $this->createMockGenerator(['models'], ['created' => ['Model.php']]);
        $controllerGenerator = $this->createMockGenerator(['controllers'], ['created' => ['Controller.php']]);
        
        $this->blueprint->registerGenerator($modelGenerator);
        $this->blueprint->registerGenerator($controllerGenerator);

        // Only run models generator
        $only = ['models'];

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GenerationStarted::class));

        // Should only fire events for models generator
        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($modelGenerator) {
                return $event instanceof GeneratorExecuting &&
                       $event->generator === $modelGenerator;
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($modelGenerator) {
                return $event instanceof GeneratorExecuted &&
                       $event->generator === $modelGenerator;
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::type(GenerationCompleted::class));

        $result = $this->blueprint->generate($tree, $only);
        
        $this->assertArrayHasKey('created', $result);
        $this->assertContains('Model.php', $result['created']);
        $this->assertNotContains('Controller.php', $result['created']);
    }

    #[Test]
    public function it_can_get_and_set_event_dispatcher(): void
    {
        $blueprint = new Blueprint();
        
        $this->assertNull($blueprint->getEventDispatcher());
        
        $dispatcher = \Mockery::mock(Dispatcher::class);
        $blueprint->setEventDispatcher($dispatcher);
        
        $this->assertSame($dispatcher, $blueprint->getEventDispatcher());
    }

    #[Test]
    public function it_passes_correct_parameters_to_events(): void
    {
        $tree = new Tree(['models' => [], 'controllers' => []]);
        $only = ['models'];
        $skip = ['controllers'];
        $generator = $this->createMockGenerator(['models'], ['created' => ['Model.php']]);
        
        $this->blueprint->registerGenerator($generator);

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $only, $skip) {
                return $event instanceof GenerationStarted &&
                       $event->tree === $tree &&
                       $event->only === $only &&
                       $event->skip === $skip;
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $generator, $only, $skip) {
                return $event instanceof GeneratorExecuting &&
                       $event->tree === $tree &&
                       $event->generator === $generator &&
                       $event->only === $only &&
                       $event->skip === $skip;
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $generator, $only, $skip) {
                return $event instanceof GeneratorExecuted &&
                       $event->tree === $tree &&
                       $event->generator === $generator &&
                       $event->only === $only &&
                       $event->skip === $skip &&
                       isset($event->output['created']);
            }));

        $this->events->shouldReceive('dispatch')
            ->once()
            ->with(\Mockery::on(function ($event) use ($tree, $only, $skip) {
                return $event instanceof GenerationCompleted &&
                       $event->tree === $tree &&
                       $event->only === $only &&
                       $event->skip === $skip &&
                       isset($event->generated['created']);
            }));

        $this->blueprint->generate($tree, $only, $skip);
    }

    private function createMockGenerator(array $types, array $output): Generator
    {
        $generator = \Mockery::mock(Generator::class);
        $generator->shouldReceive('types')->andReturn($types);
        $generator->shouldReceive('output')->andReturn($output);
        
        return $generator;
    }
} 