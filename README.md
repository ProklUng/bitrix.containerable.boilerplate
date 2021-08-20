# Boilerplate для работы с контейнерами Symfony в модулях Битрикс

***INTERNAL***

### Установка

composer.json:

```json
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/proklung/bitrix.containerable.boilerplate"
        }
    ]
```

```bash
composer require proklung/bitrix-containerable-boilerplate
```

### Прочее

#### Как загружать бандлы

1) Из файла:
   
Конфигурационный файл как в Symfony:

```php
return [
    Prokl\MyBundle\MyBundle::class => ['all' => true],
]
```

```php
 $bundlesConfigFile = __DIR_. '../../config/bundles.php'
 
 //...
 // Важно - перед загрузкой сервисов!
  $loaderBundles = new LoaderBundles(
      static::$container,
      $this->environment
 );

 $loaderBundles->fromFile($bundlesConfigFile);
```

2) Из секции `bundles` целевого модуля:
```php
 use Bitrix\Main\Config\Configuration;
 //...
 $this->config = Configuration::getInstance()->get('my.module') ?? ['my.module' => []];
 $this->bundles = $this->config['bundles'] ?? [];
 
 //...
 // Важно - перед загрузкой сервисов!
  $loaderBundles = new LoaderBundles(
      static::$container,
      $this->environment
 );

 $loaderBundles->fromArray($this->bundles);
```