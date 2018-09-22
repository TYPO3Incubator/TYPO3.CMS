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
                null,
                [],
                '/static/{aa}/{bb}/{some_cc}/tail'
            ],
            'aa -> zz, no namespace' => [
                null,
                ['aa' => 'zz'],
                '/static/{zz}/{bb}/{some_cc}/tail'
            ],
            'aa -> @any/nested, no namespace' => [
                null,
                ['aa' => '@any/nested'],
                '/static/{9e3b73c3fcc6e4245a477041dad1c59b}/{bb}/{some_cc}/tail'
            ],
            'no arguments, first' => [
                'first',
                [],
                '/static/{first__aa}/{first__bb}/{first__some_cc}/tail'
            ],
            'aa -> zz, first' => [
                'first',
                ['aa' => 'zz'],
                '/static/{first__zz}/{first__bb}/{first__some_cc}/tail'
            ],
            'aa -> @any/nested, first' => [
                'first',
                ['aa' => '@any/nested'],
                '/static/{6b20f126a13b6fa75188af9b4b54f4af}/{first__bb}/{first__some_cc}/tail'
            ],
        ];
    }

    /**
     * @param null|string $namespace
     * @param array $arguments
     * @param string $deflatedRoutePath
     *
     * @test
     * @dataProvider routePathDataProvider
     */
    public function isRoutePathProcessed(?string $namespace, array $arguments, string $deflatedRoutePath)
    {
        $inflatedRoutePath = '/static/{aa}/{bb}/{some_cc}/tail';
        static::assertSame(
            $deflatedRoutePath,
            $this->subject->deflateRoutePath($inflatedRoutePath, $namespace, $arguments)
        );
        static::assertSame(
            $inflatedRoutePath,
            $this->subject->inflateRoutePath($deflatedRoutePath, $namespace, $arguments)
        );
    }

    public function parametersDataProvider(): array
    {
        return [
            // no changes expected without having a non-empty namespace
            'no namespace, no arguments' => [
                '',
                [],
                ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa', '@any' => '@any']]]
            ],
            'no namespace, a -> newA' => [
                '',
                ['a' => 'newA'],
                ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa', '@any' => '@any']]]
            ],
            'no namespace, a -> @any/nested' => [
                '',
                ['a' => '@any/nested'],
                ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa', '@any' => '@any']]]
            ],
            // changes for namespace 'first' are expected
            'first, no arguments' => [
                'first',
                [],
                ['a' => 'a', 'first__aa' => 'aa', 'first__second__aaa' => 'aaa', 'a9d66412d169b85537e11d9e49b75f9b' => '@any']
            ],
            'first, aa -> newAA' => [
                'first',
                ['aa' => 'newAA'],
                ['a' => 'a', 'first__newAA' => 'aa', 'first__second__aaa' => 'aaa', 'a9d66412d169b85537e11d9e49b75f9b' => '@any']
            ],
            'first, second -> newSecond' => [
                'first',
                ['second' => 'newSecond'],
                ['a' => 'a', 'first__aa' => 'aa', 'first__newSecond__aaa' => 'aaa', '27aded81f5d1607191c695720db7ab23' => '@any']
            ],
            'first, aa -> @any/nested' => [
                'first',
                ['aa' => '@any/nested'],
                ['a' => 'a', '6b20f126a13b6fa75188af9b4b54f4af' => 'aa', 'first__second__aaa' => 'aaa', 'a9d66412d169b85537e11d9e49b75f9b' => '@any']
            ],
            'first, aa -> newAA, second => newSecond' => [
                'first',
                ['aa' => 'newAA', 'second' => 'newSecond'],
                ['a' => 'a', 'first__newAA' => 'aa', 'first__newSecond__aaa' => 'aaa', '27aded81f5d1607191c695720db7ab23' => '@any']
            ],
        ];
    }

    /**
     * @param null|string $namespace
     * @param array $arguments
     * @param array $deflatedParameters
     *
     * @test
     * @dataProvider parametersDataProvider
     */
    public function parametersAreProcessed(string $namespace, array $arguments, array $deflatedParameters)
    {
        $inflatedParameters = ['a' => 'a', 'first' => ['aa' => 'aa', 'second' => ['aaa' => 'aaa', '@any' => '@any']]];
        static::assertEquals(
            $deflatedParameters,
            $this->subject->deflateNamespaceParameters($inflatedParameters, $namespace, $arguments)
        );
        static::assertEquals(
            $inflatedParameters,
            $this->subject->inflateNamespaceParameters($deflatedParameters, $namespace, $arguments)
        );
    }

    public function keysDataProvider(): array
    {
        return [
            'no arguments, no namespace' => [
                null,
                [],
                ['a' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']]
            ],
            'a -> newA, no namespace' => [
                null,
                ['a' => 'newA'],
                ['newA' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']]
            ],
            'a -> @any/nested, no namespace' => [
                null,
                ['a' => '@any/nested'],
                ['9e3b73c3fcc6e4245a477041dad1c59b' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']]
            ],
            'no arguments, first' => [
                'first',
                [],
                ['first__a' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
            'a -> newA, first' => [
                'first',
                ['a' => 'newA'],
                ['first__newA' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
            'a -> @any/nested, first' => [
                'first',
                ['a' => '@any/nested'],
                ['6b20f126a13b6fa75188af9b4b54f4af' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
            'd -> newD, first' => [
                'first',
                ['d' => 'newD'], // not substituted, which is expected
                ['first__a' => 'a', 'first__b' => 'b', 'first__c' => ['d' => 'd', 'e' => 'e']]
            ],
        ];
    }

    /**
     * @param null|string $namespace
     * @param array $arguments
     * @param array $deflatedKeys
     *
     * @test
     * @dataProvider keysDataProvider
     */
    public function keysAreDeflated(?string $namespace, array $arguments, array $deflatedKeys)
    {
        $inflatedKeys = ['a' => 'a', 'b' => 'b', 'c' => ['d' => 'd', 'e' => 'e']];
        static::assertEquals(
            $deflatedKeys,
            $this->subject->deflateKeys($inflatedKeys, $namespace, $arguments)
        );
        static::assertEquals(
            $inflatedKeys,
            $this->subject->inflateKeys($deflatedKeys, $namespace, $arguments)
        );
    }
}
