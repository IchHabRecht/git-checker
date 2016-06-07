<?php
namespace IchHabRecht\GitChecker\Tests\Config;

use IchHabRecht\GitChecker\Config\ArrayHandler;

class ArrayHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return array
     */
    public function mergeReturnsExceptedResultDataProvider()
    {
        return [
            'Simple array' => [
                [
                    'foo' => [
                        'default' => [
                            'bar' => 'default',
                        ],
                        'baz' => [
                            'bar' => 'baz',
                        ],
                    ],
                ],
                'foo',
                [
                    'default',
                    'baz',
                ],
                [
                    'foo' => [
                        'bar' => 'baz',
                    ],
                ],
            ],
            'Preserve default' => [
                [
                    'foo' => [
                        'default' => [
                            'foobar' => 'default',
                            'foobaz' => 'Hello world!',
                        ],
                        'baz' => [
                            'foobar' => 'baz',
                        ],
                    ],
                ],
                'foo',
                [
                    'default',
                    'baz',
                ],
                [
                    'foo' => [
                        'foobar' => 'baz',
                        'foobaz' => 'Hello world!',
                    ],
                ],
            ],
            'Complex array' => [
                [
                    'foo' => [
                        'bar' => [
                            'baz' => [
                                'default' => [
                                    'preserved' => 42,
                                    'overlay' => 'default',
                                ],
                                'foobar' => [
                                    'overlay' => 'foobar',
                                    'added' => 'Hello world!',
                                ],
                                'foobaz' => [
                                    'overlay' => 'foobaz',
                                ],
                            ],
                        ],
                    ],
                ],
                'foo/bar/baz',
                [
                    'default',
                    'foobar',
                    'foobaz',
                ],
                [
                    'foo' => [
                        'bar' => [
                            'baz' => [
                                'preserved' => 42,
                                'overlay' => 'foobaz',
                                'added' => 'Hello world!',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array $object
     * @param string $path
     * @param array $keys
     * @param array $expected
     * @dataProvider mergeReturnsExceptedResultDataProvider
     */
    public function testMergeReturnsExceptedResult($object, $path, $keys, $expected)
    {
        $arrayHandler = new ArrayHandler();

        $this->assertSame($expected, $arrayHandler->merge($object, $path, $keys));
    }
}
