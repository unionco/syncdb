<?php

namespace unionco\syncdb\Model;

use unionco\syncdb\Model\SetupStep;
use unionco\syncdb\Model\ScenarioStep;
use unionco\syncdb\Model\TeardownStep;

class Scenario
{
    protected $name;
    /** @var ScenarioStep[] */
    protected $setupSteps = [];
    /** @var SetupStep[] */
    protected $chainSteps = [];
    /** @var TeardownStep[] */
    protected $teardownSteps = [];

    protected $sshContext = null;

    public function __construct($name, $sshContext)
    {
        $this->name = $name;
        $this->sshContext = $sshContext;
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

    public function compile()
    {
        // foreach ($this->getChainSteps() as $chainStep)
        // {
        //     if ($setup = $chainStep->getSetupSteps()) {
        //         foreach ($setup as $step) {
        //             $this->addSetupStep($step);
        //         }
        //     }
        //     if ($teardown = $chainStep->getTeardownSteps()) {
        //         foreach ($teardown as $step) {
        //             $this->addTeardownStep($step);
        //         }
        //     }
        // }
    }

    public function preview()
    {
        // $this->compile();

        $output = '> setup steps' . PHP_EOL;
        foreach ($this->getSetupSteps() as $setupStep)
        {
            $output .= "\t{$setupStep->id}|\t{$setupStep->getName()}|\t{$setupStep->getCommandString($this->sshContext)}\n";
        }
        $output .= '> chain steps' . PHP_EOL;
        foreach ($this->getChainSteps() as $chainStep) {
            $output .= "\t{$chainStep->id}|\t{$chainStep->getName()}|\t{$chainStep->getCommandString($this->sshContext)}\n";
        }

        $output .= '> teardown steps' . PHP_EOL;
        foreach ($this->getTeardownSteps() as $teardownStep) {
            $output .= "\t{$teardownStep->id} -> {$teardownStep->relatedId}|\t{$teardownStep->getName()}\t{$teardownStep->getCommandString($this->sshContext)}\n";
        }
        return $output;
    }
}
