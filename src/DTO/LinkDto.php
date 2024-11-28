<?php

declare(strict_types=1);

namespace Chester\DTO;

final class LinkDto
{
    public function __construct(public string $url, public string $text) {}
}
