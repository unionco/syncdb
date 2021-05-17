<?php

namespace unionco\syncdb\Model;

/**
 * The main actions in a scenario are considered 'Chain' steps.
 * If any one of the chain steps fail, the entire scenario is abandoned and SyncDb
 * will attempt to run the appropriate teardown steps.
 */
class ChainStep extends Step
{
    // public function __construct(string $name, bool $remote = true, bool $chain = true, bool $ignoreWarnings = false)
    // {
    //     parent::__construct($name, [], $remote, $chain, $ignoreWarnings);
    // }
}
