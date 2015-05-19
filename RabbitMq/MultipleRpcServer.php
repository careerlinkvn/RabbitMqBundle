<?php

namespace OldSound\RabbitMqBundle\RabbitMq;

use OldSound\RabbitMqBundle\RabbitMq\Exception\QueueNotFoundException;
use PhpAmqpLib\Message\AMQPMessage;

class MultipleRpcServer extends RpcServer
{
    protected $queues = array();

    public function getQueueConsumerTag($queue)
    {
        return sprintf('%s-%s', $this->getConsumerTag(), $queue);
    }

    public function setQueues(array $queues)
    {
        $this->queues = $queues;
    }

    public function initServer($queueNames)
    {
        $this->setExchangeOptions(array('name' => '', 'type' => 'direct', 'declare' => false));
    }

    protected function setupConsumer()
    {
        if ($this->autoSetupFabric) {
            $this->setupFabric();
        }

        foreach ($this->queues as $name => $options) {
            //PHP 5.3 Compliant
            $currentObject = $this;

            $this->getChannel()->basic_consume($name, $this->getQueueConsumerTag($name), false, false, false, false, function (AMQPMessage $msg) use($currentObject, $name) {
                $this->processQueueMessage($name, $msg);
            });
        }
    }

    protected function queueDeclare()
    {
        foreach ($this->queues as $name => $options) {
            list($queueName, ,) = $this->getChannel()->queue_declare($name, $options['passive'],
                $options['durable'], $options['exclusive'],
                $options['auto_delete'], $options['nowait'],
                $options['arguments'], $options['ticket']);

            if (isset($options['routing_keys']) && count($options['routing_keys']) > 0) {
                foreach ($options['routing_keys'] as $routingKey) {
                    $this->getChannel()->queue_bind($queueName, $this->exchangeOptions['name'], $routingKey);
                }
            } else {
                if ($this->exchangeOptions['declare']) {
                    $this->getChannel()->queue_bind($queueName, $this->exchangeOptions['name'], $this->routingKey);
                }
            }
        }

        $this->queueDeclared = true;
    }

    public function processQueueMessage($queueName, AMQPMessage $msg)
    {
        if (!isset($this->queues[$queueName])) {
            throw new QueueNotFoundException();
        }

        try {
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            $result = call_user_func_array($this->queues[$queueName]['callback'], array($msg, $queueName));
            $this->sendReply($result, $msg->get('reply_to'), $msg->get('correlation_id'));
            $this->consumed++;
            $this->maybeStopConsumer();

            if (!is_null($this->getMemoryLimit()) && $this->isRamAlmostOverloaded()) {
                $this->stopConsuming();
            }
        } catch (\Exception $e) {
            $this->sendReply('error: ' . $e->getMessage(), $msg->get('reply_to'), $msg->get('correlation_id'));

            throw $e;
        }
    }

    public function stopConsuming()
    {
        foreach ($this->queues as $name => $options) {
            $this->getChannel()->basic_cancel($this->getQueueConsumerTag($name));
        }
    }

    protected function sendReply($result, $client, $correlationId)
    {
        $reply = new AMQPMessage($result, array('content_type' => 'text/plain', 'correlation_id' => $correlationId));
        $this->getChannel()->basic_publish($reply, '', $client);
    }

    /**
     * Checks if memory in use is greater or equal than memory allowed for this process
     *
     * @return boolean
     */
    protected function isRamAlmostOverloaded()
    {
        if (memory_get_usage(true) >= ($this->getMemoryLimit() * 1024 * 1024)) {
            return true;
        } else {
            return false;
        }
    }
}
