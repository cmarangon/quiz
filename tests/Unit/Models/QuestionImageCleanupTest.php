<?php

use App\Models\Category;
use App\Models\Question;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('deleting a match_pairs question removes its uploaded images', function () {
    Storage::fake('public');

    $leftImagePath = Storage::disk('public')->put('questions', UploadedFileStub());
    $rightImagePath = Storage::disk('public')->put('questions', UploadedFileStub());

    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->matchPairs()->create([
        'options' => [
            'left' => [
                ['kind' => 'image', 'value' => $leftImagePath],
                ['kind' => 'text', 'value' => 'B'],
                ['kind' => 'text', 'value' => 'C'],
                ['kind' => 'text', 'value' => 'D'],
            ],
            'right' => [
                ['kind' => 'image', 'value' => $rightImagePath],
                ['kind' => 'text', 'value' => 'X'],
                ['kind' => 'text', 'value' => 'Y'],
                ['kind' => 'text', 'value' => 'Z'],
            ],
        ],
    ]);

    $question->delete();

    Storage::disk('public')->assertMissing($leftImagePath);
    Storage::disk('public')->assertMissing($rightImagePath);
});

test('deleting a question of another type does not touch the public disk', function () {
    Storage::fake('public');

    $category = Category::factory()->create();
    $question = Question::factory()->for($category)->create();

    $question->delete();

    // No files were ever written; this just asserts no exception is thrown
    // for a type with no "options.left"/"options.right" shape.
    expect(Question::find($question->id))->toBeNull();
});

function UploadedFileStub(): UploadedFile
{
    return UploadedFile::fake()->image('flag.png');
}
