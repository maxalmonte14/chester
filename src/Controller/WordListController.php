<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\WordListPayloadDto;
use App\Services\JishoCrawlerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class WordListController extends AbstractController
{
    public function __construct(
        private readonly JishoCrawlerService $crawlerService,
    ) {}

    #[Route('/word-list', methods: ['POST'])]
    public function getWords(#[MapRequestPayload] WordListPayloadDto $payload): Response {
        $words = $this->crawlerService->getWords(WordListPayloadDto::toLinkCollection($payload->data));
        return $this->render('home/word-list.html.twig', [
            'words' => $words,
        ]);
    }
}
