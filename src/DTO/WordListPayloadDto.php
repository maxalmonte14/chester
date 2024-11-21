<?php

declare(strict_types=1);

namespace App\DTO;

final class WordListPayloadDto
{
    public string $data;

    /**
     * @return array<LinkDto>
     */
    public static function toLinkCollection(string $data): array
    {
        $links = [];
        $jsonDecoded = json_decode($data, true);

        foreach ($jsonDecoded as $value) {
            $links[] = new LinkDto($value['url'], $value['text']);
        }

        return $links;
    }
}
