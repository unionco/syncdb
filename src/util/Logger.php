<?php

namespace abryrath\syncdb\util;

interface Logger
{
    public function log($text, $level = 'normal') : void;
}
