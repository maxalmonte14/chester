<?php

declare(strict_types=1);

namespace Chester\DTO;

final class SenseDTO
{
    public function __construct(
        public string $sense,
    ) {}

    public function __toString(): string
    {
        return $this->sense;
    }
}