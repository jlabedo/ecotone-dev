<?php declare(strict_types=1);

namespace App\EventSourcing;

use App\EventSourcing\Event\PriceWasChanged;
use App\EventSourcing\Event\ProductWasRegistered;
use Ecotone\EventSourcing\Attribute\Projection;
use Ecotone\EventSourcing\Attribute\ProjectionInitialization;
use Ecotone\Messaging\Support\Assert;
use Ecotone\Modelling\Attribute\EventHandler;
use Ecotone\Modelling\Attribute\QueryHandler;

#[Projection("price_change_over_time", Product::class)]
class PriceChangeOverTimeProjection
{
    /** @var PriceChange[][] */
    private array $priceChangeOverTime = [];

    private bool $isInitialized = false;

    /**
     * @return PriceChange[]
     */
    #[QueryHandler("product.getPriceChange")]
    public function getPriceChangesFor(int $productId): array
    {
        if (!isset($this->priceChangeOverTime[$productId])) {
            return [];
        }

        return $this->priceChangeOverTime[$productId];
    }

    #[EventHandler]
    public function registerPrice(ProductWasRegistered $event): void
    {
        Assert::isTrue($this->isInitialized, "Projection is not initialized");
        $this->priceChangeOverTime[$event->getProductId()][] = new PriceChange($event->getPrice(), 0);
    }

    #[EventHandler]
    public function registerPriceChange(PriceWasChanged $event): void
    {
        Assert::isTrue($this->isInitialized, "Projection is not initialized");
        $lastPrice = end($this->priceChangeOverTime[$event->getProductId()]);
        $this->priceChangeOverTime[$event->getProductId()][] = new PriceChange($event->getPrice(), $event->getPrice() - $lastPrice->getPrice());
    }

    #[ProjectionInitialization]
    public function initialize(): void
    {
        $this->isInitialized = true;
    }
}