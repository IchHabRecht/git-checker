<?php
namespace IchHabRecht\GitChecker\Config;

interface HandlerInterface
{
    /**
     * @param mixed $object
     * @param $path
     * @param array $keys
     * @return mixed
     */
    public function merge($object, $path, array $keys);
}
