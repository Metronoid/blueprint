<?php

namespace BlueprintExtensions\Auditing\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use OwenIt\Auditing\Models\Audit;
use App\Models\User;
use BlueprintExtensions\Auditing\Traits\UnrewindableAuditTrait;

class UnrewindableAuditTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_mark_an_audit_as_unrewindable()
    {
        // Create a test model with the trait
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create an audit for the user
        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'is_unrewindable' => false,
        ]);

        // Mark the audit as unrewindable
        $result = $testModel->markAuditAsUnrewindable($audit->id, 'Test reason');

        $this->assertTrue($result);
        
        // Verify the audit was marked as unrewindable
        $audit->refresh();
        $this->assertTrue($audit->is_unrewindable);
        $this->assertStringContainsString('unrewindable', $audit->tags);
        $this->assertEquals('Test reason', $audit->metadata['unrewindable_reason']);
    }

    /** @test */
    public function it_can_mark_multiple_audits_as_unrewindable()
    {
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create multiple audits
        $audit1 = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name 1'],
            'new_values' => ['name' => 'New Name 1'],
            'is_unrewindable' => false,
        ]);

        $audit2 = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name 2'],
            'new_values' => ['name' => 'New Name 2'],
            'is_unrewindable' => false,
        ]);

        // Mark multiple audits as unrewindable
        $results = $testModel->markAuditsAsUnrewindable([$audit1->id, $audit2->id], 'Bulk update');

        $this->assertCount(2, $results['success']);
        $this->assertCount(0, $results['failed']);
        
        // Verify both audits were marked
        $audit1->refresh();
        $audit2->refresh();
        $this->assertTrue($audit1->is_unrewindable);
        $this->assertTrue($audit2->is_unrewindable);
    }

    /** @test */
    public function it_can_check_if_an_audit_can_be_rewound()
    {
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create a rewindable audit
        $rewindableAudit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'is_unrewindable' => false,
        ]);

        // Create an unrewindable audit
        $unrewindableAudit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'is_unrewindable' => true,
        ]);

        // Check if audits can be rewound
        $this->assertTrue($testModel->canRewindAudit($rewindableAudit->id));
        $this->assertFalse($testModel->canRewindAudit($unrewindableAudit->id));
    }

    /** @test */
    public function it_can_get_rewindable_statistics()
    {
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create rewindable audits
        Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name 1'],
            'new_values' => ['name' => 'New Name 1'],
            'is_unrewindable' => false,
        ]);

        Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name 2'],
            'new_values' => ['name' => 'New Name 2'],
            'is_unrewindable' => false,
        ]);

        // Create unrewindable audit
        Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name 3'],
            'new_values' => ['name' => 'New Name 3'],
            'is_unrewindable' => true,
        ]);

        // Get statistics
        $stats = $testModel->getRewindableStatistics();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['rewindable']);
        $this->assertEquals(1, $stats['unrewindable']);
        $this->assertEquals(66.67, $stats['rewindable_percentage']);
        $this->assertEquals(33.33, $stats['unrewindable_percentage']);
    }

    /** @test */
    public function it_can_get_unrewindable_reason()
    {
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create an audit
        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'is_unrewindable' => false,
        ]);

        // Mark as unrewindable with a reason
        $testModel->markAuditAsUnrewindable($audit->id, 'Compliance requirement');

        // Get the reason
        $reason = $testModel->getUnrewindableReason($audit->id);

        $this->assertEquals('Compliance requirement', $reason);
    }

    /** @test */
    public function it_returns_null_for_rewindable_audit_reason()
    {
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create a rewindable audit
        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => ['name' => 'Old Name'],
            'new_values' => ['name' => 'New Name'],
            'is_unrewindable' => false,
        ]);

        // Get the reason (should be null)
        $reason = $testModel->getUnrewindableReason($audit->id);

        $this->assertNull($reason);
    }

    /** @test */
    public function it_prevents_marking_audit_for_different_model()
    {
        $testModel = new class extends \Illuminate\Database\Eloquent\Model {
            use UnrewindableAuditTrait;
            
            protected $table = 'users';
            protected $fillable = ['name', 'email'];
        };
        
        $testModel->id = $this->user->id;
        
        // Create an audit for a different model
        $audit = Audit::create([
            'user_type' => User::class,
            'user_id' => $this->user->id,
            'event' => 'updated',
            'auditable_type' => 'App\Models\Post', // Different model
            'auditable_id' => 999, // Different ID
            'old_values' => ['title' => 'Old Title'],
            'new_values' => ['title' => 'New Title'],
            'is_unrewindable' => false,
        ]);

        // Try to mark as unrewindable (should fail)
        $result = $testModel->markAuditAsUnrewindable($audit->id, 'Test reason');

        $this->assertFalse($result);
        
        // Verify the audit was not marked
        $audit->refresh();
        $this->assertFalse($audit->is_unrewindable);
    }
} 