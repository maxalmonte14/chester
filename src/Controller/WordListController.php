<?php

declare(strict_types=1);

namespace Chester\Controller;

use Chester\DTO\WordListPayloadDto;
use Chester\Exceptions\UnableToRetrieveWordListException;
use Chester\Services\JishoCrawlerService;
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
    public function __invoke(#[MapRequestPayload] WordListPayloadDto $payload): Response {
        try {
            $words = $this->crawlerService->getWords(WordListPayloadDto::toLinkCollection($payload->data));

            return $this->render('home/word-list.html.twig', [
                'words' => $words,
            ]);
        } catch (UnableToRetrieveWordListException $exception) {
            return $this->render('errors/500.html.twig', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
