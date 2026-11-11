<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

function userPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'username' => 'test.user.'.fake()->unique()->numerify('####'),
        'email' => fake()->unique()->safeEmail(),
        'phone' => '0701234567',
        'password' => 'StrongPass123!',
        'password_confirmation' => 'StrongPass123!',
        'role' => User::ROLE_CASHIER,
        'status' => 'active',
    ], $overrides);
}

test('branch managers only see and manage users in their own branch', function () {
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $manager = User::factory()->create(['role' => User::ROLE_BRANCH_MANAGER, 'branch_id' => $branch->id]);
    $sameBranchUser = User::factory()->create(['role' => User::ROLE_CASHIER, 'branch_id' => $branch->id]);
    $otherBranchUser = User::factory()->create(['role' => User::ROLE_CASHIER, 'branch_id' => $otherBranch->id]);

    $this->actingAs($manager)->get('/users')->assertOk()->assertSee($sameBranchUser->name)->assertDontSee($otherBranchUser->name);
    $this->actingAs($manager)->get('/users/'.$otherBranchUser->id.'/edit')->assertForbidden();
});

test('branch manager creation is forced to own branch and rejects forbidden roles', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $manager = User::factory()->create(['role' => User::ROLE_BRANCH_MANAGER, 'branch_id' => $branch->id]);

    $wrongBranchPayload = userPayload([
        'branch_id' => $otherBranch->id,
    ]);
    $this->actingAs($manager)->post('/users', $wrongBranchPayload)->assertRedirect('/users');
    $this->assertDatabaseHas('users', [
        'email' => $wrongBranchPayload['email'],
        'branch_id' => $branch->id,
    ]);

    $forbiddenRolePayload = userPayload([
        'branch_id' => $branch->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $this->actingAs($manager)->post('/users', $forbiddenRolePayload)->assertSessionHasErrors('role');

    $this->assertDatabaseMissing('users', ['email' => $forbiddenRolePayload['email']]);
});

test('blocking a user requires the target to be manageable', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $branch = Branch::factory()->create();
    $otherBranch = Branch::factory()->create();
    $manager = User::factory()->create(['role' => User::ROLE_BRANCH_MANAGER, 'branch_id' => $branch->id]);
    $ownUser = User::factory()->create(['role' => User::ROLE_CASHIER, 'branch_id' => $branch->id]);
    $foreignUser = User::factory()->create(['role' => User::ROLE_CASHIER, 'branch_id' => $otherBranch->id]);

    $this->actingAs($manager)->post('/users/'.$foreignUser->id.'/block')->assertSessionHas('error');
    expect($foreignUser->fresh()->status)->toBe('active');

    $this->actingAs($manager)->post('/users/'.$ownUser->id.'/block')->assertSessionHas('success');
    expect($ownUser->fresh()->status)->toBe('blocked');
});

test('user list rejects invalid filters instead of silently applying them', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get('/users?status=unknown')->assertSessionHasErrors('status');
    $this->actingAs($admin)->get('/users?role=unknown')->assertSessionHasErrors('role');
});

