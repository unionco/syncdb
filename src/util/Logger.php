<?php

namespace abryrath\syncdb\util;

interface Logger
{
    public function log($text, $level = 'normal'): void;
    public function logCmd($text): void;
    public function logOutput($text): void;
}
