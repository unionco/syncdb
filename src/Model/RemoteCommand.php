<?php

namespace App\Model;

use Symfony\Component\Process\Process;

class RemoteCommand
{
    protected $remoteConfig;
    // public function execute()
    // {
    //     $cmd = $this->commandPrefix() . ' ' . $this->getCommand();
    //     $cmdExploded = explode(' ', $cmd);
    //     $proc = new Process($cmd, $this->getBaseDir())
    // }

    public function commandPrefix()
    {
        return "";
    }
}
