<?php

declare(strict_types=1);

namespace Enqueue\Dbal;

use Doctrine\DBAL\Connection;
use Ecotone\Dbal\Compatibility\SchemaManagerCompatibility;
use Ecotone\Messaging\Scheduling\Clock;
use Ecotone\Messaging\Scheduling\EcotoneClockInterface;
use Interop\Queue\Consumer;
use Interop\Queue\Context;
use Interop\Queue\Destination;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\TemporaryQueueNotSupportedException;
use Interop\Queue\Message;
use Interop\Queue\Producer;
use Interop\Queue\Queue;
use Interop\Queue\SubscriptionConsumer;
use Interop\Queue\Topic;
use InvalidArgumentException;
use LogicException;

/**
 * licence MIT
 * code comes from https://github.com/php-enqueue/dbal
 */
class DbalContext implements Context
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var callable
     */
    private $connectionFactory;

    /**
     * @var array
     */
    private $config;
    private EcotoneClockInterface $clock;

    /**
     * Callable must return instance of Doctrine\DBAL\Connection once called.
     *
     * @param Connection|callable $connection
     */
    public function __construct($connection, array $config = [])
    {
        $this->config = array_replace([
            'table_name' => 'enqueue',
            'polling_interval' => null,
            'subscription_polling_interval' => null,
        ], $config);

        if ($connection instanceof Connection) {
            $this->connection = $connection;
        } elseif (is_callable($connection)) {
            $this->connectionFactory = $connection;
        } else {
            throw new InvalidArgumentException(sprintf('The connection argument must be either %s or callable that returns %s.', Connection::class, Connection::class));
        }

        $this->clock = Clock::get();
    }

    /**
     * {@inheritdoc}
     */
    public function createMessage(string $body = '', array $properties = [], array $headers = []): Message
    {
        $message = new DbalMessage();
        $message->setBody($body);
        $message->setProperties($properties);
        $message->setHeaders($headers);

        return $message;
    }

    /**
     * @return DbalDestination
     */
    public function createQueue(string $name): Queue
    {
        return new DbalDestination($name);
    }

    /**
     * @return DbalDestination
     */
    public function createTopic(string $name): Topic
    {
        return new DbalDestination($name);
    }

    public function createTemporaryQueue(): Queue
    {
        throw TemporaryQueueNotSupportedException::providerDoestNotSupportIt();
    }

    /**
     * @return DbalProducer
     */
    public function createProducer(): Producer
    {
        return new DbalProducer($this);
    }

    /**
     * @return DbalConsumer
     */
    public function createConsumer(Destination $destination): Consumer
    {
        InvalidDestinationException::assertDestinationInstanceOf($destination, DbalDestination::class);

        $consumer = new DbalConsumer($this, $destination);

        if (isset($this->config['polling_interval'])) {
            $consumer->setPollingInterval((int) $this->config['polling_interval']);
        }

        if (isset($this->config['redelivery_delay'])) {
            $consumer->setRedeliveryDelay((int) $this->config['redelivery_delay']);
        }

        return $consumer;
    }

    public function close(): void
    {
    }

    public function createSubscriptionConsumer(): SubscriptionConsumer
    {
        $consumer = new DbalSubscriptionConsumer($this);

        if (isset($this->config['redelivery_delay'])) {
            $consumer->setRedeliveryDelay($this->config['redelivery_delay']);
        }

        if (isset($this->config['subscription_polling_interval'])) {
            $consumer->setPollingInterval($this->config['subscription_polling_interval']);
        }

        return $consumer;
    }

    /**
     * @internal It must be used here and in the consumer only
     */
    public function convertMessage(array $arrayMessage): DbalMessage
    {
        /** @var DbalMessage $message */
        $message = $this->createMessage(
            $arrayMessage['body'],
            $arrayMessage['properties'] ? JSON::decode($arrayMessage['properties']) : [],
            $arrayMessage['headers'] ? JSON::decode($arrayMessage['headers']) : []
        );

        if (isset($arrayMessage['id'])) {
            $message->setMessageId($arrayMessage['id']);
        }
        if (isset($arrayMessage['queue'])) {
            $message->setQueue($arrayMessage['queue']);
        }
        if (isset($arrayMessage['redelivered'])) {
            $message->setRedelivered((bool) $arrayMessage['redelivered']);
        }
        if (isset($arrayMessage['priority'])) {
            $message->setPriority((int) (-1 * $arrayMessage['priority']));
        }
        if (isset($arrayMessage['published_at'])) {
            $message->setPublishedAt((int) $arrayMessage['published_at']);
        }
        if (isset($arrayMessage['delivery_id'])) {
            $message->setDeliveryId($arrayMessage['delivery_id']);
        }
        if (isset($arrayMessage['redeliver_after'])) {
            $message->setRedeliverAfter((int) $arrayMessage['redeliver_after']);
        }

        return $message;
    }

    /**
     * @param DbalDestination $queue
     */
    public function purgeQueue(Queue $queue): void
    {
        $this->getDbalConnection()->delete(
            $this->getTableName(),
            ['queue' => $queue->getQueueName()],
            ['queue' => DbalType::STRING]
        );
    }

    public function getTableName(): string
    {
        return $this->config['table_name'];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getDbalConnection(): Connection
    {
        if (false == $this->connection) {
            $connection = call_user_func($this->connectionFactory);
            if (false == $connection instanceof Connection) {
                throw new LogicException(sprintf('The factory must return instance of Doctrine\DBAL\Connection. It returns %s', is_object($connection) ? get_class($connection) : gettype($connection)));
            }

            $this->connection = $connection;
        }

        return $this->connection;
    }

    public function createDataBaseTable(): void
    {
        $connection = $this->getDbalConnection();
        $tableExists = SchemaManagerCompatibility::tableExists($connection, $this->getTableName());

        if ($tableExists) {
            return;
        }

        $table = SchemaManagerCompatibility::getTableToCreate($connection, $this->getTableName());

        $table->addColumn('id', 'guid', ['length' => 16, 'fixed' => true]);
        $table->addColumn('published_at', 'bigint');
        $table->addColumn('body', 'text', ['notnull' => false]);
        $table->addColumn('headers', 'text', ['notnull' => false]);
        $table->addColumn('properties', 'text', ['notnull' => false]);
        $table->addColumn('redelivered', 'boolean', ['notnull' => false]);
        $table->addColumn('queue', 'string', ['length' => 255]);
        $table->addColumn('priority', 'integer', ['notnull' => false]);
        $table->addColumn('delayed_until', 'bigint', ['notnull' => false]);
        $table->addColumn('time_to_live', 'bigint', ['notnull' => false]);
        $table->addColumn('delivery_id', 'guid', ['length' => 16, 'fixed' => true, 'notnull' => false]);
        $table->addColumn('redeliver_after', 'bigint', ['notnull' => false]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['priority', 'published_at', 'queue', 'delivery_id', 'delayed_until', 'id']);
        $table->addIndex(['redeliver_after', 'delivery_id']);
        $table->addIndex(['time_to_live', 'delivery_id']);
        $table->addIndex(['delivery_id']);

        $schemaManager = SchemaManagerCompatibility::getSchemaManager($connection);
        $schemaManager->createTable($table);
    }

    public function getClock(): EcotoneClockInterface
    {
        return $this->clock;
    }
}
