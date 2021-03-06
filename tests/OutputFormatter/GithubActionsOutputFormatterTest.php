<?php

namespace Tests\SensioLabs\Deptrac\OutputFormatter;

use PHPUnit\Framework\TestCase;
use SensioLabs\Deptrac\AstRunner\AstMap\AstInherit;
use SensioLabs\Deptrac\AstRunner\AstMap\ClassLikeName;
use SensioLabs\Deptrac\AstRunner\AstMap\FileOccurrence;
use SensioLabs\Deptrac\Console\Command\AnalyzeCommand;
use SensioLabs\Deptrac\Console\Symfony\Style;
use SensioLabs\Deptrac\Console\Symfony\SymfonyOutput;
use SensioLabs\Deptrac\Dependency\Dependency;
use SensioLabs\Deptrac\Dependency\InheritDependency;
use SensioLabs\Deptrac\OutputFormatter\GithubActionsOutputFormatter;
use SensioLabs\Deptrac\OutputFormatter\OutputFormatterInput;
use SensioLabs\Deptrac\RulesetEngine\Context;
use SensioLabs\Deptrac\RulesetEngine\Error;
use SensioLabs\Deptrac\RulesetEngine\SkippedViolation;
use SensioLabs\Deptrac\RulesetEngine\Uncovered;
use SensioLabs\Deptrac\RulesetEngine\Violation;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\SensioLabs\Deptrac\EmptyEnv;

final class GithubActionsOutputFormatterTest extends TestCase
{
    public function testGetName()
    {
        self::assertEquals('github-actions', (new GithubActionsOutputFormatter())->getName());
    }

    /**
     * @dataProvider finishProvider
     */
    public function testFinish(array $rules, array $errors, string $expectedOutput): void
    {
        $bufferedOutput = new BufferedOutput();

        $formatter = new GithubActionsOutputFormatter();
        $formatter->finish(
            new Context($rules, $errors),
            $this->createSymfonyOutput($bufferedOutput),
            new OutputFormatterInput([
                AnalyzeCommand::OPTION_REPORT_UNCOVERED => true,
                GithubActionsOutputFormatter::LEGACY_REPORT_UNCOVERED => false,
            ])
        );

        self::assertEquals($expectedOutput, $bufferedOutput->fetch());
    }

    public function finishProvider(): iterable
    {
        yield 'No Rules, No Output' => [
            [],
            [],
            '',
        ];

        $originalA = ClassLikeName::fromFQCN('\ACME\OriginalA');
        $originalB = ClassLikeName::fromFQCN('\ACME\OriginalB');
        $originalAOccurrence = FileOccurrence::fromFilepath('/home/testuser/originalA.php', 12);

        yield 'Simple Violation' => [
            [
                new Violation(
                    new Dependency($originalA, $originalB, $originalAOccurrence),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            "::error file=/home/testuser/originalA.php,line=12::ACME\OriginalA must not depend on ACME\OriginalB (LayerA on LayerB)\n",
        ];

        yield 'Skipped Violation' => [
            [
                new SkippedViolation(
                    new Dependency($originalA, $originalB, $originalAOccurrence),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            "::warning file=/home/testuser/originalA.php,line=12::[SKIPPED] ACME\OriginalA must not depend on ACME\OriginalB (LayerA on LayerB)\n",
        ];

        yield 'Uncovered Dependency' => [
            [
                new Uncovered(
                    new Dependency($originalA, $originalB, $originalAOccurrence),
                    'LayerA'
                ),
            ],
            [],
            "::warning file=/home/testuser/originalA.php,line=12::ACME\OriginalA has uncovered dependency on ACME\OriginalB (LayerA)\n",
        ];

        yield 'Inherit dependency' => [
            [
                new Violation(
                    new InheritDependency(
                        ClassLikeName::fromFQCN('ClassA'),
                        ClassLikeName::fromFQCN('ClassB'),
                        new Dependency($originalA, $originalB, FileOccurrence::fromFilepath('originalA.php', 12)),
                        AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritA'), FileOccurrence::fromFilepath('originalA.php', 3))
                            ->withPath([
                                AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritB'), FileOccurrence::fromFilepath('originalA.php', 4)),
                                AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritC'), FileOccurrence::fromFilepath('originalA.php', 5)),
                                AstInherit::newExtends(ClassLikeName::fromFQCN('ClassInheritD'), FileOccurrence::fromFilepath('originalA.php', 6)),
                            ])
                    ),
                    'LayerA',
                    'LayerB'
                ),
            ],
            [],
            "::error file=originalA.php,line=12::ClassA must not depend on ClassB (LayerA on LayerB)%0AClassInheritD::6 ->%0AClassInheritC::5 ->%0AClassInheritB::4 ->%0AClassInheritA::3 ->%0AACME\OriginalB::12\n",
        ];

        yield 'an error occurred' => [
            [],
            [new Error('an error occurred')],
            "::error ::an error occurred\n",
        ];
    }

    public function testGithubActionsOutputFormatterIsNotEnabledByDefault(): void
    {
        self::assertFalse((new GithubActionsOutputFormatter(new EmptyEnv()))->enabledByDefault());
    }

    public function testGetOptions(): void
    {
        self::assertCount(1, (new GithubActionsOutputFormatter(new EmptyEnv()))->configureOptions());
    }

    private function createSymfonyOutput(BufferedOutput $bufferedOutput): SymfonyOutput
    {
        return new SymfonyOutput(
            $bufferedOutput,
            new Style(new SymfonyStyle($this->createMock(InputInterface::class), $bufferedOutput))
        );
    }
}
