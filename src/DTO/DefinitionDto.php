<?php

declare(strict_types=1);

namespace Chester\DTO;

/**
 * @property array<CategoryDTO> $categories
 * @property array<SenseDTO>    $senses
 */
final class DefinitionDto
{
    /**
     * @param array<SenseDTO>    $senses
     * @param array<CategoryDTO> $categories
     */
    public function __construct(
        public string $definition,
        public array  $senses,
        public array  $categories
    ) {}
}
