<?php

declare(strict_types=1);

namespace App\DTO;

final class LinkDto
{
    public function __construct(public string $url, public string $text) {}
}
