<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreCommentRequest;
use App\Events\TaskCommentAdded;    
use App\Http\Requests\Tasks\UpdateCommentRequest;
use App\Http\Resources\V1\TaskCommentResource;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        $comments = $task->comments()->with('user')->paginate(min((int) $request->query('per_page', 15), 50));

        return $this->paginated($comments, TaskCommentResource::class);
    }

    public function store(StoreCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('comment', $task);

        $comment = $task->comments()->create([
            'user_id'  => $request->user()->id,
            'body'     => (string) $request->validated('body'),
            'mentions' => $this->extractMentions((string) $request->validated('body')),
        ]);

        if ($task->project_id !== null) {
            ProjectActivity::record(
                Project::query()->find($task->project_id),
                $request->user(),
                'task_commented',
                'commented on task "' . $task->title . '"',
                $task,
            );
        }

        event(new TaskCommentAdded($task->fresh(), $comment, $request->user()));

        return $this->success(new TaskCommentResource($comment->loadMissing('user')), 'Comment added.', 201);
    }

    public function update(UpdateCommentRequest $request, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('view', $task);
        $this->assertBelongsTo($task, $comment);

        if ($comment->user_id !== $request->user()->id) {
            return $this->error('You can only edit your own comments.', 403);
        }

        $comment->update([
            'body'      => (string) $request->validated('body'),
            'mentions'  => $this->extractMentions((string) $request->validated('body')),
            'edited_at' => now(),
        ]);

        return $this->success(new TaskCommentResource($comment->fresh()->loadMissing('user')), 'Comment updated.');
    }

    public function destroy(Request $request, Task $task, TaskComment $comment): JsonResponse
    {
        $this->authorize('view', $task);
        $this->assertBelongsTo($task, $comment);

        if ($comment->user_id !== $request->user()->id && ! $request->user()->can('tasks.delete')) {
            return $this->error('You can only delete your own comments.', 403);
        }

        $comment->delete();

        return $this->success(message: 'Comment deleted.');
    }

    /** @return array<int, string>|null */
    private function extractMentions(string $body): ?array
    {
        preg_match_all('/@([\w.\- ]{2,50})/u', $body, $matches);

        $mentions = array_values(array_unique(array_map('trim', $matches[1] ?? [])));

        return $mentions !== [] ? $mentions : null;
    }

    private function assertBelongsTo(Task $task, TaskComment $comment): void
    {
        abort_unless($comment->task_id === $task->id, 404);
    }
}
