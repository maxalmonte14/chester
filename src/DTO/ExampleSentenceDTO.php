<?php

declare(strict_types=1);

namespace Chester\DTO;

final class ExampleSentenceDTO
{
    public function __construct(
        public string $sentence,
        public string $translation,
    ) {}
}
