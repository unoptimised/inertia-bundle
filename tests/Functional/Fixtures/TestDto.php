<?php

declare(strict_types=1);

namespace Unoptimised\InertiaBundle\Tests\Functional\Fixtures;

class TestDto
{
    public function __construct(
        public string $name,
        public int $count,
    ) {
    }
}