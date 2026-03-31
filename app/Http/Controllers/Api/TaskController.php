<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskStatusRequest;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

/**
 * Task CRUD, status workflow, soft-delete (archive), restore, permanent delete, and daily report.
 *
 * Delete rules (challenge spec): archive and permanent delete only when `status` is `done`; otherwise **403** for all roles.
 * Listing uses {@see Task::SoftDeletes}: default query excludes archived; `onlyTrashed()` for archived tab.
 */
class TaskController extends Controller
{
    #[
        OA\Get(
            path: '/api/tasks',
            summary: 'List tasks',
            description: 'Sorted by priority (high → low), then due_date ascending. Users see only their tasks; admins see all.',
            tags: ['Tasks'],
            security: [['jwt' => []]],
            parameters: [
                new OA\Parameter(
                    name: 'status',
                    in: 'query',
                    required: false,
                    schema: new OA\Schema(type: 'string', enum: ['pending', 'in_progress', 'done']),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'OK',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(property: 'tasks', type: 'array', items: new OA\Items(type: 'object')),
                            new OA\Property(property: 'message', type: 'string', nullable: true),
                        ],
                    ),
                ),
            ],
        )
    ]
    /**
     * List tasks for the current tab (active vs archived), optional status filter, and aggregate counts for both tabs.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'done'])],
        ]);

        // Query strings send "true"/"false" as strings; Laravel's "boolean" rule rejects those.
        // Request::boolean() parses them correctly (same as filter_var(..., FILTER_VALIDATE_BOOLEAN)).
        $archived = $request->boolean('archived');

        $base = $this->scopedTaskQuery($request);
        // Tab badges always show totals for active and archived buckets (independent of current filter).
        $countsActive = $this->countStatuses(clone $base);
        $countsArchived = $this->countStatuses((clone $base)->onlyTrashed());

        $query = $this->scopedTaskQuery($request)
            ->when($archived, fn (Builder $q) => $q->onlyTrashed())
            ->when(isset($data['status']), fn ($q) => $q->where('status', $data['status']))
            ->orderByRaw("CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 END")
            ->orderBy('due_date');

        $tasks = $query->get();

        $payload = [
            'tasks' => $tasks,
            'counts' => [
                'active' => $countsActive,
                'archived' => $countsArchived,
            ],
        ];

        if ($tasks->isEmpty()) {
            $payload['message'] = 'No tasks found.';
        }

        return response()->json($payload);
    }

    /**
     * Tasks visible to the current user: all rows for admin, or `user_id = auth` for normal users.
     *
     * @return Builder<Task>
     */
    protected function scopedTaskQuery(Request $request): Builder
    {
        return Task::query()
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('user_id', $request->user()->id));
    }

    /**
     * Group counts by status for badge UI (pending / in_progress / done / total).
     *
     * @param  Builder<Task>  $query
     * @return array{pending: int, in_progress: int, done: int, total: int}
     */
    protected function countStatuses(Builder $query): array
    {
        $rows = (clone $query)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $pending = (int) ($rows['pending'] ?? 0);
        $in_progress = (int) ($rows['in_progress'] ?? 0);
        $done = (int) ($rows['done'] ?? 0);

        return [
            'pending' => $pending,
            'in_progress' => $in_progress,
            'done' => $done,
            'total' => $pending + $in_progress + $done,
        ];
    }

    #[
        OA\Post(
            path: '/api/tasks',
            summary: 'Create task',
            tags: ['Tasks'],
            security: [['jwt' => []]],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['title', 'due_date', 'priority'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'due_date', type: 'string', format: 'date'),
                        new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high']),
                    ],
                ),
            ),
            responses: [
                new OA\Response(response: 201, description: 'Created'),
                new OA\Response(response: 422, description: 'Validation error'),
            ],
        )
    ]
    /** Create a new task in `pending` status for the authenticated user. */
    public function store(StoreTaskRequest $request)
    {
        $task = Task::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json($task, 201);
    }

    #[
        OA\Patch(
            path: '/api/tasks/{id}/status',
            summary: 'Update task status',
            description: 'Status may only advance: pending → in_progress → done.',
            tags: ['Tasks'],
            security: [['jwt' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ],
            requestBody: new OA\RequestBody(
                required: true,
                content: new OA\JsonContent(
                    required: ['status'],
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'done']),
                    ],
                ),
            ),
            responses: [
                new OA\Response(response: 200, description: 'OK'),
                new OA\Response(response: 403, description: 'Forbidden'),
                new OA\Response(response: 422, description: 'Invalid transition or validation'),
            ],
        )
    ]
    /**
     * Advance status one step: pending → in_progress → done only (no skip, no revert).
     */
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        $next = match ($task->status) {
            'pending' => 'in_progress',
            'in_progress' => 'done',
            default => null,
        };

        $newStatus = $request->validated()['status'];

        if ($next === null || $newStatus !== $next) {
            return response()->json([
                'message' => 'Status can only progress pending → in_progress → done without skipping or reverting.',
            ], 422);
        }

        $task->update(['status' => $newStatus]);

        return response()->json($task->fresh());
    }

    #[
        OA\Delete(
            path: '/api/tasks/{id}',
            summary: 'Delete task',
            description: 'Soft-delete (archive) only when task status is done; otherwise 403.',
            tags: ['Tasks'],
            security: [['jwt' => []]],
            parameters: [
                new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            ],
            responses: [
                new OA\Response(response: 204, description: 'No content'),
                new OA\Response(response: 403, description: 'Forbidden'),
            ],
        )
    ]
    /**
     * Soft-delete (archive). Allowed only when status is `done` (403 otherwise), per take-home spec.
     */
    public function destroy(Request $request, Task $task)
    {
        $this->authorize('delete', $task);

        if ($task->status !== 'done') {
            return response()->json([
                'message' => 'Only tasks with status done can be deleted.',
            ], 403);
        }

        $task->delete();

        return response()->noContent();
    }

    /**
     * Un-archive a soft-deleted task (must load via {@see Task::onlyTrashed()}).
     */
    public function restore(Request $request, string $id)
    {
        $task = Task::onlyTrashed()->whereKey($id)->firstOrFail();
        $this->authorize('restore', $task);

        $task->restore();

        return response()->json($task->fresh());
    }

    /**
     * Permanently remove an archived task. Same `done`-only rule as {@see destroy()}.
     */
    public function forceDestroy(Request $request, string $id)
    {
        $task = Task::onlyTrashed()->whereKey($id)->firstOrFail();
        $this->authorize('forceDelete', $task);

        if ($task->status !== 'done') {
            return response()->json([
                'message' => 'Only tasks with status done can be permanently deleted.',
            ], 403);
        }

        $task->forceDelete();

        return response()->noContent();
    }

    #[
        OA\Get(
            path: '/api/tasks/report',
            summary: 'Daily task report (bonus)',
            description: 'Counts per priority and status for tasks with due_date on the given day. Scoped to current user unless admin.',
            tags: ['Tasks'],
            security: [['jwt' => []]],
            parameters: [
                new OA\Parameter(name: 'date', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-03-28')),
            ],
            responses: [
                new OA\Response(response: 200, description: 'OK'),
                new OA\Response(response: 422, description: 'Validation error'),
            ],
        )
    ]
    /**
     * Bonus: counts per priority × status for tasks whose due_date equals `date` (non-archived only).
     */
    public function report(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $data['date'];
        $user = $request->user();

        // Default Task::query() excludes soft-deleted rows.
        $base = Task::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->whereDate('due_date', $date);

        $rows = (clone $base)
            ->selectRaw('priority, status, count(*) as c')
            ->groupBy('priority', 'status')
            ->get();

        $summary = [
            'high' => ['pending' => 0, 'in_progress' => 0, 'done' => 0],
            'medium' => ['pending' => 0, 'in_progress' => 0, 'done' => 0],
            'low' => ['pending' => 0, 'in_progress' => 0, 'done' => 0],
        ];

        foreach ($rows as $row) {
            $summary[$row->priority][$row->status] = (int) $row->c;
        }

        return response()->json([
            'date' => $date,
            'summary' => $summary,
        ]);
    }
}
