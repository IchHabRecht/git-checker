<?php
namespace IchHabRecht\GitChecker\Config;

class Processor
{
    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var HandlerInterface
     */
    private $handler;

    /**
     * @param array $configuration
     * @param HandlerInterface $handler
     */
    public function __construct(array $configuration, HandlerInterface $handler = null)
    {
        $this->configuration = $configuration;
        $this->handler = $handler !== null ? $handler : new ArrayHandler();
    }

    /**
     * First argument has to be the path to the array which should be merged
     *
     * @param string $path
     * @param string $default
     * @return $this|Processor
     */
    public function combine($path, $default)
    {
        $arguments = func_get_args();
        array_shift($arguments);
        $this->configuration = $this->handler->merge($this->configuration, $path, $arguments);

        return $this;
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }
}
