<?php

declare(strict_types=1);

namespace Chester\DTO;

/**
 * @property array<DefinitionDto> $definitions
 * @property array<OtherFormDto>  $otherForms
 */
final class WordDto
{
    /**
     * @param array<DefinitionDto> $definitions
     * @param array<OtherFormDto>  $otherForms
     */
    public function __construct(
        public string $word,
        public ?string $kana,
        public array $definitions,
        public array $otherForms,
        public ?ExampleSentenceDTO $exampleSentence,
    ) {}
}
