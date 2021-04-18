<?php

namespace App\Service;

use App\Facade;
use App\Model\SshInfo;
use App\Service\ValidatorInterface;

class DatabaseSync
{
    protected static $validator;

    public function __construct()
    {
        static::$validator = Facade::create('validator');
    }

    public static function parseSshInfo($host, $user = '', $port = '', $identity = ''): SshInfo
    {
        $model = new SshInfo();
        $model->setHost($host)
            ->setUser($user)
            ->setPort($port)
            ->setIdentity($identity);
        if (static::validate($model)) {
            return $model;
        }
    }
    private static function validate($model): bool
    {
        $errors = static::$validator->validate($model);
        if (count($errors)) {
            $errorString = (string) $errors;
            throw new \Exception($errorString);
        }
        return true;
    }
    public function dumpDatabase(SshInfo $ssh, DatabaseInfo $db)
    {

    }
}
