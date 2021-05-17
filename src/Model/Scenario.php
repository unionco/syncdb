<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\ChainStep;
use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\SshInfo;
use unionco\syncdb\Model\TeardownStep;
use unionco\syncdb\Service\DatabaseSync;
use unionco\syncdb\Service\Logger;
use unionco\syncdb\SyncDb;

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

    public function __construct(string $name = '')
    {
        $this->name = $name;
        static::$dbSync = SyncDb::$container->get('dbSync');
    }

    /**
     * Run the scenario
     */
    public function run(): array
    {
        $results = [];

        try {
            $results = $this->runSection('setup');
            $results = $this->runSection('chain', $results);
            $results = $this->runSection('teardown', $results);
        } catch (\Exception $e) {
            $results = $this->runSection('teardown', $results);
        }
        return $results;
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
     * @psalm-param 'setup'|'chain'|'teardown' $section
     * @param array<array{stage:string,id:int,name:string,command:string,result:false|string,relatedId?:int}>
     * @return array<array{stage:string,id:int,name:string,command:string,result:false|string,relatedId?:int}>
     * @throws \Exception
     */
    private function runSection(string $section, array $results = [])
    {
        /** @var Logger */
        $log = SyncDb::$container->get('log');

        /** @var Step[] $steps */
        $steps = [];

        // Determine the steps to run
        switch ($section) {
            case 'setup':
                $steps = $this->getSetupSteps();
                break;
            case 'chain':
                $steps = $this->getChainSteps();
                break;
            case 'teardown':
                $steps = $this->getTeardownSteps();
                if ($this->undo) {
                    $log->debug('Tearing down specific IDs: ' . join(', ', $this->undo));
                    $undoIds = $this->undo;
                    $steps = \array_filter($steps, function (TeardownStep $step) use ($undoIds): bool {
                        return \in_array($step->getRelatedId(), $undoIds);
                    });
                } else {
                    $log->debug('Tearing down all steps');
                }
            default:
                throw new \Exception('Invalid section handle: ' . $section);
        }

        $log->info("Starting {$section} steps");
        foreach ($steps as $step) {
            /** @var SshInfo|null */
            $ssh = $this->getSshContext();
            if (!$ssh) {
                $log->error('SSH Context is null');
                throw new \Exception('SSH Context is null');
            }

            /** @var string */
            $cmd = $step->getCommandString($ssh);
            $log->debug('Step::getCommandString($ssh) -> ' . $cmd);

            /** @var string */
            $cmdOutput = '';
            if ($step->getRemote()) {
                $cmdOuput = static::$dbSync->runRemote($ssh, $step);
            } else {
                $cmdOutput = static::$dbSync->runLocal($step);
            }

            if ($cmdOutput === false) {
                $log->error('Command failed: ' . $cmd);
                if ($section === 'setup' || $section === 'chain') {
                    $undoIds = \array_map(function (array $result): int {
                        return $result['id'];
                    }, $results);
                    $this->undo = \array_merge($this->undo, $undoIds);
                }
                throw new \Exception('Command failed - stopping');
            }

            $result = [
                'stage' => $section,
                'id' => $step->getId(),
                'name' => $step->getName(),
                'command' => $cmd,
                'result' => $cmdOutput,
            ];
            if ($section === 'teardown') {
                $result['relatedId'] = $step->getRelatedId();
            }
            $results[] = $result;
        }
        $log->info("Finished {$section} steps");
        return $results;
    }

    /**
     * Get the value of sshContext
     * @return SshInfo|null
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
    public function setSshContext(SshInfo $sshContext)
    {
        $this->sshContext = $sshContext;

        return $this;
    }
}
