<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Tests\Unit\Routing\Enhancer;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Routing\Enhancer\VariableProcessor;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class VariableProcessorTest extends UnitTestCase
{
    /**
     * @var VariableProcessor
     */
    protected $subject;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = new VariableProcessor();
    }

    protected function tearDown()
    {
        unset($this->subject);
        parent::tearDown();
    }

    public function routePathDataProvider(): array
    {
        return [
            'no arguments, no namespace' => [
                [],
                null,
                '/static/{aa}/{bb}/{some_cc}/tail'
            ],
            'aa -> zz, no namespace' => [
                ['aa' => 'zz'],
                null,
                '/static/{zz}/{bb}/{some_cc}/tail'
            ],
            'no arguments, first' => [
                [],
                'first',
                '/static/{first__aa}/{first__bb}/{first__some_cc}/tail'
            ],
            'aa -> zz, first' => [
                ['aa' => 'zz'],
                'first',
                '/static/{first__zz}/{first__bb}/{first__some_cc}/tail'
            ],
        ];
    }

    /**
     * @param array $arguments
     * @param null|string $namespace
     * @param string $expectation
     *
     * @test
     * @dataProvider routePathDataProvider
     */
    public function isRoutePathDeflated(array $arguments, ?string $namespace, string $expectation)
    {
        $routePath = '/static/{aa}/{bb}/{some_cc}/tail';
        static::assertSame(
            $expectation,
            $this->subject->deflateRoutePath($routePath, $arguments, $namespace)
        );
    }

    /**
     * @param array $arguments
     * @param null|string $namespace
     * @param string $routePath
     *
     * @test
     * @dataProvider routePathDataProvider
     */
    public function isRoutePathInflated(array $arguments, ?string $namespace, string $routePath)
    {
        $expectation = '/static/{aa}/{bb}/{some_cc}/tail';
        static::assertSame(
            $expectation,
            $this->subject->inflateRoutePath($routePath, $arguments, $namespace)
        );
    }

    public function parametersDataProvider(): array
    {
        return [
            'no arguments, no namespace' => [
                [],
                null,
                ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa']]]
            ],
            'a -> newA, no namespace' => [
                ['a' => 'newA'],
                null,
                ['newA' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa']]]
            ],
            'no arguments, first' => [
                [],
                'first',
                ['a' => 'a', 'first__aa' => 'aa', 'first__second__aaa' => 'aaa']
            ],
            'a -> newA, first' => [
                ['a' => 'newA'],
                'first',
                ['newA' => 'a', 'first__aa' => 'aa', 'first__second__aaa' => 'aaa']
            ],
            'aa -> newAA, first' => [
                ['aa' => 'newAA'],
                'first',
                ['a' => 'a', 'first__newAA' => 'aa', 'first__second__aaa' => 'aaa']
            ],
            'a -> newA, aa -> newAA, first' => [
                ['a' => 'newA', 'aa' => 'newAA'],
                'first',
                ['newA' => 'a', 'first__newAA' => 'aa', 'first__second__aaa' => 'aaa']
            ],
        ];
    }

    /**
     * @param array $arguments
     * @param null|string $namespace
     * @param array $expectation
     *
     * @test
     * @dataProvider parametersDataProvider
     */
    public function parametersAreDeflated(array $arguments, ?string $namespace, array $expectation)
    {
        $parameters = ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa']]];
        static::assertEquals(
            $expectation,
            $this->subject->deflateParameters($parameters, $arguments, $namespace)
        );
    }

    /**
     * @param array $arguments
     * @param null|string $namespace
     * @param array $parameters
     *
     * @test
     * @dataProvider parametersDataProvider
     */
    public function parametersAreInflated(array $arguments, ?string $namespace, array $parameters)
    {
        $expectation = ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa']]];
        static::assertEquals(
            $expectation,
            $this->subject->inflateParameters($parameters, $arguments, $namespace)
        );
    }

    public function keysDataProvider(): array
    {
        return [
            'no arguments, no namespace' => [
                [],
                null,
                ['a' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']]
            ],
            'a -> newA, no namespace' => [
                ['a' => 'newA'],
                null,
                ['newA' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']]
            ],
            'no arguments, first' => [
                [],
                'first',
                ['first__a' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
            'a -> newA, first' => [
                ['a' => 'newA'],
                'first',
                ['first__newA' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
            'd -> newD, first' => [
                ['d' => 'newD'], // not substituted, which is expected
                'first',
                ['first__a' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
        ];
    }

    /**
     * @param array $arguments
     * @param null|string $namespace
     * @param array $expectation
     *
     * @test
     * @dataProvider keysDataProvider
     */
    public function keysAreDeflated(array $arguments, ?string $namespace, array $expectation)
    {
        $values = ['a' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']];
        static::assertEquals(
            $expectation,
            $this->subject->deflateKeys($values, $arguments, $namespace)
        );
    }

    /**
     * @param array $arguments
     * @param null|string $namespace
     * @param array $values
     *
     * @test
     * @dataProvider keysDataProvider
     */
    public function keysAreInflated(array $arguments, ?string $namespace, array $values)
    {
        $expectation = ['a' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']];
        static::assertEquals(
            $expectation,
            $this->subject->inflateKeys($values, $arguments, $namespace)
        );
    }
}
