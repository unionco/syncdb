<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\DatabaseSync;
use unionco\syncdb\SyncDb;

class Scenario
{
    /** @var string */
    protected $name;

    /** @var ScenarioStep[] */
    protected $setupSteps = [];

    /** @var SetupStep[] */
    protected $chainSteps = [];

    /** @var TeardownStep[] */
    protected $teardownSteps = [];

    /** @var null|SshInfo */
    protected $sshContext = null;

    /** @var DatabaseSync|null */
    protected static $dbSync = null;

    public function __construct($name, $sshContext)
    {
        $this->name = $name;
        $this->sshContext = $sshContext;
        if (!static::$dbSync) {
            static::$dbSync = SyncDb::$container->get('dbSync');
        }
    }

    /**
     * Get the value of setupSteps
     * @return ScenarioStep[]
     */
    public function getSetupSteps()
    {
        return $this->setupSteps;
    }

    /**
     * Set the value of setupSteps
     * @param ScenarioStep[] $setupSteps
     * @return  self
     */
    public function setSetupSteps($setupSteps): self
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
     * @return ScenarioStep[]
     */
    public function getChainSteps()
    {
        return $this->chainSteps;
    }

    /**
     * Set the value of chainSteps
     * @param ScenarioStep[] $chainSteps
     * @return  self
     */
    public function setChainSteps($chainSteps): self
    {
        $this->chainSteps = $chainSteps;

        return $this;
    }

    public function addChainStep(ScenarioStep $chainStep): self
    {
        $this->chainSteps[] = $chainStep;
        return $this;
    }

    /**
     * Get the value of TeardownSteps
     * @return ScenarioStep[]
     */
    public function getTeardownSteps()
    {
        return $this->teardownSteps;
    }

    /**
     * Set the value of TeardownSteps
     * @param ScenarioStep[] $teardownSteps
     * @return  self
     */
    public function setTeardownSteps($teardownSteps): self
    {
        $this->teardownSteps = $teardownSteps;

        return $this;
    }

    public function addTeardownStep(TeardownStep $teardownStep): self
    {
        $this->teardownSteps[] = $teardownStep;
        return $this;
    }

    /**
     * Debug
     * @return string a text representation of all of the steps to run
     */
    public function preview()
    {
        $output = '> setup steps' . PHP_EOL;
        foreach ($this->getSetupSteps() as $setupStep) {
            $output .= "\n\t{$setupStep->id}\n\t{$setupStep->getName()}\n\t{$setupStep->getCommandString($this->sshContext)}\n";
        }
        $output .= '> chain steps' . PHP_EOL;
        foreach ($this->getChainSteps() as $chainStep) {
            $output .= "\n\t{$chainStep->id}\n\t{$chainStep->getName()}\n\t{$chainStep->getCommandString($this->sshContext)}\n";
        }

        $output .= '> teardown steps' . PHP_EOL;
        foreach ($this->getTeardownSteps() as $teardownStep) {
            $output .= "\n\t{$teardownStep->id} -> {$teardownStep->relatedId}\n\t{$teardownStep->getName()}\n\t{$teardownStep->getCommandString($this->sshContext)}\n";
        }
        return $output;
    }

    public function runSetup()
    {
        $results = [];

        foreach ($this->getSetupSteps() as $setupStep) {
            $cmd = $setupStep->getCommandString($this->sshContext);
            if ($setupStep->remote) {
                $result = static::$dbSync->runRemote($this->sshContext, $setupStep);
            } else {
                $result = static::$dbSync->runLocal($setupStep);
            }

            $results[] = [
                'stage' => 'setup',
                'id' => $setupStep->id,
                // 'teardownId' => $setupStep->get,
                'name' => $setupStep->getName(),
                'command' => $cmd,
                'result' => $result,
            ];
            if ($result === false) {
                var_dump($results);
                throw new \Exception('Stopping');
                // run related teardown
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
                throw new \Exception('Stopping');
                // run related teardown
            }
        }
        return $results;
    }

    public function runTeardown()
    {
        $results = [];

        foreach ($this->getTeardownSteps() as $teardownStep) {
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
        $results = $this->runSetup();
        $results = \array_merge($results, $this->runChain());
        $results = \array_merge($results, $this->runTeardown());
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
