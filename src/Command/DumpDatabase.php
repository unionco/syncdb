<?php

namespace App\Command;

use App\Facade;
use App\Service\DatabaseSync;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpDatabase extends Command
{
    protected static $defaultName = 'db:dump';
    protected $databaseSync;

    // public function __construct()
    // {
    //     // $this->databaseSync = $databaseSync;
    //     parent::__construct();
    // }

    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->databaseSync = Facade::create('app.dbSync');
    }

    public function configure(): void
    {

    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $u = 'abry';
        $h = '192.168.1.22';
        $p = '22';
        $i = '/Users/abry/.ssh/id_rsa';

        $sshInfo = $this->databaseSync->parseSshInfo($u, $h, $p, $i);
        var_dump($sshInfo);
        return self::SUCCESS;
    }

}
