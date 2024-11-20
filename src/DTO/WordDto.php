<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * @property  $definitions array<DefinitionDto>
 * @property  $otherForms  array<OtherFormDto>
 */
final class WordDto
{
    public function __construct(
        public string $word,
        public string $kana,
        public array  $definitions,
        public array  $otherForms,
    ) {}
}
