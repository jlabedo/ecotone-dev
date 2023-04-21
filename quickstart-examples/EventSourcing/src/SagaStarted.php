<?php declare(strict_types=1);

namespace App\EventSourcing;

class SagaStarted
{
    public function __construct(private int $productId) {}

    public function getProductId(): int
    {
        return $this->productId;
    }
}