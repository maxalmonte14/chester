<?php

declare(strict_types=1);

namespace Chester\Controller;

use Chester\DTO\ExtractLinkPayloadDto;
use Chester\Exceptions\UnableToFetchLinksException;
use Chester\Services\JishoCrawlerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class ExtractController extends AbstractController
{
    public function __construct(
        private readonly JishoCrawlerService $crawlerService,
    ) {}

    #[Route('/extract', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ExtractLinkPayloadDto $payload): Response {
        try {
            $links = $this->crawlerService->getLinksFromPage($payload->pageLink);

            return $this->render('home/extract.html.twig', [
                'links' => $links,
                'count' => count($links),
                'extra' => json_encode($links),
            ]);
        } catch (UnableToFetchLinksException $exception) {
            return $this->render('errors/500.html.twig', [
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
