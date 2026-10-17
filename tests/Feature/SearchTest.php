<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => seedPermissions());

test('search returns only projects the person can open', function () {
    $org = makeOrg();

    $visible = makeProject(['name' => 'Alpha Portal', 'owner_id' => $org['manager']->id, 'project_manager_id' => $org['manager']->id]);
    $visible->members()->attach($org['report']->id, ['role' => 'contributor', 'added_by' => $org['manager']->user_id]);

    makeProject(['name' => 'Alpha Secret', 'owner_id' => $org['designer']->id, 'project_manager_id' => $org['designer']->id]);

    Sanctum::actingAs(userFor($org['report']));

    $names = collect($this->getJson('/api/v1/search?q=Alpha')->assertOk()->json('data.projects'))->pluck('name')->all();

    expect($names)->toBe(['Alpha Portal']);
});

test('very short search terms return nothing', function () {
    $org = makeOrg();

    Sanctum::actingAs(userFor($org['manager']));

    $this->getJson('/api/v1/search?q=a')->assertOk()->assertJsonPath('data.projects', []);
});

test('responses carry security headers', function () {
    $this->getJson('/up')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
});
