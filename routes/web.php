<?php

use App\Livewire\QuizBuilder;
use App\Livewire\QuizIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('/quizzes', QuizIndex::class)->name('quizzes.index');
    Route::get('/quizzes/create', QuizBuilder::class)->name('quizzes.create');
    Route::get('/quizzes/{quiz}/edit', QuizBuilder::class)->name('quizzes.edit');
});

require __DIR__.'/settings.php';
