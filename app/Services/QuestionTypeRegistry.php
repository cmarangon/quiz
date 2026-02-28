<?php

namespace App\Services;

use App\Contracts\QuestionTypeInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class QuestionTypeRegistry
{
    public function __construct(
        private readonly Container $container,
        private readonly array $types,
    ) {}

    public function resolve(string $typeKey): QuestionTypeInterface
    {
        if (! isset($this->types[$typeKey])) {
            throw new InvalidArgumentException("Unknown question type: {$typeKey}");
        }
        return $this->container->make($this->types[$typeKey]);
    }

    public function registered(): array
    {
        return array_keys($this->types);
    }
}
