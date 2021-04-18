<?php

namespace App\Model;

use Symfony\Component\Process\Process;

class LocalCommand
{
    /** @var string */
    protected $command;
    /** @var string */
    protected $baseDir;
    /** @var string */
    protected $output;

    public function execute()
    {
        $cmd = $this->commandPrefix() . ' ' . $this->getCommand();
        $cmdExploded = explode(' ', $cmd);
        $proc = new Process($cmd, $this->getBaseDir())
    }

    public function commandPrefix()
    {
        return "";
    }
}
