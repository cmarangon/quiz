<?php

use App\Services\QuestionTypeRegistry;
use App\Contracts\QuestionTypeInterface;

test('registry resolves a registered question type', function () {
    $registry = app(QuestionTypeRegistry::class);
    $type = $registry->resolve('multiple_choice');
    expect($type)->toBeInstanceOf(QuestionTypeInterface::class);
});

test('registry throws on unknown question type', function () {
    $registry = app(QuestionTypeRegistry::class);
    $registry->resolve('nonexistent');
})->throws(InvalidArgumentException::class, 'Unknown question type: nonexistent');

test('registry lists all registered types', function () {
    $registry = app(QuestionTypeRegistry::class);
    expect($registry->registered())->toContain('multiple_choice', 'true_false');
});
