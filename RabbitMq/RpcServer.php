<?php

namespace OldSound\RabbitMqBundle\RabbitMq;

use PhpAmqpLib\Message\AMQPMessage;

class RpcServer extends BaseConsumer
{
    /**
     * @var int $memoryLimit
     */
    protected $memoryLimit = null;

    /**
     * Set the memory limit
     *
     * @param int $memoryLimit
     */
    public function setMemoryLimit($memoryLimit)
    {
        $this->memoryLimit = $memoryLimit;
    }

    /**
     * Get the memory limit
     *
     * @return int
     */
    public function getMemoryLimit()
    {
        return $this->memoryLimit;
    }

    public function initServer($name)
    {
        $this->setExchangeOptions(array('name' => '', 'type' => 'direct', 'declare' => false));
        $this->setQueueOptions(array('name' => $name));
    }

    public function processMessage(AMQPMessage $msg)
    {
        try {
            $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            $result = call_user_func($this->callback, $msg);
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
