<?php

namespace Tests\Feature;

use Blueprint\Blueprint;
use Blueprint\Tree;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class FrontendGenerationTest extends TestCase
{
    protected $blueprint;
    protected $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Use real filesystem for frontend generation tests
        $this->filesystem = new \Illuminate\Filesystem\Filesystem();
        
        // Bind the filesystem to the container so Blueprint uses the same instance
        $this->app->singleton('files', function () {
            return $this->filesystem;
        });
        
        $this->blueprint = app(Blueprint::class);
    }

    public function test_generates_react_component()
    {
        $yaml = <<<'YAML'
frontend:
  UserProfile:
    framework: react
    type: component
    props:
      user: User
      isEditing: boolean
    state:
      formData: {}
      isLoading: false
    methods:
      handleSubmit: |
        setLoading(true);
        await saveUser(formData);
        setLoading(false);
YAML;

        $tokens = $this->blueprint->parse($yaml);
        $tree = $this->blueprint->analyze($tokens);
        $generated = $this->blueprint->generate($tree, ['frontend']);

        $this->assertArrayHasKey('created', $generated);
        $this->assertContains('resources/js/components/user-profile.jsx', $generated['created']);

        $componentContent = $this->filesystem->get('resources/js/components/user-profile.jsx');
        
        $this->assertStringContainsString('import React, { useState } from \'react\';', $componentContent);
        $this->assertStringContainsString('interface UserProfileProps', $componentContent);
        $this->assertStringContainsString('user: User', $componentContent);
        $this->assertStringContainsString('isEditing: boolean', $componentContent);
        $this->assertStringContainsString('const [formData, setFormData] = useState({});', $componentContent);
        $this->assertStringContainsString('const [isLoading, setIsLoading] = useState(false);', $componentContent);
        $this->assertStringContainsString('const handleSubmit = () => {', $componentContent);
    }

    public function test_generates_vue_component()
    {
        $yaml = <<<'YAML'
frontend:
  ProductCard:
    framework: vue
    type: component
    props:
      product: Product
      showPrice: boolean
    state:
      isHovered: false
    methods:
      toggleHover: |
        isHovered = !isHovered;
YAML;

        $tokens = $this->blueprint->parse($yaml);
        $tree = $this->blueprint->analyze($tokens);
        $generated = $this->blueprint->generate($tree, ['frontend']);

        $this->assertArrayHasKey('created', $generated);
        $this->assertContains('resources/js/components/product-card.vue', $generated['created']);

        $componentContent = $this->filesystem->get('resources/js/components/product-card.vue');
        
        $this->assertStringContainsString('<template>', $componentContent);
        $this->assertStringContainsString('<script setup lang="ts">', $componentContent);
        $this->assertStringContainsString('interface ProductCardProps', $componentContent);
        $this->assertStringContainsString('product: Product', $componentContent);
        $this->assertStringContainsString('showPrice: boolean', $componentContent);
        $this->assertStringContainsString('const isHovered = ref(false);', $componentContent);
        $this->assertStringContainsString('const toggleHover = () => {', $componentContent);
    }

    public function test_generates_svelte_component()
    {
        $yaml = <<<'YAML'
frontend:
  TodoList:
    framework: svelte
    type: component
    props:
      todos: Todo[]
    state:
      newTodo: ''
    methods:
      addTodo: |
        if (newTodo.trim()) {
          todos = [...todos, { id: Date.now(), text: newTodo, completed: false }];
          newTodo = '';
        }
YAML;

        $tokens = $this->blueprint->parse($yaml);
        $tree = $this->blueprint->analyze($tokens);
        $generated = $this->blueprint->generate($tree, ['frontend']);

        $this->assertArrayHasKey('created', $generated);
        $this->assertContains('resources/js/components/todo-list.svelte', $generated['created']);

        $componentContent = $this->filesystem->get('resources/js/components/todo-list.svelte');
        
        $this->assertStringContainsString('<script lang="ts">', $componentContent);
        $this->assertStringContainsString('export let todos: Todo[];', $componentContent);
        $this->assertStringContainsString('let newTodo = \'\';', $componentContent);
        $this->assertStringContainsString('function addTodo() {', $componentContent);
    }

    public function test_generates_component_with_styles()
    {
        $yaml = <<<'YAML'
frontend:
  StyledComponent:
    framework: react
    type: component
    styles:
      '.styled-component': 
        padding: '1rem'
        border: '1px solid #ccc'
      '.styled-component h2':
        color: '#333'
        marginBottom: '1rem'
YAML;

        $tokens = $this->blueprint->parse($yaml);
        $tree = $this->blueprint->analyze($tokens);
        $generated = $this->blueprint->generate($tree, ['frontend']);

        $this->assertArrayHasKey('created', $generated);
        $this->assertContains('resources/js/components/styled-component.css', $generated['created']);

        $cssContent = $this->filesystem->get('resources/js/components/styled-component.css');
        
        $this->assertStringContainsString('.styled-component {', $cssContent);
    }

    public function test_generates_component_with_dependencies()
    {
        $yaml = <<<'YAML'
frontend:
  ComponentWithDeps:
    framework: react
    type: component
    dependencies:
      useState: react
      useEffect: react
      axios: axios
YAML;

        $tokens = $this->blueprint->parse($yaml);
        $tree = $this->blueprint->analyze($tokens);
        $generated = $this->blueprint->generate($tree, ['frontend']);

        $this->assertArrayHasKey('created', $generated);
        $this->assertContains('resources/js/components/component-with-deps.jsx', $generated['created']);

        $componentContent = $this->filesystem->get('resources/js/components/component-with-deps.jsx');
        
        $this->assertStringContainsString('import { useState } from \'react\';', $componentContent);
        $this->assertStringContainsString('import { useEffect } from \'react\';', $componentContent);
        $this->assertStringContainsString('import axios from \'axios\';', $componentContent);
    }

    public function test_generates_page_component()
    {
        $yaml = <<<'YAML'
frontend:
  DashboardPage:
    framework: vue
    type: page
    layout: AdminLayout
    route: /dashboard
    props:
      user: User
      stats: DashboardStats
    api:
      fetchDashboard:
        url: '/api/dashboard'
        method: GET
YAML;

        $tokens = $this->blueprint->parse($yaml);
        $tree = $this->blueprint->analyze($tokens);
        $generated = $this->blueprint->generate($tree, ['frontend']);

        $this->assertArrayHasKey('created', $generated);
        $this->assertContains('resources/js/components/dashboard-page.vue', $generated['created']);

        $componentContent = $this->filesystem->get('resources/js/components/dashboard-page.vue');
        
        $this->assertStringContainsString('interface DashboardPageProps', $componentContent);
        $this->assertStringContainsString('user: User', $componentContent);
        $this->assertStringContainsString('stats: DashboardStats', $componentContent);
        $this->assertStringContainsString('const fetchDashboard = async () => {', $componentContent);
    }

    protected function tearDown(): void
    {
        // Clean up generated files
        $this->filesystem->deleteDirectory('resources/js/components');
        
        parent::tearDown();
    }
} 