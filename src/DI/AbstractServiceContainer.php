<?php

namespace ProklUng\ContainerBoilerplate\DI;

use Closure;
use Exception;
use LogicException;
use ProklUng\ContainerBoilerplate\CompilerContainer;
use ProklUng\ContainerBoilerplate\Utils\BitrixSettingsDiAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class AbstractServiceContainer
 * @package ProklUng\ContainerBoilerplate\DI
 *
 * @since 16.07.2021
 */
abstract class AbstractServiceContainer
{
    /**
     * @var ContainerBuilder|null $container Контейнер.
     */
    protected static $container;

    /**
     * @var array $config Битриксовая конфигурация.
     */
    protected $config = [];

    /**
     * @var array $parameters Параметры битриксового сервис-локатора.
     */
    protected $parameters = [];

    /**
     * @var array $services Сервисы битриксового сервис-локатора.
     */
    protected $services = [];

    /**
     * @var string $environment Окружение.
     */
    protected $environment;

    /**
     * @var boolean $debug Режим отладки.
     */
    protected $debug;

    /**
     * @var string $moduleId ID модуля (переопределяется наследником).
     */
    protected $moduleId = '';

    /**
     * Services constructor.
     */
    public function __construct()
    {
        $this->debug = !array_key_exists('DEBUG', $_ENV) ? true : (bool)$_ENV['DEBUG'];
        $this->environment = $this->debug ? 'dev' : 'prod';
    }

    /**
     * Инициализация контейнера.
     *
     * @return void
     * @throws Exception
     */
    abstract public function initContainer() : void;

    /**
     * Загрузка всего хозяйства.
     *
     * @return void
     * @throws Exception | LogicException
     */
    public function load() : void
    {
        if (static::$container !== null) {
            return;
        }

        if (!$this->moduleId) {
            throw new LogicException('Children of AbstractServiceContainer must define moduleId property.');
        }

        $this->createContainer();
        $compilerContainer = new CompilerContainer($_SERVER['DOCUMENT_ROOT'], $this->moduleId);

        // Кэшировать контейнер?
        if (!in_array($this->environment, $this->parameters['compile_container_envs'], true)) {
            $this->initContainer();
            return;
        }

        static::$container = $compilerContainer->cacheContainer(
            static::$container,
            $_SERVER['DOCUMENT_ROOT'] . $this->parameters['cache_path'],
            'container.php',
            $this->environment,
            $this->debug,
            Closure::fromCallable([$this, 'initContainer'])
        );
    }

    /**
     * Загрузка и инициализация контейнера.
     *
     * @return Container
     * @throws Exception
     */
    public static function boot() : Container
    {
        $self = new static();

        $self->load();

        return $self->getContainer();
    }

    /**
     * Alias boot для читаемости.
     *
     * @return Container
     * @throws Exception
     */
    public static function getInstance() : Container
    {
        return static::boot();
    }

    /**
     * Экземпляр контейнера.
     *
     * @return Container|null
     * @throws Exception
     */
    public function getContainer(): ?Container
    {
        return static::$container;
    }

    /**
     * Фасад над методом get контейнера.
     *
     * @param string $serviceId ID сервиса.
     *
     * @return mixed
     * @throws Exception
     */
    public static function get(string $serviceId)
    {
        return static::boot()->get($serviceId);
    }

    /**
     * Фасад над методом has контейнера.
     *
     * @param string $serviceId ID сервиса.
     *
     * @return boolean
     * @throws Exception
     */
    public static function has(string $serviceId) : bool
    {
        return static::boot()->has($serviceId);
    }

    /**
     * Фасад над методом getParameter контейнера.
     *
     * @param string $param Параметр.
     *
     * @return mixed
     * @throws Exception
     */
    public static function getParameter(string $param)
    {
        return static::boot()->getParameter($param);
    }

    /**
     * Фасад над методом hasParameter контейнера.
     *
     * @param string $param Параметр.
     *
     * @return mixed
     * @throws Exception
     */
    public static function hasParameter(string $param)
    {
        return static::boot()->hasParameter($param);
    }

    /**
     * Создать пустой экземпляр контейнера.
     *
     * @return void
     */
    private function createContainer() : void
    {
        static::$container = new ContainerBuilder();
        $adapter = new BitrixSettingsDiAdapter();

        $adapter->importParameters(static::$container, $this->config);
        $adapter->importParameters(static::$container, $this->parameters);
        $adapter->importServices(static::$container, $this->services);
    }
}
