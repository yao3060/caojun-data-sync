<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class LogFactory
{
    private array $logger;

    public function make(string $name = 'app'): Logger
    {
        if (isset($this->logger[$name]) && $this->logger[$name] instanceof Logger) {
            return $this->logger[$name];
        }
        // create a log channel
        $this->logger[$name] = new Logger($name);
        $this->logger[$name]->pushHandler(
            new StreamHandler(
                __DIR__ . '/logs/' . date('Y-m-d') . '/' . $name . '.log',
                Logger::INFO
            )
        );

        return $this->logger[$name];
    }
}
