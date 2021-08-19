<?php

namespace ProklUng\ContainerBoilerplate\DI;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class LoaderBundles
 *
 * @since 19.08.2021
 */
class LoaderBundles
{
    /**
     * @var array $bundles
     */
    private $bundles = [];

    /**
     * @var ContainerBuilder $containerBuilder Целевой контейнер.
     */
    private $containerBuilder;

    /**
     * @var string $environment Окружение.
     */
    private $environment;

    /**
     * @param ContainerBuilder $containerBuilder Целевой контейнер.
     * @param string           $environment      Окружение.
     */
    public function __construct(
        ContainerBuilder $containerBuilder,
        string $environment
    ) {
        $this->containerBuilder = $containerBuilder;
        $this->environment = $environment;
    }

    /**
     * Загрузка из файла.
     *
     * @param string $configPath Путь к файлу с конфигурацией бандлов.
     *
     * @return array
     * @throws RuntimeException
     */
    public function fromFile(string $configPath) : array
    {
        $bundles = $this->loadConfig($configPath);

        return $this->loadBundles($bundles);
    }

    /**
     * Загрузка из файла.
     *
     * @param array $config Конфиг в виде массива.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function fromArray(array $config) : array
    {
        return $this->loadBundles($config);
    }

    /**
     * Загрузка конфига бандлов из файла.
     *
     * @param string $configPath Путь к файлу с конфигурацией бандлов.
     *
     * @return array
     * @throws RuntimeException
     */
    private function loadConfig(string $configPath) : array
    {
        if (!file_exists($configPath)) {
            throw new RuntimeException('Config bundles file' .  $configPath . ' not exists.');
        }

        $this->bundles = (array)require $configPath;

        return $this->bundles;
    }

    /**
     * Загрузка и инициализация бандлов.
     *
     * @param array $config Массив с конфигом бандлов.
     *
     * @return array
     * @throws InvalidArgumentException
     */
    private function loadBundles(array $config) : array
    {
        $resultBundles = [];

        foreach ($config as $bundleClass => $envs) {
            if (!class_exists($bundleClass)) {
                throw new InvalidArgumentException(
                    sprintf('Bundle class %s not exist.', $bundleClass)
                );
            }

            if (!array_key_exists($this->environment, (array)$envs)
                &&
                !array_key_exists('all', (array)$envs)
            ) {
                continue;
            }

            if (!method_exists($bundleClass, 'getContainerExtension')) {
                throw new InvalidArgumentException(
                    sprintf('Bundle %s dont have implemented getContainerExtension method.', $bundleClass)
                );
            }

            /**
             * @var Bundle $bundle Бандл.
             */
            $bundle = new $bundleClass;

            if ((bool)$_ENV['DEBUG'] === true) {
                $this->containerBuilder->addObjectResource($bundle);
            }

            $extension = $bundle->getContainerExtension();
            if ($extension !== null) {
                $this->containerBuilder->registerExtension($extension);
                $bundle->build($this->containerBuilder);
            }

            $resultBundles[static::class][$bundle->getName()] = $bundle;
        }

        return $resultBundles;
    }
}
