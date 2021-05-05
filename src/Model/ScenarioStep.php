<?php

namespace unionco\syncdb\Model;

class ScenarioStep extends Step
{
    public function __construct($name, $remote = true)
    {
        parent::__construct($name, [], $remote);
    }
}
