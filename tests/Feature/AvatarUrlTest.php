<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('uploaded photos are served even when the public/storage link is missing', function () {
    Storage::fake('public');
    Storage::disk('public')->put('employees/avatars/me.jpg', 'fake-image-bytes');

    $this->get('/storage/employees/avatars/me.jpg')->assertOk();
});

test('the storage route returns 404 for missing files and refuses traversal', function () {
    Storage::fake('public');

    $this->get('/storage/employees/avatars/nope.jpg')->assertNotFound();
    $this->get('/storage/..%2F.env')->assertNotFound();
});
