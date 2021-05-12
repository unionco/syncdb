<?php

namespace unionco\syncdb\Model;

class ScenarioStep extends Step
{
    public function __construct($name, $remote = true, $chain = true, $ignoreWarnings = false)
    {
        parent::__construct($name, [], $remote, $chain, $ignoreWarnings);
    }
}
