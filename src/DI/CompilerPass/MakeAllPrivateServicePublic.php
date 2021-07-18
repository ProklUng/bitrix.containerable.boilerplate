<?php

namespace ProklUng\ContainerBoilerplate\DI\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class MakePrivateServicePublic
 * Сделать все сервисы публичными.
 * @package Prokl\ServiceProvider\CompilePasses
 *
 * @since 18.07.2021
 */
final class MakeAllPrivateServicePublic implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container) : void
    {
        $services = $container->getServiceIds();

        foreach ($services as $id => $service) {
            if (!$container->hasDefinition($service)) {
                continue;
            }

            $def = $container->getDefinition($service);
            $def->setPublic(true);
        }
    }
}
