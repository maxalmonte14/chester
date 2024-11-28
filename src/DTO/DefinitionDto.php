<?php

declare(strict_types=1);

namespace Chester\DTO;

/**
 * @property  $categories array<CategoryDTO>
 * @property  $senses     array<SenseDTO>
 */
final class DefinitionDto
{
    public function __construct(
        public string $definition,
        public array  $senses,
        public array  $categories
    ) {}
}
