<?php

namespace abryrath\syncdb\util;

interface LoggerInterface
{
    public function log(string $text, string $level = 'normal'): void;
    public function logCmd(string $text): void;
    public function logOutput(array $text): void;
}
