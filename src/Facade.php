<?php

namespace App;

// use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ValidatorBuilder;

class Facade
{
    /** @var self|null */
    protected static $instance;

    /** @var ContainerInterface|null */
    protected static $container;

    private function __construct(ContainerInterface $container)
    {
        self::$container = $container;
    }

    /**
     * @param string $serviceId
     * @return object|null
     */
    public static function create($serviceId)
    {
        if (self::$instance === null) {
            throw new \Exception('Facade is not instantiated');
        }
        if ($serviceId === 'validator') {
        }
        return self::$container->get($serviceId);
    }

    public static function init(ContainerInterface $container)
    {
        if (self::$instance === null) {
            self::$instance = new self($container);
        }
        $validator = Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();
        self::$container->set('validator', $validator);

        return self::$instance;
    }
}
