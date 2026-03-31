<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Authorization for task actions: admins can access any task; users only their own.
 *
 * “Done-only” delete/archive rules are enforced in {@see \App\Http\Controllers\Api\TaskController}
 * in addition to these visibility checks.
 */
class TaskPolicy
{
    /** Whether the user may read this task (list/detail/report scope). */
    public function view(User $user, Task $task): bool
    {
        return $user->isAdmin() || $user->id === $task->user_id;
    }

    /** Status transitions and updates (non-trashed task). */
    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    /** Soft-delete (archive) — same visibility as view. */
    public function delete(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    /** Clear soft-delete and return task to active list. */
    public function restore(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    /** Hard-delete from DB (archived row only in controller). */
    public function forceDelete(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }
}
