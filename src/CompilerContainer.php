<?php

namespace ProklUng\ContainerBoilerplate;

use InvalidArgumentException;
use ProklUng\ContainerBoilerplate\Resource\FileBitrixSettingsResource;
use Symfony\Bridge\ProxyManager\LazyProxy\PhpDumper\ProxyDumper;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Class CompilerContainer
 * @package Proklung\Redis\DI
 *
 * @since 14.07.2021
 *
 * @internal Файлы .meta -> .gitignore.
 */
class CompilerContainer
{
    /**
     * @var string $projectRoot DOCUMENT_ROOT.
     */
    private $projectRoot;

    /**
     * @var string $moduleId ID модуля.
     */
    private $moduleId = '';

    /**
     * @var Filesystem $filesystem Файловая система.
     */
    private $filesystem;

    /**
     * CompilerContainer constructor.
     *
     * @param string $projectRoot DOCUMENT_ROOT.
     * @param string $moduleId    ID модуля.
     */
    public function __construct(string $projectRoot, string $moduleId)
    {
        $this->projectRoot = $projectRoot;
        $this->moduleId = $moduleId;

        $this->filesystem = new Filesystem();
    }

    /**
     * Свежий основной конфиг Битрикса или нет.
     *
     * @param string $configFile Конфигурационный файл.
     *
     * @return boolean
     */
    public function isConfigFresh(string $configFile = '/bitrix/.settings.php') : bool
    {
        $meta = $configFile . '.meta';

        // Не существует мета-файл = конфиг потенциально не свежий.
        if (!$this->filesystem->exists($this->projectRoot . $meta)) {
            return false;
        }

        $content = file_get_contents($this->projectRoot . $meta);

        /** @var FileBitrixSettingsResource $checker */
        $checker = unserialize($content);
        // Кривизна в мета-файле = конфиг потенциально не свежий.
        if ($checker === false) {
            return false;
        }

        $timestamp = filemtime($this->projectRoot . $configFile);

        return $checker->isFresh($timestamp);
    }

    /**
     * Записать мета-информацию на основной конфиг битрикса.
     *
     * @param string $configFile Конфиг.
     *
     * @return void
     */
    public function createConfigMeta(string $configFile = '/bitrix/.settings.php') : void
    {
        $checker = new FileBitrixSettingsResource($this->projectRoot . $configFile);

        @file_put_contents($this->projectRoot . $configFile . '.meta', serialize($checker));
    }

    /**
     * @param ContainerBuilder $container            Контейнер.
     * @param string           $cacheDirectory       Директория кэша.
     * @param string           $filename             Файл кэша.
     * @param string           $environment          Окружение.
     * @param boolean          $debug                Режим отладки.
     * @param callable         $initializerContainer Инициализатор контейнера.
     *
     * @return Container
     */
    public function cacheContainer(
        ContainerBuilder $container,
        string $cacheDirectory,
        string $filename,
        string $environment,
        bool $debug,
        callable $initializerContainer
    ) : Container {
        $this->createCacheDirectory($cacheDirectory);

        // По clear_cache перестраивать контейнер.
        if (isset($_GET['clear_cache']) && strtolower($_GET['clear_cache']) === 'y') {
            $this->filesystem->remove($cacheDirectory);
        }

        $compiledContainerFile = $cacheDirectory . '/' . $filename;

        $containerConfigCache = new ConfigCache($compiledContainerFile, true);

        // Класс скомпилированного контейнера.
        $classCompiledContainerName = $this->getContainerClass($environment, $debug) . md5($filename);

        $hasContainerFresh = $containerConfigCache->isFresh();

        $configsBag = [
            '/bitrix/.settings.php',
            '/bitrix/.settings_extra.php',
            $this->locateModuleDir($this->moduleId).'/.settings.php', // Конфиг модуля
        ];

        foreach ($configsBag as $configFile) {
            // Если конфиг-файл изменился - пересобрать дамп контейнера.
            if ($this->filesystem->exists($this->projectRoot . $configFile) && !$this->isConfigFresh($configFile)) {
                $this->createConfigMeta($configFile);
                $hasContainerFresh = false;
            }
        }

        if (!$hasContainerFresh) {
            // Загрузить, инициализировать и скомпилировать контейнер.
            $newContainer = $initializerContainer();

            // Блокировка на предмет конкурентных запросов.
            $lockFile = $cacheDirectory . '/container.lock';

            // Silence E_WARNING to ignore "include" failures - don't use "@" to prevent silencing fatal errors
            $errorLevel = error_reporting(\E_ALL ^ \E_WARNING);

            $lock = false;
            try {
                if ($lock = fopen($lockFile, 'w')) {
                    flock($lock, \LOCK_EX | \LOCK_NB, $wouldBlock);
                    if (!flock($lock, $wouldBlock ? \LOCK_SH : \LOCK_EX)) {
                        fclose($lock);
                        @unlink($lockFile);
                        $lock = null;
                    }
                } else {
                    // Если в файл контейнера уже что-то пишется, то вернем свежую копию контейнера.
                    flock($lock, \LOCK_UN);
                    fclose($lock);
                    @unlink($lockFile);

                    return $newContainer;
                }
            } catch (Throwable $e) {
            } finally {
                error_reporting($errorLevel);
            }

            $this->dumpContainer($containerConfigCache, $container, $classCompiledContainerName, $debug);

            if ($lock) {
                flock($lock, \LOCK_UN);
                fclose($lock);
                @unlink($lockFile);
            }
        }

        // Подключение скомпилированного контейнера.
        /** @noinspection PhpIncludeInspection */
        require_once $compiledContainerFile;

        $classCompiledContainerName = '\\'.$classCompiledContainerName;
        /** @psalm-suppress LessSpecificReturnStatement */
        return new $classCompiledContainerName();
    }

    /**
     * Если надо создать директорию для компилированного контейнера.
     *
     * @param string $dir Директория.
     *
     * @return void
     */
    private function createCacheDirectory(string $dir) : void
    {
        if (!$this->filesystem->exists($dir)) {
            $this->filesystem->mkdir($dir);
        }
    }

    /**
     * Класс контейнера.
     *
     * @param string  $env   Окружение.
     * @param boolean $debug Режим отладки.
     *
     * @return string The container class.
     */
    private function getContainerClass(string $env, bool $debug) : string
    {
        $class = static::class;
        $class = false !== strpos($class, "@anonymous\0") ? get_parent_class($class).str_replace('.', '_', ContainerBuilder::hash($class))
            : $class;
        $class = str_replace('\\', '_', $class).ucfirst($env).($debug ? 'Debug' : '').'Container';

        if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $class)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The environment "%s" contains invalid characters, it can only contain characters allowed in PHP class names.',
                    $env
                )
            );
        }

        return $class;
    }

    /**
     * Dumps the service container to PHP code in the cache.
     *
     * @param ConfigCache      $cache     Кэш.
     * @param ContainerBuilder $container Контейнер.
     * @param string           $class     The name of the class to generate.
     * @param boolean          $debug     Отладка.
     *
     * @return void
     */
    private function dumpContainer(ConfigCache $cache, ContainerBuilder $container, string $class, bool $debug) : void
    {
        // Опция - дампить как файлы. По умолчанию - нет.
        $asFiles = false;
        if ($container->hasParameter('container.dumper.inline_factories')) {
            $asFiles = $container->getParameter('container.dumper.inline_factories');
        }

        $dumper = new PhpDumper($container);
        /** @psalm-suppress UndefinedClass */
        if (class_exists(\ProxyManager\Configuration::class) && class_exists(ProxyDumper::class)) {
            /** @psalm-suppress InvalidArgument */
            $dumper->setProxyDumper(new ProxyDumper());
        }

        $content = $dumper->dump(
            [
                'class' => $class,
                'file' => $cache->getPath(),
                'as_files' => $asFiles,
                'debug' => $debug,
                'build_time' => $container->hasParameter('kernel.container_build_time')
                    ? $container->getParameter('kernel.container_build_time') : time(),
                'preload_classes' => [],
            ]
        );

        // Если as_files = true.
        if (is_array($content)) {
            $rootCode = array_pop($content);
            $dir = \dirname($cache->getPath()).'/';

            $filesystem = new Filesystem();

            foreach ($content as $file => $code) {
                $filesystem->dumpFile($dir.$file, $code);
                @chmod($dir.$file, 0666 & ~umask());
            }

            $legacyFile = \dirname($dir.key($content)).'.legacy';
            if (is_file($legacyFile)) {
                @unlink($legacyFile);
            }

            $content = $rootCode;
        }

        $cache->write(
            $content, // @phpstan-ignore-line
            $container->getResources()
        );
    }

    /**
     * Определить, где лежит модуль - в local или bitrix.
     *
     * @param string $moduleId   ID модуля.
     * @param string $baseFolder Базовая папка.
     *
     * @return string
     */
    private function locateModuleDir(string $moduleId, $baseFolder = '/bitrix/modules') : string
    {
        $hasLocalDir = is_dir($this->projectRoot . '/local/modules/' . $moduleId);

        if ($hasLocalDir && $this->filesystem->exists($this->projectRoot . '/local/modules/' . $moduleId)) {
            return '/local/modules/' . $this->projectRoot;
        }
        elseif ($this->filesystem->exists($this->projectRoot. $baseFolder . '/' . $moduleId))
        {
            return $baseFolder . '/' . $moduleId;
        }

        return '';
    }
}