<?php

declare(strict_types=1);

/*
 * This file is part of the "matomo_integration" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Brotkrueml\MatomoIntegration\EventListener;

use Brotkrueml\MatomoIntegration\Code\JavaScriptCode;
use Brotkrueml\MatomoIntegration\Event\BeforeTrackPageViewEvent;
use Brotkrueml\MatomoIntegration\Extension;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @internal
 */
final class TrackErrorPages
{
    private ServerRequestInterface $request;

    /**
     * Arguments for testing purposes!
     */
    public function __construct(ServerRequestInterface $request = null)
    {
        $this->request = $request ?? $GLOBALS['TYPO3_REQUEST'];
    }

    public function __invoke(BeforeTrackPageViewEvent $event): void
    {
        if (! $event->getConfiguration()->trackErrorPages) {
            return;
        }

        $errorHandlers = $this->request->getAttribute('site')->getConfiguration()['errorHandling'] ?? [];
        if ($errorHandlers === []) {
            return;
        }

        $pageId = $this->request->getAttribute('routing')->getPageId();
        $errorHandlerForPage = \array_filter(
            $errorHandlers,
            static fn (array $handler): bool => $handler['errorHandler'] === 'Page' && $handler['errorContentSource'] === 't3://page?uid=' . $pageId
        );
        if ($errorHandlerForPage === []) {
            return;
        }

        $template = $event->getConfiguration()->errorPagesTemplate ?: Extension::DEFAULT_TEMPLATE_ERROR_PAGES;
        $templateVariables = [
            '{statusCode}' => (int)$errorHandlerForPage[0]['errorCode'],
            '{path}' => '"+encodeURIComponent(document.location.pathname+document.location.search)+"',
            '{referrer}' => '"+encodeURIComponent(document.referrer)+"',
        ];
        $code = \sprintf(
            '"%s"',
            \str_replace(
                \array_keys($templateVariables),
                \array_values($templateVariables),
                \addcslashes($template, '"')
            )
        );

        $event->addMatomoMethodCall('setDocumentTitle', new JavaScriptCode($code));
    }
}
