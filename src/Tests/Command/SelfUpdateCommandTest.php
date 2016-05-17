<?php

namespace SensioLabs\AstRunner\Tests\Command;

use Humbug\SelfUpdate\Updater;
use SensioLabs\Deptrac\Command\SelfUpdateCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Humbug\SelfUpdate\Exception\HttpRequestException;

class SelfUpdateCommandTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests the command status is 1 (fail) if the update fails
     */
    public function testUpdateFail()
    {
        $updater = $this->prophesize(Updater::class);
        $input = $this->prophesize(InputInterface::class);
        $output = $this->prophesize(OutputInterface::class);

        $updater->update()->willThrow(HttpRequestException::class);

        $command = new SelfUpdateCommand($updater->reveal());
        $result = $command->run($input->reveal(), $output->reveal());

        $this->assertSame(1, $result);
    }

    /**
     * Tests the command status is 0 (success) if the update success or is not necessary
     *
     * @param $isPharOutdated
     *
     * @dataProvider testUpdateSuccessProvider
     */
    public function testUpdateSuccess($isPharOutdated)
    {
        $updater = $this->prophesize(Updater::class);
        $input = $this->prophesize(InputInterface::class);
        $output = $this->prophesize(OutputInterface::class);

        $updater->update()->willReturn($isPharOutdated);

        $command = new SelfUpdateCommand($updater->reveal());
        $result = $command->run($input->reveal(), $output->reveal());

        $this->assertSame(0, $result);
    }

    public function testUpdateSuccessProvider()
    {
       yield [true];
       yield [false];
    }
}
