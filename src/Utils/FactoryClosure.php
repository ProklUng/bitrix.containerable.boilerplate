<?php

namespace ProklUng\ContainerBoilerplate\Utils;

use Closure;

/**
 * Class FactoryClosure
 * @package ProklUng\ContainerBoilerplate\Utils
 *
 * @since 13.07.2021
 */
class FactoryClosure
{
    /**
     * @param Closure $closure Closure.
     *
     * @return mixed
     */
    public function from(Closure $closure)
    {
        return $closure();
    }
}