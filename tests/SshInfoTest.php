<?php

namespace Test;

use unionco\syncdbModel\SshInfo;
use unionco\syncdbService\DatabaseSync;
use PHPUnit\Framework\TestCase;

class SshInfoTest extends TestCase
{
    public function __construct()
    {
        parent::__construct();
        // $this->dbSync = new DatabaseSync();
    }

    public function testEmptyHost(): void
    {
        $model = DatabaseSync::parseSshInfo('');
        $this->expectException(DatabaseSync::validate($model));
    }
}
