<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

class DatabaseInfo
{
    /** @Assert\NotBlank */
    protected $user;
    /** @Assert\NotBlank */
    protected $pass;
    /** @Assert\NotBlank */
    protected $host;
    /** @Assert\NotBlank */
    protected $name;
    protected $ignoreTables;
    protected $args;
}
