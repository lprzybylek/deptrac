<?php

declare(strict_types=1);

namespace SensioLabs\Deptrac\AstRunner\Resolver;

use phpDocumentor\Reflection\Types\Context;
use PhpParser\Node;
use SensioLabs\Deptrac\AstRunner\AstMap\ClassReferenceBuilder;

class StaticCallResolver implements ClassDependencyResolver
{
    private $typeResolver;

    public function __construct(TypeResolver $typeResolver)
    {
        $this->typeResolver = $typeResolver;
    }

    public function processNode(Node $node, ClassReferenceBuilder $classReferenceBuilder, Context $context): void
    {
        if ($node instanceof Node\Expr\StaticCall && $node->class instanceof Node\Name) {
            foreach ($this->typeResolver->resolvePHPParserTypes($context, $node->class) as $classLikeName) {
                $classReferenceBuilder->staticMethod($classLikeName, $node->class->getLine());
            }
        }
    }
}
