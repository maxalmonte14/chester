<?php

namespace App\Services;

use App\DTO\CategoryDTO;
use App\DTO\DefinitionDto;
use App\DTO\LinkDto;
use App\DTO\OtherFormDto;
use App\DTO\WordDto;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class JishoCrawlerService
{
    private Crawler $crawler;

    private array $excludeCategories = [
        'Place',
        'Wikipedia definition',
    ];

    public function __construct(
        private readonly HttpClientInterface $client,
        private array $definitions = [],
        private array $otherForms = [],
    ) {
        $this->crawler = new Crawler();
    }

    /**
     * @return array<LinkDto>
     */
    public function getLinksFromPage(string $link): array
    {
        try {
            $response = $this->client->request('GET', $link);
        } catch (\Exception $exception) {
            return [];
        }

        $this->crawler->addHtmlContent($response->getContent());
        $links = [];

        foreach ($this->crawler->filter('.concept_light.clearfix') as $domElement) {
            $word = $domElement
                ->childNodes
                ->item(1)
                ->firstElementChild
                ->firstElementChild
                ->lastElementChild
                ->textContent;
            $link = $domElement
                ->childNodes
                ->item(5)
                ->getAttribute('href');
            $links[] = new LinkDto($link, $word);
        }

        return $links;
    }

    /**
     * @param array<LinkDto> $links
     */
    public function getWords(array $links): array
    {
        $words = [];

        foreach ($links as $link) {
            $response = $this->client->request('GET', sprintf('https:%s', $link->url));
            $this->crawler->addHtmlContent($response->getContent());

            $kana = $this->getKana();

            foreach ($this->crawler->filter('.meanings-wrapper div') as $node) {
                if (
                    $node->getAttribute('class') == 'meaning-tags' &&
                    $node->textContent != 'Other forms'
                ) {
                    $meaningDefinition = $node->nextElementSibling->firstChild;
                    $formattedCategories = $this->getCategories(trim($node->textContent));

                    if (in_array($formattedCategories[0]->name, $this->excludeCategories)) {
                        continue;
                    }

                    $this->definitions = array_merge(
                        $this->definitions,
                        $this->getDefinition($meaningDefinition->childNodes, $formattedCategories)
                    );
                } else if (
                    $node->getAttribute('class') == 'meaning-tags' &&
                    $node->textContent == 'Other forms'
                ) {
                    {
                        $meaningDefinition = $node->nextElementSibling->firstChild;
                        $this->otherForms = $this->getOtherForms($meaningDefinition->childNodes);
                    }
                }
            }

            $words[] = new WordDto(
                trim($link->text),
                $kana,
                $this->definitions,
                $this->otherForms,
            );

            $this->resetProperties();
        }

        return $words;
    }

    private function resetProperties(): void
    {
        $this->crawler->clear();

        $this->definitions = [];
        $this->otherForms = [];
    }

    private function getKana(): string
    {
        $furigana = $this->crawler->filter('.furigana');
        $results = $furigana
                        ->siblings()
                        ->first()
                        ->children()
                        ->filter('span')
                        ->each(fn(Crawler $crawler) => $crawler->text());

        $furigana
            ->children()
            ->filter('span')
            ->each(function (Crawler $crawler, $key) use (&$results) {
                if (trim($crawler->text()) != '') {
                    array_splice($results, $key, 0, $crawler->text());
                }
            });

        return join($results);
    }

    /**
     * @return array<CategoryDTO>
     */
    private function getCategories(string $categories): array
    {
        return array_map(function ($category) {
            return new CategoryDTO(ucfirst(trim($category)));
        }, array_unique(explode(',', $categories)));
    }

    /**
     * @return array<DefinitionDto>
     */
    private function getDefinition(\DOMNodeList $nodeList, array $categories): array
    {
        $definitions = [];

        foreach ($nodeList as $meaningDefinitionContent) {
            if ($meaningDefinitionContent->getAttribute('class') == 'meaning-meaning') {
                $definitions[] = new DefinitionDto(
                    trim($meaningDefinitionContent->textContent),
                    $categories
                );
            }
        }

        return $definitions;
    }

    /**
     * @return array<OtherFormDto>
     */
    private function getOtherForms(\DOMNodeList $nodeList): array
    {
        $otherForms = [];

        foreach ($nodeList as $meaningDefinitionContent) {
            if ($meaningDefinitionContent->getAttribute('class') == 'meaning-meaning') {
                $otherForms[] = new OtherFormDto(
                    trim($meaningDefinitionContent->textContent),
                );
            }
        }

        return $otherForms;
    }
}
