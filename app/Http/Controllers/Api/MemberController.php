<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MemberResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Admins manage memberships — role, access, removal — never the accounts
 * behind them. An account is shared across workspaces; editing someone's
 * email here would hand their other workspaces to this one's admin.
 */
class MemberController extends Controller {
    public function index(Request $request, CurrentWorkspace $current): AnonymousResourceCollection {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            // A role of this workspace, or "none" for people without one.
            'role'     => ['sometimes', $request->input('role') === 'none'
                ? 'in:none'
                : Rule::exists('roles', 'id')->where('workspace_id', $current->id())],
            'status'   => ['sometimes', Rule::in(['active', 'deactivated'])],
        ]);

        $search = $request->string('search')->trim()->value();
        $role = $validated['role'] ?? null;
        $status = $validated['status'] ?? null;

        $members = WorkspaceMember::query()
            ->with(['user', 'role'])
            ->when($role === 'none', fn($query) => $query->whereNull('role_id'))
            ->when($role !== null && $role !== 'none', fn($query) => $query->where('role_id', $role))
            ->when($status !== null, fn($query) => $query->where('is_active', $status === 'active'))
            ->when($search !== '', fn($query) => $query->whereHas('user', function ($user) use ($search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $user->where('name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->orderBy(User::select('name')->whereColumn('users.id', 'workspace_members.user_id'))
            // Names repeat. Without a unique tie-break, two pages can hold the
            // same person and miss another.
            ->orderBy('workspace_members.id')
            ->paginate($request->integer('per_page', 50));

        return MemberResource::collection($members);
    }

    public function updateRole(Request $request, User $user, CurrentWorkspace $current): MemberResource {
        $membership = $this->membershipOf($user);
        $this->refuseForOwner($user, $current, 'The owner\'s role cannot be changed. Hand over ownership first.');
        abort_if($user->is($request->user()), 422, 'You cannot change your own role.');

        $validated = $request->validate([
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('workspace_id', $current->id())],
        ]);

        // members.manage must not be a back door to Admin: nobody hands out,
        // or takes away, more access than they hold themselves.
        abort_if($current->ranksAbove($membership->role), 403, 'You cannot change the role of someone with more access than you.');
        abort_if(! $current->canGrant(Role::find($validated['role_id'])), 403, 'You cannot give a role with more access than your own.');

        $membership->update(['role_id' => $validated['role_id']]);

        return new MemberResource($membership->load(['user', 'role']));
    }

    public function updateActive(Request $request, User $user, CurrentWorkspace $current): MemberResource {
        $membership = $this->membershipOf($user);
        $this->refuseForOwner($user, $current, 'The owner cannot be deactivated.');
        abort_if($user->is($request->user()), 422, 'You cannot deactivate yourself.');
        abort_if($current->ranksAbove($membership->role), 403, 'You cannot switch off someone with more access than you.');

        // The value asked for, not a flip: two admins pressing Deactivate at
        // once, or one double click, must not end up switching access back on.
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        $membership->update(['is_active' => $validated['is_active']]);

        return new MemberResource($membership->load(['user', 'role']));
    }

    public function destroy(Request $request, User $user, CurrentWorkspace $current): JsonResponse {
        $membership = $this->membershipOf($user);
        $this->refuseForOwner($user, $current, 'The owner cannot be removed. Hand over ownership first.');
        abort_if($user->is($request->user()), 422, 'You cannot remove yourself.');
        abort_if($current->ranksAbove($membership->role), 403, 'You cannot remove someone with more access than you.');

        // When projects arrive, this is where their memberships and task
        // assignments in this workspace get cleaned up too.
        $membership->delete();

        return response()->json(['message' => 'Removed from the workspace.']);
    }

    // BelongsToWorkspace keeps this inside the current workspace.
    private function membershipOf(User $user): WorkspaceMember {
        return WorkspaceMember::where('user_id', $user->id)
            ->firstOr(fn() => abort(404, 'That person is not a member of this workspace.'));
    }

    private function refuseForOwner(User $user, CurrentWorkspace $current, string $message): void {
        abort_if($current->get()->isOwnedBy($user), 422, $message);
    }
}
