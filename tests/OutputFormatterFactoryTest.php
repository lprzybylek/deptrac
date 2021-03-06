<?php

declare(strict_types=1);

namespace Tests\SensioLabs\Deptrac;

use PHPUnit\Framework\TestCase;
use SensioLabs\Deptrac\OutputFormatter\OutputFormatterInterface;
use SensioLabs\Deptrac\OutputFormatter\OutputFormatterOption;
use SensioLabs\Deptrac\OutputFormatterFactory;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

final class OutputFormatterFactoryTest extends TestCase
{
    private function createNamedFormatter($name)
    {
        $formatter = $this->createMock(OutputFormatterInterface::class);
        $formatter->method('getName')->willReturn($name);

        return $formatter;
    }

    public function testGetFormatterByName(): void
    {
        $formatterFactory = new OutputFormatterFactory([
            $formatter1 = $this->createNamedFormatter('formatter1'),
            $formatter2 = $this->createNamedFormatter('formatter2'),
        ]);

        self::assertSame($formatter1, $formatterFactory->getFormatterByName('formatter1'));
        self::assertSame($formatter2, $formatterFactory->getFormatterByName('formatter2'));
    }

    public function testGetFormatterOptions(): void
    {
        $formatter1 = $this->createMock(OutputFormatterInterface::class);
        $formatter1->method('enabledByDefault')->willReturn(true);
        $formatter1->method('getName')->willReturn('foo1');
        $formatter1->method('configureOptions')->willReturn([
            OutputFormatterOption::newValueOption('f1-n1', 'f1-n1-desc', 'f1-n1-default'),
        ]);

        $formatter2 = $this->createMock(OutputFormatterInterface::class);
        $formatter2->method('enabledByDefault')->willReturn(true);
        $formatter2->method('getName')->willReturn('foo2');
        $formatter2->method('configureOptions')->willReturn([
            OutputFormatterOption::newValueOption('f2-n1', 'f2-n1-desc', 'f2-n1-default'),
            OutputFormatterOption::newValueOption('f2-n2', 'f2-n2-desc', 'f2-n2-default'),
        ]);

        $formatter3 = $this->createMock(OutputFormatterInterface::class);
        $formatter3->method('enabledByDefault')->willReturn(true);
        $formatter3->method('getName')->willReturn('foo3');
        $formatter3->method('configureOptions')->willReturn([]);

        $formatterFactory = new OutputFormatterFactory([
            $formatter1,
            $formatter2,
            $formatter3,
        ]);

        /** @var $arguments InputArgument[] */
        $arguments = $formatterFactory->getFormatterOptions();

        self::assertEquals('formatter-foo1', $arguments[0]->getName());

        self::assertEquals('f1-n1', $arguments[1]->getName());
        self::assertEquals('f1-n1-default', $arguments[1]->getDefault());
        self::assertEquals('f1-n1-desc', $arguments[1]->getDescription());

        self::assertEquals('formatter-foo2', $arguments[2]->getName());

        self::assertEquals('f2-n1', $arguments[3]->getName());
        self::assertEquals('f2-n1-default', $arguments[3]->getDefault());
        self::assertEquals('f2-n1-desc', $arguments[3]->getDescription());

        self::assertEquals('f2-n2', $arguments[4]->getName());
        self::assertEquals('f2-n2-default', $arguments[4]->getDefault());
        self::assertEquals('f2-n2-desc', $arguments[4]->getDescription());

        self::assertCount(6, $arguments);
    }

    public function testIsFormatterActive(): void
    {
        $formatter = (new OutputFormatterFactory([
            $this->createNamedFormatter('f1'),
            $this->createNamedFormatter('f2'),
            $this->createNamedFormatter('f3'),
        ]));

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->withConsecutive(
            ['formatter-f1'],
            ['formatter-f2'],
            ['formatter-f3']
        )
        ->willReturnOnConsecutiveCalls(true, true, false);

        self::assertCount(2, $formatter->getActiveFormatters($input));
    }

    public function testGetFormatterByNameNotFound(): void
    {
        $this->expectException(\LogicException::class);

        (new OutputFormatterFactory([]))->getFormatterByName('formatter1');
    }

    public function testGetFormattersByNames(): void
    {
        $formatterFactory = new OutputFormatterFactory([
            $formatter1 = $this->createNamedFormatter('formatter1'),
            $formatter2 = $this->createNamedFormatter('formatter2'),
        ]);

        self::assertSame([$formatter1], $formatterFactory->getFormattersByNames(['formatter1']));
        self::assertSame(
            [$formatter1, $formatter2],
            $formatterFactory->getFormattersByNames(['formatter1', 'formatter2'])
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Following formatters ["invalid", "invalid2"] are not supported.');
        $formatterFactory->getFormattersByNames(['invalid', 'invalid2']);
    }

    public function testGetFormattersEnabledByDefault(): void
    {
        $formatter1 = $this->createMock(OutputFormatterInterface::class);
        $formatter1->method('enabledByDefault')->willReturn(true);
        $formatter1->method('getName')->willReturn('foo1');
        $formatter1->method('configureOptions')->willReturn([]);

        $formatter2 = $this->createMock(OutputFormatterInterface::class);
        $formatter2->method('enabledByDefault')->willReturn(true);
        $formatter2->method('getName')->willReturn('foo2');
        $formatter2->method('configureOptions')->willReturn([]);

        $formatter3 = $this->createMock(OutputFormatterInterface::class);
        $formatter3->method('enabledByDefault')->willReturn(false);
        $formatter3->method('getName')->willReturn('foo3');
        $formatter3->method('configureOptions')->willReturn([]);

        $formatterFactory = new OutputFormatterFactory([$formatter1, $formatter2, $formatter3]);
        self::assertSame([$formatter1, $formatter2], $formatterFactory->getFormattersEnabledByDefault());
    }
}
