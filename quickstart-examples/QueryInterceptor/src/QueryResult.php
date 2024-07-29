<?php

namespace App\QueryInterceptor;

class QueryResult
{
    private ?int $executionTime = null;
    public function __construct(
        private mixed $value,
    ) {
    }

    public function setExecutionTime(int $executionTime): void
    {
        $this->executionTime = $executionTime;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getExecutionTime(): ?int
    {
        return $this->executionTime;
    }
}