<?php

namespace OldSound\RabbitMqBundle\Command;

use OldSound\RabbitMqBundle\RabbitMq\BaseConsumer as Consumer;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RpcServerCommand extends BaseRabbitMqCommand
{
    protected $rpcServer;

    public function stopRpcServer()
    {
        if ($this->rpcServer instanceof Consumer) {
            $this->rpcServer->forceStopConsumer();
        } else {
            exit();
        }
    }

    public function restartRpcServer()
    {
        // TODO: Implement restarting of rpcServer
    }

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('rabbitmq:rpc-server')
            ->addArgument('name', InputArgument::REQUIRED, 'Server Name')
            ->addOption('messages', 'm', InputOption::VALUE_OPTIONAL, 'Messages to consume', 0)
            ->addOption('memory-limit', 'l', InputOption::VALUE_OPTIONAL, 'Allowed memory for this process', null)
            ->addOption('debug', 'd', InputOption::VALUE_OPTIONAL, 'Debug mode', false)
            ->addOption('without-signals', 'w', InputOption::VALUE_NONE, 'Disable catching of system signals')
        ;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \InvalidArgumentException When the number of messages to consume is less than 0
     * @throws \BadFunctionCallException When the pcntl is not installed and option -s is true
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (defined('AMQP_WITHOUT_SIGNALS') === false) {
            define('AMQP_WITHOUT_SIGNALS', $input->getOption('without-signals'));
        }

        if (!AMQP_WITHOUT_SIGNALS && extension_loaded('pcntl')) {
            if (!function_exists('pcntl_signal')) {
                throw new \BadFunctionCallException("Function 'pcntl_signal' is referenced in the php.ini 'disable_functions' and can't be called.");
            }

            pcntl_signal(SIGTERM, array(&$this, 'stopRpcServer'));
            pcntl_signal(SIGINT, array(&$this, 'stopRpcServer'));
            pcntl_signal(SIGHUP, array(&$this, 'restartRpcServer'));
        }

        define('AMQP_DEBUG', (bool) $input->getOption('debug'));
        $amount = $input->getOption('messages');

        if (0 > $amount) {
            throw new \InvalidArgumentException("The -m option should be null or greater than 0");
        }

        $this->rpcServer = $this->getContainer()
            ->get(sprintf('old_sound_rabbit_mq.%s_server', $input->getArgument('name')));

        if (!is_null($input->getOption('memory-limit')) && ctype_digit((string)$input->getOption('memory-limit')) && $input->getOption('memory-limit') > 0) {
            $this->rpcServer->setMemoryLimit($input->getOption('memory-limit'));
        }

        $this->rpcServer->start($amount);
    }
}
