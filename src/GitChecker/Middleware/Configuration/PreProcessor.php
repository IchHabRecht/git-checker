<?php
namespace IchHabRecht\GitChecker\Middleware\Configuration;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PreProcessor
{
    /**
     * @var string
     */
    protected $configurationAttribute;

    /**
     * @param string $configurationAttribute
     */
    public function __construct($configurationAttribute)
    {
        $this->configurationAttribute = $configurationAttribute;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (!$next) {
            return $response;
        }

        $configuration = $request->getAttribute($this->configurationAttribute);
        if (!empty($configuration) && is_array($configuration)) {
            $route = $request->getAttribute('route');

            $virtualHost = rtrim($route->getArgument('virtualHost'), '/\\');

            $processor = new \IchHabRecht\GitChecker\Config\Processor($configuration);
            $processor->combine('virtual-host', 'default', $virtualHost);
            $request = $request->withAttribute($this->configurationAttribute, $processor->getConfiguration());
        }

        return $next($request, $response);
    }

}
