<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\Step;

class TeardownStep extends Step
{
    public $relatedId;
    public function __construct($name, $commands, $related = null, $remote = true, $chain = false)
    {
        parent::__construct($name, $commands, $remote, $chain);
        if ($related) {
            $this->relatedId = $related->id;
        }
    }
}
