<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

function branchPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test Branch '.fake()->unique()->numerify('###'),
        'code' => 'BR-'.fake()->unique()->numerify('####'),
        'phone' => '0701234567',
        'email' => fake()->unique()->safeEmail(),
        'country' => 'Afghanistan',
        'province' => 'Kabul',
        'district' => 'District 1',
        'address' => 'Kabul',
        'status' => 'active',
    ], $overrides);
}

test('admin has branches module for sidebar visibility', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    expect($admin->canAccessModule('branches'))->toBeTrue()
        ->and($admin->getAccessibleModules())->toContain('branches')
        ->and($admin->hasPermission('branches.view'))->toBeTrue()
        ->and($admin->hasPermission('branches.create'))->toBeTrue()
        ->and($admin->hasPermission('branches.update'))->toBeTrue()
        ->and($admin->canManageBranches())->toBeTrue();
});

test('super admin can list and create branches', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $existing = Branch::factory()->create();

    $this->actingAs($superAdmin)
        ->get('/branches')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('branches/Index')
            ->has('branches.data')
        );

    $payload = branchPayload(['code' => 'BR-SA-'.fake()->unique()->numerify('####')]);

    $this->actingAs($superAdmin)
        ->post('/branches', $payload)
        ->assertRedirect('/branches');

    $this->assertDatabaseHas('branches', [
        'code' => $payload['code'],
        'name' => $payload['name'],
        'trash' => false,
    ]);

    $this->actingAs($superAdmin)
        ->get('/branches/'.$existing->id)
        ->assertOk();
});

test('admin can access branch list create edit and update', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $branch = Branch::factory()->create(['code' => 'BR-OLD-'.fake()->unique()->numerify('###')]);

    $this->actingAs($admin)->get('/branches')->assertOk();
    $this->actingAs($admin)->get('/branches/create')->assertOk();
    $this->actingAs($admin)->get('/branches/'.$branch->id.'/edit')->assertOk();

    $payload = branchPayload([
        'name' => 'Updated Branch',
        'code' => $branch->code,
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->put('/branches/'.$branch->id, $payload)
        ->assertRedirect('/branches');

    expect($branch->fresh()->name)->toBe('Updated Branch');
});

test('admin cannot deactivate branch but super admin can', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $branch = Branch::factory()->create(['status' => 'active']);

    $this->actingAs($admin)
        ->post('/branches/'.$branch->id.'/deactivate')
        ->assertForbidden();

    expect($branch->fresh()->status)->toBe('active');

    $this->actingAs($superAdmin)
        ->post('/branches/'.$branch->id.'/deactivate')
        ->assertRedirect();

    expect($branch->fresh()->status)->toBe('inactive');
});

test('cashier cannot access branch administration routes', function () {
    $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
    $branch = Branch::factory()->create();

    $this->actingAs($cashier)->get('/branches')->assertForbidden();
    $this->actingAs($cashier)->get('/branches/create')->assertForbidden();
    $this->actingAs($cashier)->get('/branches/'.$branch->id)->assertForbidden();
    $this->actingAs($cashier)->get('/branches/'.$branch->id.'/edit')->assertForbidden();
});

test('branch manager cannot access branch administration routes', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create([
        'role' => User::ROLE_BRANCH_MANAGER,
        'branch_id' => $branch->id,
    ]);

    $this->actingAs($manager)->get('/branches')->assertForbidden();
    $this->actingAs($manager)->get('/branches/create')->assertForbidden();
});

test('unique branch code is enforced on create and update', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $existing = Branch::factory()->create(['code' => 'BR-UNIQUE-1']);
    $other = Branch::factory()->create(['code' => 'BR-UNIQUE-2']);

    $this->actingAs($admin)
        ->post('/branches', branchPayload(['code' => 'BR-UNIQUE-1']))
        ->assertSessionHasErrors('code');

    $this->actingAs($admin)
        ->put('/branches/'.$other->id, branchPayload([
            'code' => 'BR-UNIQUE-1',
            'name' => $other->name,
            'status' => $other->status,
        ]))
        ->assertSessionHasErrors('code');

    $this->assertDatabaseHas('branches', ['id' => $existing->id, 'code' => 'BR-UNIQUE-1']);
});

test('branch scoped inventory forces own branch for cashier', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $cashier = User::factory()->create([
        'role' => User::ROLE_CASHIER,
        'branch_id' => $branch->id,
    ]);

    $response = $this->actingAs($cashier)->get('/inventory?branch_id='.$otherBranch->id);

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('filters.branch_id', $branch->id)
    );
});
