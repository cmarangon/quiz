<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class QuestionImageStorage
{
    public function store(UploadedFile $file): string
    {
        return $file->store('questions', 'public');
    }

    public function delete(string $path): void
    {
        Storage::disk('public')->delete($path);
    }
}
