<?php
namespace GitChecker\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Symfony\Component\Yaml\Parser;

class YamlParser
{
    /**
     * @var string
     */
    protected $attributeName = 'yaml';

    /**
     * @var string
     */
    protected $yamlFile;

    /**
     * @var Parser
     */
    protected $yamlParser;

    /**
     * @param string $yamlFile
     * @param string $attributeName
     * @param Parser|null $yamlParser
     */
    public function __construct($yamlFile, $attributeName = null, Parser $yamlParser = null)
    {
        $this->yamlFile = $yamlFile;
        if ($attributeName) {
            $this->attributeName = $attributeName;
        }
        $this->yamlParser = $yamlParser ?: new Parser();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function __invoke(Request $request, Response $response, callable $next)
    {
        $yaml = $this->yamlParser->parse(file_get_contents($this->yamlFile));
        $request = $request->withAttribute($this->attributeName, $yaml);

        return $next($request, $response);
    }

}
