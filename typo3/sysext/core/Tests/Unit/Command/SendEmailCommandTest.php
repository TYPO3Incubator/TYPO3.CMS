<?php
declare(strict_types = 1);
namespace TYPO3\CMS\Core\Tests\Unit\Command;

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

use Prophecy\Argument;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Transport\TransportInterface;
use TYPO3\CMS\Core\Command\SendEmailCommand;
use TYPO3\CMS\Core\Mail\DelayedTransportInterface;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case
 */
class SendEmailCommandTest extends UnitTestCase
{
    /**
     * @test
     */
    public function executeWillFlushTheQueue()
    {
        $delayedTransportProphecy = $this->prophesize(DelayedTransportInterface::class);
        $delayedTransportProphecy->flushQueue(Argument::any())->willReturn(5);
        $realTransportProphecy = $this->prophesize(TransportInterface::class);

        $mailer = $this->getMockBuilder(Mailer::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTransport', 'getRealTransport'])
            ->getMock();

        $mailer
            ->expects($this->any())
            ->method('getTransport')
            ->will($this->returnValue($delayedTransportProphecy->reveal()));

        $mailer
            ->expects($this->any())
            ->method('getRealTransport')
            ->will($this->returnValue($realTransportProphecy->reveal()));

        /** @var SendEmailCommand|\PHPUnit_Framework_MockObject_MockObject $command */
        $command = $this->getMockBuilder(SendEmailCommand::class)
            ->setConstructorArgs(['mailer:spool:send'])
            ->setMethods(['getMailer'])
            ->getMock();

        $command
            ->expects($this->any())
            ->method('getMailer')
            ->will($this->returnValue($mailer));

        $tester = new CommandTester($command);
        $tester->execute([], []);

        $this->assertTrue(strpos($tester->getDisplay(), '5 emails sent') > 0);
    }
}
