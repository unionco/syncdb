<?php

namespace abryrath\syncdb\util;

interface Logger
{
    public function log(string $text, string $level = 'normal'): void;
    public function logCmd(string $text): void;
    public function logOutput(array $text): void;
}
