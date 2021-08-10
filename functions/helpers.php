<?php

// Compability with Symfony 4.x
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\EnvConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\InlineServiceConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

if (!function_exists('service')) {
    /**
     * Creates a reference to a service.
     *
     * @param string $serviceId Service ID.
     *
     * @return ReferenceConfigurator
     */
    function service(string $serviceId): ReferenceConfigurator
    {
        return new ReferenceConfigurator($serviceId);
    }
}

if (!function_exists('inline_service')) {
    /**
     * Creates an inline service.
     */
    function inline_service(string $class = null): InlineServiceConfigurator
    {
        return new InlineServiceConfigurator(new Definition($class));
    }
}

if (!function_exists('abstract_arg')) {
    /**
     * Creates an abstract argument.
     */
    function abstract_arg(string $description): AbstractArgument
    {
        return new AbstractArgument($description);
    }
}

if (!function_exists('env')) {
    /**
     * Creates an environment variable reference.
     */
    function env(string $name): EnvConfigurator
    {
        return new EnvConfigurator($name);
    }
}