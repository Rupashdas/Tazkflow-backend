<?php

use App\Actions\CreateWorkspace;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

function roleIn(Workspace $workspace, string $name): Role {
    return Role::where('workspace_id', $workspace->id)->where('name', $name)->firstOrFail();
}

function joinAs(Workspace $workspace, string $role, array $attributes = []): User {
    $user = User::factory()->create($attributes);
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id'      => $user->id,
        'role_id'      => roleIn($workspace, $role)->id,
    ]);

    return $user;
}

function membershipIn(Workspace $workspace, User $user): WorkspaceMember {
    return WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $user->id)->firstOrFail();
}

beforeEach(function () {
    $this->workspace = app(CreateWorkspace::class)(User::factory()->create(['name' => 'Owner']), 'Acme', 'acme');
    $this->admin = joinAs($this->workspace, 'admin', ['name' => 'Admin']);
});

it('pages through people with the same name without repeating or skipping anyone', function () {
    $sames = collect(range(1, 4))->map(fn() => joinAs($this->workspace, 'member', ['name' => 'Sam']));

    $seen = collect(range(1, 6))->flatMap(fn($page) => $this->actingAs($this->admin)
        ->withHeader('X-Workspace', 'acme')
        ->getJson("/api/members?per_page=1&page={$page}")
        ->assertOk()
        ->json('data.*.id'));

    // Admin, Owner, then the four Sams in the order they joined.
    expect($seen->all())->toBe([$this->admin->id, $this->workspace->owner_id, ...$sames->pluck('id')->all()]);
});

it('sets access to the value asked for, so sending it twice changes nothing', function () {
    $member = joinAs($this->workspace, 'member');

    foreach ([1, 2] as $_) {
        $this->actingAs($this->admin)
            ->withHeader('X-Workspace', 'acme')
            ->patchJson("/api/members/{$member->id}/active", ['is_active' => false])
            ->assertSuccessful()
            ->assertJsonPath('data.is_active', false);
    }

    expect(membershipIn($this->workspace, $member)->is_active)->toBeFalse();
});

it('needs to be told whether access is on or off', function () {
    $member = joinAs($this->workspace, 'member');

    $this->actingAs($this->admin)
        ->withHeader('X-Workspace', 'acme')
        ->patchJson("/api/members/{$member->id}/active")
        ->assertJsonValidationErrors(['is_active']);
});

it('does not let anyone change their own role', function () {
    $this->actingAs($this->admin)
        ->withHeader('X-Workspace', 'acme')
        ->patchJson("/api/members/{$this->admin->id}/role", ['role_id' => roleIn($this->workspace, 'member')->id])
        ->assertStatus(422);

    expect(membershipIn($this->workspace, $this->admin)->role_id)->toBe(roleIn($this->workspace, 'admin')->id);
});
