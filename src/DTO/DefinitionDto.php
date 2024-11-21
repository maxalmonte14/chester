<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * @property  $categories array<CategoryDTO>
 */
final class DefinitionDto
{
    public function __construct(public string $definition, public array $categories) {
    }
}
