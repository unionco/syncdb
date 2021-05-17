<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\SyncDb;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Service\Logger;
use unionco\syncdb\Model\ChainStep;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\DatabaseSync;

class Scenario
{
    /** @var string */
    protected $name;

    /** @var SetupStep[] */
    protected $setupSteps = [];

    /** @var ChainStep[] */
    protected $chainSteps = [];

    /** @var TeardownStep[] */
    protected $teardownSteps = [];

    /** @var null|SshInfo */
    protected $sshContext = null;

    /** @var int[] Tasks to teardown */
    protected $undo = [];

    /** @var DatabaseSync */
    protected static $dbSync;

    public function __construct(string $name, SshInfo $sshContext)
    {
        $this->name = $name;
        $this->sshContext = $sshContext;
        if (!static::$dbSync) {
            static::$dbSync = SyncDb::$container->get('dbSync');
        }
    }

    /**
     * Get the value of setupSteps
     * @return SetupStep[]
     */
    public function getSetupSteps()
    {
        return $this->setupSteps;
    }

    /**
     * Set the value of setupSteps
     * @param SetupStep[] $setupSteps
     * @return  self
     */
    public function setSetupSteps(array $setupSteps): self
    {
        $this->setupSteps = $setupSteps;

        return $this;
    }

    public function addSetupStep(SetupStep $setupStep): self
    {
        $this->setupSteps[] = $setupStep;
        return $this;
    }

    /**
     * Get the value of chainSteps
     * @return ChainStep[]
     */
    public function getChainSteps()
    {
        return $this->chainSteps;
    }

    /**
     * Set the value of chainSteps
     * @param ChainStep[] $chainSteps
     * @return  self
     */
    public function setChainSteps(array $chainSteps): self
    {
        $this->chainSteps = $chainSteps;

        return $this;
    }

    /**
     * Push one chain step
     */
    public function addChainStep(ChainStep $chainStep): self
    {
        $this->chainSteps[] = $chainStep;
        return $this;
    }

    /**
     * Get the value of TeardownSteps
     * @return TeardownStep[]
     */
    public function getTeardownSteps(): array
    {
        return $this->teardownSteps;
    }

    /**
     * Set the value of TeardownSteps
     * @param TeardownStep[] $teardownSteps
     * @return  self
     */
    public function setTeardownSteps(array $teardownSteps): self
    {
        $this->teardownSteps = $teardownSteps;

        return $this;
    }

    /**
     * Push one teardown step
     */
    public function addTeardownStep(TeardownStep $teardownStep): self
    {
        $this->teardownSteps[] = $teardownStep;
        return $this;
    }

    /**
     * Run all of the setup steps for the scenario
     * @return array
     */
    public function runSetup()
    {
        $results = [];

        /** @var DatabaseSync */
        $dbSync = static::$dbSync;
        if (!$dbSync) {
            throw new \Exception('Error with dependencies');
        }

        foreach ($this->getSetupSteps() as $setupStep) {
            $cmd = $setupStep->getCommandString($this->sshContext);
            if ($setupStep->remote) {
                $result = $dbSync->runRemote($this->sshContext, $setupStep);
            } else {
                $result = $dbSync->runLocal($setupStep);
            }

            $results[] = [
                'stage' => 'setup',
                'id' => $setupStep->id,
                'name' => $setupStep->getName(),
                'command' => $cmd,
                'result' => $result,
            ];
            if ($result === false) {
                /** @var Logger */
                $log = SyncDb::$container->get('log');
                $log->error(json_encode($results));
                throw new \Exception('Stopping');
            }
        }
        return $results;
    }

    /**
     * Run the chained commands in the scenario. Typically, these are the main actions
     * (not setup/cleanup commands)
     * @return list<array{stage:string,id:int,name:string,command:string,result:string|false}>
     */
    public function runChain()
    {
        $results = [];

        foreach ($this->getChainSteps() as $chainStep) {
            $cmd = $chainStep->getCommandString($this->sshContext);
            if ($chainStep->remote) {
                $result = static::$dbSync->runRemote($this->sshContext, $chainStep);
            } else {
                $result = static::$dbSync->runLocal($chainStep);
            }

            $results[] = [
                'stage' => 'chain',
                'id' => (int) $chainStep->id,
                'name' => $chainStep->getName(),
                'command' => $cmd,
                'result' => $result,
            ];
            if ($result === false) {
                /** @var Logger */
                $log = SyncDb::$container->get('log');
                $log->error(json_encode($results));

                $undoIds = \array_map(function ($result): int {
                    return $result['id'];
                }, $results);
                $this->undo = $undoIds;
            }
        }
        return $results;
    }

    public function runTeardown()
    {
        $results = [];

        // If there are explicit undo IDs, do only those steps.
        // Otherwise, run all teardown steps
        /** @var TeardownStep[] */
        $steps = $this->getTeardownSteps();
        if ($this->undo) {
            $undoIds = $this->undo;
            $steps = \array_filter($steps, function (TeardownStep $step) use ($undoIds): bool {
                return \in_array($step->relatedId, $undoIds);
            });
        }
        foreach ($steps as $teardownStep) {
            $cmd = $teardownStep->getCommandString($this->sshContext);
            if ($teardownStep->remote) {
                $result = static::$dbSync->runRemote($this->sshContext, $teardownStep);
            } else {
                $result = static::$dbSync->runLocal($teardownStep);
            }

            $results[] = [
                'stage' => 'teardown',
                'id' => $teardownStep->id,
                'name' => $teardownStep->getName(),
                'relatedId' => $teardownStep->relatedId,
                'command' => $cmd,
                'result' => $result,
            ];
            if ($result === false) {
                throw new \Exception('Stopping');
                // run related teardown
            }
        }
        return $results;
    }

    public function run(): array
    {
        $results = [];

        try {
            $results = $this->runSetup();
            $results = \array_merge($results, $this->runChain());
            $results = \array_merge($results, $this->runTeardown());
        } catch (\Exception $e) {
            $results = array_merge($results, $this->runTeardown());
        }
        return $results;
    }

    /**
     * Get the value of sshContext
     */
    public function getSshContext()
    {
        return $this->sshContext;
    }

    /**
     * Set the value of sshContext
     *
     * @return  self
     */
    public function setSshContext($sshContext)
    {
        $this->sshContext = $sshContext;

        return $this;
    }
}
