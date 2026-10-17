<?php

use App\Models\DesignFile;
use App\Models\WorkUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedPermissions();
    Event::fake([\App\Events\Realtime\MessageSent::class, \App\Events\Realtime\ProjectWorkLogged::class]);
});

// ── Work tracking ────────────────────────────────────────────

test('a project member can log work and it shows in the feed and stats', function () {
    $org     = makeOrg();
    $project = makeProject(['owner_id' => $org['manager']->id, 'project_manager_id' => $org['manager']->id]);
    $project->members()->attach($org['report']->id, ['role' => 'contributor', 'added_by' => $org['manager']->user_id]);

    Sanctum::actingAs(userFor($org['report']));

    $this->postJson("/api/v1/projects/{$project->uuid}/work-updates", [
        'message'       => 'Finished the auth endpoints',
        'minutes_spent' => 90,
    ])->assertCreated();

    $this->getJson("/api/v1/projects/{$project->uuid}/work-updates")
        ->assertOk()
        ->assertJsonPath('data.0.message', 'Finished the auth endpoints');

    $stats = $this->getJson("/api/v1/projects/{$project->uuid}/contributions")->assertOk();

    expect($stats->json('data.totals.updates'))->toBe(1);
    expect($stats->json('data.totals.minutes'))->toBe(90);
});

test('someone outside the project cannot log work on it', function () {
    $org     = makeOrg();
    $project = makeProject(['owner_id' => $org['manager']->id, 'project_manager_id' => $org['manager']->id]);

    Sanctum::actingAs(userFor($org['designer']));

    $this->postJson("/api/v1/projects/{$project->uuid}/work-updates", ['message' => 'Nope'])->assertForbidden();
});

test('moving a task to completed writes a system entry to the work feed', function () {
    $org     = makeOrg();
    $project = makeProject(['owner_id' => $org['manager']->id, 'project_manager_id' => $org['manager']->id]);
    $task    = makeTask(['project_id' => $project->id]);

    $update = app(\App\Domain\Tasks\UpdateTask::class);
    $update($task, ['status' => 'completed'], userFor($org['manager']));

    expect(WorkUpdate::where('project_id', $project->id)->where('source', 'system')->count())->toBe(1);
});

// ── Design tracking ──────────────────────────────────────────

test('a design file can be tracked and versions logged by hand (Adobe XD)', function () {
    $org     = makeOrg();
    $project = makeProject(['owner_id' => $org['manager']->id, 'project_manager_id' => $org['manager']->id]);

    Sanctum::actingAs(userFor($org['manager']));

    $file = $this->postJson("/api/v1/projects/{$project->uuid}/design-files", [
        'title'    => 'Checkout flow',
        'url'      => 'https://xd.adobe.com/view/abc123',
        'provider' => 'adobe_xd',
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/design-files/{$file['id']}/versions", [
        'label'    => 'Wireframes done',
        'progress' => 40,
        'status'   => 'in_review',
    ])->assertCreated();

    $show = $this->getJson("/api/v1/design-files/{$file['id']}")->assertOk();

    expect($show->json('data.progress'))->toBe(40);
    expect($show->json('data.status'))->toBe('in_review');
    expect($show->json('data.versions'))->toHaveCount(1);
    expect(WorkUpdate::where('kind', 'design')->count())->toBe(1);
});

test('figma sync imports named versions once', function () {
    config(['services.figma.token' => 'test-token']);

    Http::fake([
        'api.figma.com/*' => Http::response(['versions' => [
            ['id' => '1', 'created_at' => now()->subDay()->toIso8601String(), 'label' => 'Hero v1', 'description' => 'First pass', 'user' => ['handle' => 'dana']],
            ['id' => '2', 'created_at' => now()->toIso8601String(), 'label' => null, 'description' => null, 'user' => ['handle' => 'dana']],
        ]]),
    ]);

    $org     = makeOrg();
    $project = makeProject(['owner_id' => $org['manager']->id, 'project_manager_id' => $org['manager']->id]);

    Sanctum::actingAs(userFor($org['manager']));

    $file = $this->postJson("/api/v1/projects/{$project->uuid}/design-files", [
        'title' => 'Landing page', 'url' => 'https://www.figma.com/design/AbC123xyz/Landing', 'provider' => 'figma',
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/design-files/{$file['id']}/sync")->assertOk();
    $this->postJson("/api/v1/design-files/{$file['id']}/sync")->assertOk();

    // Autosave (no label) skipped; the named version imported exactly once.
    expect(DesignFile::first()->versions()->count())->toBe(1);
});

// ── Chat ─────────────────────────────────────────────────────

test('two people can message each other directly and unread counts follow', function () {
    $org = makeOrg();

    $rita = userFor($org['report']);
    $mary = userFor($org['manager']);

    Sanctum::actingAs($rita);

    $conversation = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'direct', 'user_id' => $mary->uuid,
    ])->assertCreated()->json('data');

    $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/messages", ['body' => 'Hi Mary'])->assertCreated();

    Sanctum::actingAs($mary);

    expect($this->getJson('/api/v1/chat/unread-count')->json('data.count'))->toBe(1);

    $this->getJson("/api/v1/chat/conversations/{$conversation['id']}/messages")
        ->assertOk()
        ->assertJsonPath('data.0.body', 'Hi Mary');

    $this->postJson("/api/v1/chat/conversations/{$conversation['id']}/read")->assertOk();

    expect($this->getJson('/api/v1/chat/unread-count')->json('data.count'))->toBe(0);
});

test('a direct conversation between the same pair is reused, not duplicated', function () {
    $org = makeOrg();
    $mary = userFor($org['manager']);

    Sanctum::actingAs(userFor($org['report']));

    $a = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $mary->uuid])->json('data.id');
    $b = $this->postJson('/api/v1/chat/conversations', ['type' => 'direct', 'user_id' => $mary->uuid])->json('data.id');

    expect($a)->toBe($b);
});

test('outsiders cannot read or post in a conversation', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));
    $id = $this->postJson('/api/v1/chat/conversations', [
        'type' => 'direct', 'user_id' => userFor($org['manager'])->uuid,
    ])->json('data.id');

    Sanctum::actingAs(userFor($org['designer']));

    $this->getJson("/api/v1/chat/conversations/{$id}/messages")->assertForbidden();
    $this->postJson("/api/v1/chat/conversations/{$id}/messages", ['body' => 'hello'])->assertForbidden();
});

test('team members share a team channel; people on other teams cannot join it', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['report']));
    $this->postJson('/api/v1/chat/conversations', ['type' => 'team', 'team_id' => $org['platform']->uuid])->assertCreated();

    Sanctum::actingAs(userFor($org['designer']));
    $this->postJson('/api/v1/chat/conversations', ['type' => 'team', 'team_id' => $org['platform']->uuid])->assertForbidden();
});
