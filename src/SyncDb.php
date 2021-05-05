<?php

namespace unionco\syncdb;
use unionco\syncdb\Service\DatabaseSync;

class SyncDb
{
    public $service;

    public function __construct()
    {
        $this->init();
    }

    protected function init(): void
    {
        $this->service = new DatabaseSync();
    }
}
