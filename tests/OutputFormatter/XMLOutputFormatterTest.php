<?php

declare(strict_types=1);

namespace Tests\SensioLabs\Deptrac\OutputFormatter;

use PHPUnit\Framework\TestCase;
use SensioLabs\Deptrac\AstRunner\AstMap\AstInherit;
use SensioLabs\Deptrac\AstRunner\AstMap\ClassLikeName;
use SensioLabs\Deptrac\AstRunner\AstMap\FileOccurrence;
use SensioLabs\Deptrac\Console\Symfony\Style;
use SensioLabs\Deptrac\Console\Symfony\SymfonyOutput;
use SensioLabs\Deptrac\Dependency\Dependency;
use SensioLabs\Deptrac\Dependency\InheritDependency;
use SensioLabs\Deptrac\OutputFormatter\JUnitOutputFormatter;
use SensioLabs\Deptrac\OutputFormatter\OutputFormatterInput;
use SensioLabs\Deptrac\OutputFormatter\XMLOutputFormatter;
use SensioLabs\Deptrac\RulesetEngine\Context;
use SensioLabs\Deptrac\RulesetEngine\SkippedViolation;
use SensioLabs\Deptrac\RulesetEngine\Violation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

final class XMLOutputFormatterTest extends TestCase
{
    private static $actual_xml_report_file = 'actual-deptrac-report.xml';

    public function tearDown(): void
    {
        if (file_exists(__DIR__.'/data/'.self::$actual_xml_report_file)) {
            unlink(__DIR__.'/data/'.self::$actual_xml_report_file);
        }
    }

    public function testGetName(): void
    {
        self::assertSame('xml', (new XMLOutputFormatter())->getName());
    }

    public function basicDataProvider(): iterable
    {
        yield [
            [
                new Violation(
                    new InheritDependency(
                        ClassLikeName::fromFQCN('ClassA'),
                        ClassLikeName::fromFQCN('ClassB'),
                        new Dependency(ClassLikeName::fromFQCN('OriginalA'), ClassLikeName::fromFQCN('OriginalB'), FileOccurrence::fromFilepath('ClassA.php', 12)),
                        AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritA'), FileOccurrence::fromFilepath('ClassA.php', 3))->withPath([
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritB'), FileOccurrence::fromFilepath('ClassInheritA.php', 4)),
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritC'), FileOccurrence::fromFilepath('ClassInheritB.php', 5)),
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritD'), FileOccurrence::fromFilepath('ClassInheritC.php', 6)),
                        ])
                    ),
                    'LayerA',
                    'LayerB'
                ),
            ],
            'expected-xml-report_1.xml',
        ];

        yield [
            [
                new Violation(
                    new Dependency(ClassLikeName::fromFQCN('OriginalA'), ClassLikeName::fromFQCN('OriginalB'), FileOccurrence::fromFilepath('ClassA.php', 12)),
                    'LayerA',
                    'LayerB'
                ),
            ],
            'expected-xml-report_2.xml',
        ];

        yield [
            [],
            'expected-xml-report_3.xml',
        ];

        yield [
            [
                $violations = new SkippedViolation(
                    new InheritDependency(
                        ClassLikeName::fromFQCN('ClassA'),
                        ClassLikeName::fromFQCN('ClassB'),
                        new Dependency(ClassLikeName::fromFQCN('OriginalA'), ClassLikeName::fromFQCN('OriginalB'), FileOccurrence::fromFilepath('ClassA.php', 12)),
                        AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritA'), FileOccurrence::fromFilepath('ClassA.php', 3))->withPath([
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritB'), FileOccurrence::fromFilepath('ClassInheritA.php', 4)),
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritC'), FileOccurrence::fromFilepath('ClassInheritB.php', 5)),
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritD'), FileOccurrence::fromFilepath('ClassInheritC.php', 6)),
                        ])
                    ),
                    'LayerA',
                    'LayerB'
                ),
                new SkippedViolation(
                    new InheritDependency(
                        ClassLikeName::fromFQCN('ClassC'),
                        ClassLikeName::fromFQCN('ClassD'),
                        new Dependency(ClassLikeName::fromFQCN('OriginalA'), ClassLikeName::fromFQCN('OriginalB'), FileOccurrence::fromFilepath('ClassA.php', 12)),
                        AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritA'), FileOccurrence::fromFilepath('ClassA.php', 3))->withPath([
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritB'), FileOccurrence::fromFilepath('ClassInheritA.php', 4)),
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritC'), FileOccurrence::fromFilepath('ClassInheritB.php', 5)),
                            AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritD'), FileOccurrence::fromFilepath('ClassInheritC.php', 6)),
                        ])
                    ),
                    'LayerA',
                    'LayerB'
                ),
            ],
            'expected-xml-report-with-skipped-violations.xml',
        ];
    }

    /**
     * @dataProvider basicDataProvider
     */
    public function testBasic(array $rules, $expectedOutputFile): void
    {
        $bufferedOutput = new BufferedOutput();

        $formatter = new XMLOutputFormatter();
        $formatter->finish(
            new Context($rules, []),
            $this->createSymfonyOutput($bufferedOutput),
            new OutputFormatterInput([
                XMLOutputFormatter::DUMP_XML => __DIR__.'/data/'.self::$actual_xml_report_file,
                XMLOutputFormatter::LEGACY_DUMP_XML => false,
            ])
        );

        self::assertXmlFileEqualsXmlFile(
            __DIR__.'/data/'.self::$actual_xml_report_file,
            __DIR__.'/data/'.$expectedOutputFile
        );
    }

    public function testGetOptions(): void
    {
        self::assertCount(2, (new JUnitOutputFormatter())->configureOptions());
    }

    private function createSymfonyOutput(BufferedOutput $bufferedOutput): SymfonyOutput
    {
        return new SymfonyOutput(
            $bufferedOutput,
            new Style(new SymfonyStyle($this->createMock(InputInterface::class), $bufferedOutput))
        );
    }
}
