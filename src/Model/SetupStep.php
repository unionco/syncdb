<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\Step;

class SetupStep extends Step
{
    protected $teardownCommands;
    // public function __construct($setupCommands, $teardownCommands)
    // {
    //     parent::__construct($setupCommands);
    //     $this->setTeardownCommands($teardownCommands);
    // }

    // protected function setTeardownCommands($commands)
    // {
    //     if (\is_array($commands)) {
    //         $this->teardownCommands = $commands;
    //     } else {
    //         $this->teardownCommands = [$commands];
    //     }
    //     return $this;
    // }
    // public function getTeardownCommands()
    // {
    //     return $this->teardownCommands;
    // }
}
