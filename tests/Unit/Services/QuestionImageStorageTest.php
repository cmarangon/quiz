<?php

use App\Services\QuestionImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('store puts the file on the public disk under questions/ and returns its path', function () {
    Storage::fake('public');
    $service = new QuestionImageStorage;

    $file = UploadedFile::fake()->image('flag.png');
    $path = $service->store($file);

    expect($path)->toStartWith('questions/');
    Storage::disk('public')->assertExists($path);
});

test('delete removes the stored file', function () {
    Storage::fake('public');
    $service = new QuestionImageStorage;

    $path = $service->store(UploadedFile::fake()->image('flag.png'));
    $service->delete($path);

    Storage::disk('public')->assertMissing($path);
});

test('delete is a no-op when the file is already gone', function () {
    Storage::fake('public');
    $service = new QuestionImageStorage;

    $service->delete('questions/does-not-exist.png');

    // No exception thrown — that's the assertion.
    expect(true)->toBeTrue();
});
