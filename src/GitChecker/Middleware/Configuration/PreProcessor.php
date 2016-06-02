<?php
namespace IchHabRecht\GitChecker\Middleware\Configuration;

use IchHabRecht\GitChecker\Config\Processor;
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

            $root = (!empty($configuration['root']) ? rtrim($configuration['root'], '/\\'): '');
            $absoluteRoot = $root . DIRECTORY_SEPARATOR;
            $virtualHost = rtrim($route->getArgument('virtualHost'), '/\\');
            $absoluteVirtualHost = $absoluteRoot . $virtualHost . DIRECTORY_SEPARATOR;
            $repository = rtrim($route->getArgument('repository'), '/\\');
            $absoluteRepository = $absoluteVirtualHost . $repository . DIRECTORY_SEPARATOR;

            $processor = new Processor($configuration);
            $processor->combine('virtual-host', 'default', $virtualHost);

            $request = $request
                ->withAttribute($this->configurationAttribute, $processor->getConfiguration())
                ->withAttribute('rootPath', $root)
                ->withAttribute('absoluteRootPath', $absoluteRoot)
                ->withAttribute('virtualHostPath', $virtualHost)
                ->withAttribute('absoluteVirtualHostPath', $absoluteVirtualHost)
                ->withAttribute('repositoryPath', $repository)
                ->withAttribute('absoluteRepositoryPath', $absoluteRepository);
        }

        return $next($request, $response);
    }

}
