<?php

use App\OutboxPattern\Domain\OrderRepository;
use App\OutboxPattern\Domain\PlaceOrder;
use App\OutboxPattern\Infrastructure\Configuration;
use Ecotone\Lite\EcotoneLiteApplication;
use Enqueue\Dbal\DbalConnectionFactory;
use PHPUnit\Framework\Assert;

require __DIR__ . "/vendor/autoload.php";
$messagingSystem = EcotoneLiteApplication::bootstrap(
    pathToRootCatalog: __DIR__,
);
$queryBus = $messagingSystem->getQueryBus();

$result = $queryBus->sendWithRouting("getProductName");

var_export($result);