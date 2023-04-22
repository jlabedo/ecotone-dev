<?php

use App\EventSourcing\Command\ChangePrice;
use App\EventSourcing\Command\RegisterProduct;
use App\EventSourcing\PriceChange;
use Ecotone\Lite\EcotoneLiteApplication;
use PHPUnit\Framework\Assert;

require __DIR__ . "/vendor/autoload.php";
$messagingSystem = EcotoneLiteApplication::boostrap(pathToRootCatalog: __DIR__);

/** If you do not do it manually, then projection will be created automatically for you */
$messagingSystem->runConsoleCommand("ecotone:es:initialize-projection", ["name" => "price_change_over_time"]);

$productId = 3;

$messagingSystem->getCommandBus()->send(new RegisterProduct($productId, 100));

