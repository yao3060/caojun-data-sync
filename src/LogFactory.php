<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class LogFactory
{
    private $logger;

    public function make(string $name = 'app'): Logger
    {
        if ($this->logger instanceof Logger) {
            return $this->logger;
        }
        // create a log channel
        $this->logger = new Logger($name);
        $this->logger->pushHandler(
            new StreamHandler(
                __DIR__ . '/logs/' . date('Y-m-d') . '/' . $name . '.log',
                Logger::INFO
            )
        );

        return $this->logger;
    }
}
