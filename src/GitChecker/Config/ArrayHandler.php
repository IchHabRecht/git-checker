<?php
namespace IchHabRecht\GitChecker\Config;

class ArrayHandler implements HandlerInterface
{
    /**
     * @param mixed $object
     * @param string $path
     * @param array $keys
     * @return mixed
     */
    public function merge($object, $path, array $keys)
    {
        if (!is_array($object)) {
            throw new \RuntimeException('Object must be an array', 1464593895);
        }
        if (empty($path)) {
            throw new \RuntimeException('Path must not be empty', 1464593900);
        }

        $currentValue = $this->getValueByPath($object, $path);
        $value = [];
        foreach ($keys as $segment) {
            if (array_key_exists($segment, $currentValue)) {
                $value = array_replace($value, (array)$currentValue[$segment]);
            }
        }

        return $this->setValueByPath($object, $path, $value);
    }

    /**
     * @param mixed $object
     * @param string $path
     * @return mixed
     * @throws \RuntimeException
     */
    protected function getValueByPath($object, $path)
    {
        if (!is_array($object)) {
            throw new \RuntimeException('Object must be an array', 1464593167);
        }
        if (empty($path)) {
            throw new \RuntimeException('Path must not be empty', 1464593182);
        }
        if (!is_string($path)) {
            throw new \RuntimeException('Path must be a string', 1464594044);
        }

        $pathArray = str_getcsv($path, '/');
        $value = $object;
        foreach ($pathArray as $segment) {
            if (empty($segment)) {
                throw new \RuntimeException(
                    sprintf('Invalid path segment "%s" specified in %s', $segment, $path),
                    1464594060
                );
            }
            if (array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                throw new \RuntimeException(
                    sprintf('Path $s does not exist in array', $path),
                    1464593214
                );
            }
        }

        return $value;
    }

    /**
     * @param mixed $object
     * @param string $path
     * @param mixed $value
     * @return mixed
     * @throws \RuntimeException
     */
    protected function setValueByPath($object, $path, $value)
    {
        if (!is_array($object)) {
            throw new \RuntimeException('Object must be an array', 1464594196);
        }
        if (empty($path)) {
            throw new \RuntimeException('Path must not be empty', 1464594202);
        }
        if (!is_string($path)) {
            throw new \RuntimeException('Path must be a string', 1464594213);
        }

        $pathArray = str_getcsv($path, '/');
        $pointer = &$object;
        foreach ($pathArray as $segment) {
            if (empty($segment)) {
                throw new \RuntimeException(
                    sprintf('Invalid path segment "%s" specified in %s', $segment, $path),
                    1464594255785
                );
            }
            if (!array_key_exists($segment, $pointer)) {
                $pointer[$segment] = [];
            }
            $pointer = &$pointer[$segment];
        }
        $pointer = $value;

        return $object;
    }
}