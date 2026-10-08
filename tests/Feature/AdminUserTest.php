<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsTenant;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use BuildsTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildTenant();
    }

    public function test_admin_can_list_users(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
            ]);
    }

    public function test_admin_can_filter_users_by_search(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/users?search='.urlencode($this->student->email))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->student->id);
    }

    public function test_admin_can_filter_users_by_type(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/users?user_type_id='.$this->student->user_type_id)
            ->assertOk()
            ->assertJsonPath('data.0.user_type.id', $this->student->user_type_id);
    }

    public function test_admin_can_create_a_user_with_hashed_password(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users', [
                'name' => 'Novo Usuário',
                'username' => 'novo.usuario',
                'email' => 'novo@cursos.test',
                'password' => 'secret-password',
                'user_type_id' => $this->student->user_type_id,
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'novo@cursos.test')
            ->assertJsonPath('data.user_type.id', $this->student->user_type_id);

        $user = User::query()->where('email', 'novo@cursos.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame($this->company->id, $user->company_id);
    }

    public function test_create_user_validates_unique_email_and_username(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users', [
                'name' => 'Duplicado',
                'username' => $this->student->username,
                'email' => $this->student->email,
                'password' => 'secret-password',
                'user_type_id' => $this->student->user_type_id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'username']);
    }

    public function test_create_user_validates_required_fields_and_type(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users', ['user_type_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'username', 'email', 'password', 'user_type_id']);
    }

    public function test_admin_can_update_a_user_without_changing_password(): void
    {
        $original = $this->student->password;

        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/users/{$this->student->id}", ['name' => 'Renomeado'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renomeado');

        $this->student->refresh();
        $this->assertSame('Renomeado', $this->student->name);
        $this->assertSame($original, $this->student->password);
    }

    public function test_admin_can_change_a_user_password(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/users/{$this->student->id}", ['password' => 'nova-senha-123'])
            ->assertOk();

        $this->student->refresh();
        $this->assertTrue(Hash::check('nova-senha-123', $this->student->password));
    }

    public function test_unique_rules_ignore_the_same_user_on_update(): void
    {
        $this->withToken($this->apiLogin($this->admin))
            ->putJson("/api/v1/admin/users/{$this->student->id}", [
                'username' => $this->student->username,
                'email' => $this->student->email,
            ])
            ->assertOk();
    }

    public function test_admin_can_delete_a_user(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->deleteJson("/api/v1/admin/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_see_users_from_another_company(): void
    {
        $other = User::factory()->create();

        $this->withToken($this->apiLogin($this->admin))
            ->getJson('/api/v1/admin/users/'.$other->id)
            ->assertNotFound();
    }

    public function test_student_cannot_manage_users(): void
    {
        $this->withToken($this->apiLogin($this->student))
            ->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_admin_can_bulk_add_users_to_a_team(): void
    {
        $team = Team::factory()->create(['company_id' => $this->company->id]);
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'add-to-teams',
                'user_ids' => [$this->student->id, $other->id],
                'team_ids' => [$team->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonCount(0, 'data.failed');

        $this->assertDatabaseHas('team_users', ['team_id' => $team->id, 'user_id' => $this->student->id]);
        $this->assertDatabaseHas('team_users', ['team_id' => $team->id, 'user_id' => $other->id]);
    }

    public function test_admin_can_bulk_update_user_status(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'deactivate',
                'user_ids' => [$this->student->id, $other->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 2);

        $this->assertSame('inactive', $this->student->fresh()->status);
        $this->assertSame('inactive', $other->fresh()->status);
    }

    public function test_admin_can_bulk_delete_users_but_not_self(): void
    {
        $other = User::factory()->create([
            'company_id' => $this->company->id,
            'user_type_id' => $this->student->user_type_id,
        ]);

        $this->withToken($this->apiLogin($this->admin))
            ->postJson('/api/v1/admin/users/bulk', [
                'action' => 'delete',
                'user_ids' => [$other->id, $this->admin->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonCount(1, 'data.failed')
            ->assertJsonPath('data.failed.0.id', $this->admin->id);

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }
}
