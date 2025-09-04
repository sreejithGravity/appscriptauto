<?php
namespace App;

use Monolog\Level;
use Monolog\Logger as MLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger {
    public static function make(string $path): MLogger {
        $logger = new MLogger('app');
        $handler = new StreamHandler($path, Level::Info);
        $handler->setFormatter(new LineFormatter(null, null, true, true));
        $logger->pushHandler($handler);
        return $logger;
    }
}
