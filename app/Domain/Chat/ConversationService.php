<?php

namespace App\Domain\Chat;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ConversationService
{
    /** One private thread per pair of people. */
    public function direct(User $a, User $b): Conversation
    {
        return DB::transaction(function () use ($a, $b): Conversation {
            $conversation = Conversation::firstOrCreate(
                ['direct_key' => Conversation::directKey($a->id, $b->id)],
                ['type' => 'direct', 'created_by' => $a->id],
            );

            foreach ([$a, $b] as $user) {
                ConversationParticipant::firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id'         => $user->id,
                ]);
            }

            return $conversation;
        });
    }

    /**
     * @param Collection<int, int> $memberIds
     */
    public function group(User $creator, string $name, Collection $memberIds): Conversation
    {
        return DB::transaction(function () use ($creator, $name, $memberIds): Conversation {
            $conversation = Conversation::create([
                'type'       => 'group',
                'name'       => $name,
                'created_by' => $creator->id,
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id'         => $creator->id,
                'role'            => 'admin',
            ]);

            foreach ($memberIds->reject(fn ($id) => $id === $creator->id)->unique() as $id) {
                ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $id]);
            }

            return $conversation;
        });
    }

    /** The team's channel; membership mirrors the team roster. */
    public function forTeam(Team $team): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['team_id' => $team->id],
            ['type' => 'team', 'name' => $team->name],
        );

        $userIds = Employee::query()->where('team_id', $team->id)->whereNotNull('user_id')->pluck('user_id');

        if ($team->leader_id !== null) {
            $leader = Employee::query()->whereKey($team->leader_id)->value('user_id');
            $userIds = $leader !== null ? $userIds->push($leader) : $userIds;
        }

        $this->syncParticipants($conversation, $userIds);

        return $conversation;
    }

    /** The project's channel; membership mirrors owner, PM and members. */
    public function forProject(Project $project): Conversation
    {
        $conversation = Conversation::firstOrCreate(
            ['project_id' => $project->id],
            ['type' => 'project', 'name' => $project->name],
        );

        $employeeIds = collect([$project->owner_id, $project->project_manager_id])
            ->merge(DB::table('project_members')->where('project_id', $project->id)->pluck('employee_id'))
            ->filter()
            ->unique();

        $userIds = Employee::query()->whereIn('id', $employeeIds)->whereNotNull('user_id')->pluck('user_id');

        $this->syncParticipants($conversation, $userIds);

        return $conversation;
    }

    /**
     * Make sure the user's team and project channels exist and are current.
     * Throttled per user — a new team member sees the channel within a minute.
     */
    public function bootstrapFor(User $user): void
    {
        if (! Cache::add('chat.bootstrap.' . $user->id, true, 60)) {
            return;
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();

        if ($employee === null) {
            return;
        }

        if ($employee->team_id !== null && ($team = Team::query()->find($employee->team_id)) !== null) {
            $this->forTeam($team);
        }

        $projectIds = DB::table('project_members')->where('employee_id', $employee->id)->pluck('project_id')
            ->merge(Project::query()->where('owner_id', $employee->id)->orWhere('project_manager_id', $employee->id)->pluck('id'))
            ->unique();

        Project::query()->whereIn('id', $projectIds)->active()->get()
            ->each(fn (Project $project) => $this->forProject($project));
    }

    /**
     * Add missing participants and drop people who left the team/project.
     *
     * @param Collection<int, int> $userIds
     */
    private function syncParticipants(Conversation $conversation, Collection $userIds): void
    {
        $userIds = $userIds->unique()->values();

        if ($userIds->isEmpty()) {
            return;
        }

        $existing = $conversation->participants()->pluck('user_id');

        foreach ($userIds->diff($existing) as $id) {
            ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $id]);
        }

        $conversation->participants()->whereNotIn('user_id', $userIds)->delete();
    }
}
