<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportDatabase extends Command
{
    protected static $defaultName = 'db:import';
    protected $databaseSync;

    public function __construct(DatabaseSync $databaseSync)
    {
        $this->databaseSync = $databaseSync;
        parent::__construct();
    }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {

    }

    public function configure(InputInterface $input, OutputInterface $output): void
    {

    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        return self::SUCCESS;
    }

}
