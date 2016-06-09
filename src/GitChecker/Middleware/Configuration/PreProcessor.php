<?php
namespace IchHabRecht\GitChecker\Middleware\Configuration;

use IchHabRecht\Filesystem\Filepath;
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
            $filepath = new Filepath();
            $route = $request->getAttribute('route');

            if (empty($configuration['root'])) {
                throw new \RuntimeException('Please configure "root" configuration in your settings.yml', 1465501654);
            }

            $root = $configuration['root'];
            $virtualHost = (string)$route->getArgument('virtualHost');
            $repository = (string)$route->getArgument('repository');

            $processor = new Processor($configuration);
            $processor->combine('virtual-host', 'default', $virtualHost);

            $request = $request
                ->withAttribute($this->configurationAttribute, $processor->getConfiguration())
                ->withAttribute('rootPath', $root)
                ->withAttribute('virtualHostPath', $virtualHost)
                ->withAttribute('absoluteVirtualHostPath', $filepath->concatenate($root, $virtualHost))
                ->withAttribute('repositoryPath', $repository)
                ->withAttribute('absoluteRepositoryPath', $filepath->concatenate($root, $virtualHost, $repository));
        }

        return $next($request, $response);
    }

}
