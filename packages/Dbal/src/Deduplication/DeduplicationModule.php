<?php

namespace Ecotone\Dbal\Deduplication;

use Ecotone\AnnotationFinder\AnnotationFinder;
use Ecotone\Dbal\Configuration\DbalConfiguration;
use Ecotone\Messaging\Attribute\AsynchronousRunningEndpoint;
use Ecotone\Messaging\Attribute\Deduplicated;
use Ecotone\Messaging\Attribute\ModuleAnnotation;
use Ecotone\Messaging\Config\Annotation\AnnotationModule;
use Ecotone\Messaging\Config\Annotation\ModuleConfiguration\ExtensionObjectResolver;
use Ecotone\Messaging\Config\Configuration;
use Ecotone\Messaging\Config\ConsoleCommandConfiguration;
use Ecotone\Messaging\Config\Container\Definition;
use Ecotone\Messaging\Config\Container\Reference;
use Ecotone\Messaging\Config\ModulePackageList;
use Ecotone\Messaging\Config\ModuleReferenceSearchService;
use Ecotone\Messaging\Config\ServiceConfiguration;
use Ecotone\Messaging\Handler\InterfaceToCallRegistry;
use Ecotone\Messaging\Handler\Logger\LoggingGateway;
use Ecotone\Messaging\Handler\Processor\MethodInvoker\AroundInterceptorBuilder;
use Ecotone\Messaging\Handler\ServiceActivator\ServiceActivatorBuilder;
use Ecotone\Messaging\Precedence;
use Ecotone\Messaging\Scheduling\EcotoneClockInterface;

#[ModuleAnnotation]
/**
 * licence Apache-2.0
 */
class DeduplicationModule implements AnnotationModule
{
    public const REMOVE_MESSAGE_AFTER_7_DAYS = 1000 * 60 * 60 * 24 * 7;

    private function __construct()
    {
    }

    /**
     * @inheritDoc
     */
    public static function create(AnnotationFinder $annotationRegistrationService, InterfaceToCallRegistry $interfaceToCallRegistry): static
    {
        return new self();
    }

    /**
     * @inheritDoc
     */
    public function prepare(Configuration $messagingConfiguration, array $extensionObjects, ModuleReferenceSearchService $moduleReferenceSearchService, InterfaceToCallRegistry $interfaceToCallRegistry): void
    {
        $dbalConfiguration = ExtensionObjectResolver::resolveUnique(DbalConfiguration::class, $extensionObjects, DbalConfiguration::createWithDefaults());

        $isDeduplicatedEnabled = $dbalConfiguration->isDeduplicatedEnabled();
        $connectionFactory     = $dbalConfiguration->getDeduplicationConnectionReference();

        $pointcut = Deduplicated::class;
        if ($isDeduplicatedEnabled) {
            $pointcut .= '||' . AsynchronousRunningEndpoint::class;
        }

        $messagingConfiguration->registerServiceDefinition(
            DeduplicationInterceptor::class,
            new Definition(
                DeduplicationInterceptor::class,
                [
                    new Reference($connectionFactory),
                    new Reference(EcotoneClockInterface::class),
                    $dbalConfiguration->minimumTimeToRemoveMessageFromDeduplication(),
                    $dbalConfiguration->deduplicationRemovalBatchSize(),
                    new Reference(LoggingGateway::class),
                ]
            )
        );

        $messagingConfiguration
            ->registerAroundMethodInterceptor(
                AroundInterceptorBuilder::create(
                    DeduplicationInterceptor::class,
                    $interfaceToCallRegistry->getFor(DeduplicationInterceptor::class, 'deduplicate'),
                    Precedence::DATABASE_TRANSACTION_PRECEDENCE + 100,
                    $pointcut
                )
            )
            ->registerMessageHandler(
                ServiceActivatorBuilder::create(
                    DeduplicationInterceptor::class,
                    'removeExpiredMessages'
                )->withInputChannelName($inputChannelName = 'ecotone.deduplication.removeExpiredMessages')
            )
            ->registerConsoleCommand(ConsoleCommandConfiguration::create(
                $inputChannelName,
                'ecotone:deduplication:remove-expired-messages',
                []
            ));
    }

    /**
     * @inheritDoc
     */
    public function canHandle($extensionObject): bool
    {
        return $extensionObject instanceof DbalConfiguration;
    }

    public function getModuleExtensions(ServiceConfiguration $serviceConfiguration, array $serviceExtensions): array
    {
        return [];
    }

    public function getModulePackageName(): string
    {
        return ModulePackageList::DBAL_PACKAGE;
    }
}
