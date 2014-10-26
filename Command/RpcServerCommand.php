<?php

namespace OldSound\RabbitMqBundle\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RpcServerCommand extends BaseRabbitMqCommand
{

    protected function configure()
    {
        parent::configure();

        $this
            ->setName('rabbitmq:rpc-server')
            ->addArgument('name', InputArgument::REQUIRED, 'Server Name')
            ->addOption('messages', 'm', InputOption::VALUE_OPTIONAL, 'Messages to consume', 0)
            ->addOption('memory-limit', 'l', InputOption::VALUE_OPTIONAL, 'Allowed memory for this process', null)
            ->addOption('debug', 'd', InputOption::VALUE_OPTIONAL, 'Debug mode', false)
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
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        define('AMQP_DEBUG', (bool) $input->getOption('debug'));
        $amount = $input->getOption('messages');

        if (0 > $amount) {
            throw new \InvalidArgumentException("The -m option should be null or greater than 0");
        }

        $rpcServer = $this->getContainer()
            ->get(sprintf('old_sound_rabbit_mq.%s_server', $input->getArgument('name')));

        if (!is_null($input->getOption('memory-limit')) && ctype_digit((string)$input->getOption('memory-limit')) && $input->getOption('memory-limit') > 0) {
            $rpcServer->setMemoryLimit($input->getOption('memory-limit'));
        }

        $rpcServer->start($amount);
    }
}
