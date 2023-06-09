<?php
declare(strict_types=1);

namespace TYPO3\CMS\FrontendEditing\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;

/**
 * Middleware check if backend user allowed to access BE
 *
 * @package TYPO3\CMS\FrontendEditing\Middleware
 */
class BackendUserRedirectToFrontend implements MiddlewareInterface
{
    /**
     * Check if BE user is forced to see only FE
     *
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Allow ajax requests even though isUserDisallowedAccessBackend is true
        $routePath = isset($request->getAttributes()['routePath']) ? $request->getAttributes()['routePath'] : "/";
        if (in_array($routePath, $this->getAllowedRoutePaths())) {
            return $handler->handle($request);
        }

        $user = $this->getBackendUser();
        if (!$user->isAdmin() && $this->isUserDisallowedAccessBackend($user)) {
            return new RedirectResponse($this->buildFrontendUri($request));
        }

        return $handler->handle($request);
    }

    /**
     * Return the available allowed route paths
     *
     * @return string[]
     */
    protected function getAllowedRoutePaths(): array
    {
        return [
            '/ajax/frontend-editing/editor-configuration',
            '/ajax/frontend-editing/process'
        ];
    }

    /**
     * Check if access to BE is disabled by TS config
     *
     * @param BackendUserAuthentication $user
     * @return bool
     */
    protected function isUserDisallowedAccessBackend(BackendUserAuthentication $user): bool
    {
        return boolval($user->getTSConfig()['frontend_editing.']['disallow_backend_access'] ?? false);
    }

    /**
     * Return BE user from global
     *
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Build frontend URI from BE Request
     *
     * @param ServerRequestInterface $request
     * @return UriInterface
     */
    protected function buildFrontendUri(ServerRequestInterface $request): UriInterface
    {
        return $request->getUri()
            ->withPath('')
            ->withQuery('');
    }
}
