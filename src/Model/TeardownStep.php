<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\Step;

class TeardownStep extends Step
{
    public function __construct(string $name, array $commands, Step $related = null, bool $remote = true, bool $chain = false, bool $ignoreWarnings = false)
    {
        parent::__construct($name, $commands, $remote, $chain, $ignoreWarnings);
        if ($related) {
            $this->setRelatedId($related->getId());
        }
    }
}
