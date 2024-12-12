<?php

declare(strict_types=1);

namespace Chester\DTO;

final class WordListPayloadDto
{
    public string $data;

    /**
     * @return array<LinkDto>
     */
    public static function toLinkCollection(string $data): array
    {
        $links = [];
        /** @var array<array{url: string, text: string}>|null */
        $jsonDecoded = json_decode($data, true);

        if (is_null($jsonDecoded)) {
            return $links;
        }

        foreach ($jsonDecoded as $value) {
            $links[] = new LinkDto($value['url'], $value['text']);
        }

        return $links;
    }
}
