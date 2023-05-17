<?php

declare(strict_types=1);

namespace TYPO3\CMS\FrontendEditing\Service;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Check access of the user to display only those actions which are allowed and needed.
 */
class AccessService implements SingletonInterface
{

    const feEditingAlreadyLoaded = "fe_editing_already_loaded";
    /**
     * Frontend editing is enabled.
     *
     * @var bool
     */
    protected $isEnabled = true;
    // debug()

    /**
     * Checks if frontend editing is enabled, checking UserTsConfig and TS.
     */
    public function __construct()
    {
        // Determine if page is loaded from the TYPO3 BE
        if ($this->isEnabled && !empty(GeneralUtility::getIndpEnv('HTTP_REFERER'))) {
            $parsedReferer = parse_url(GeneralUtility::getIndpEnv('HTTP_REFERER'));
            $pathArray = explode('/', $parsedReferer['path']);
            $viewPageView = isset($parsedReferer['query'])
                && preg_match('/web_ViewpageView/i', $parsedReferer['query']);
            $refererFromBackend = strtolower($pathArray[1]) === 'typo3' && $viewPageView;
            if ($refererFromBackend) {
                $this->isEnabled = false;
            }
        }
    }

    /**
     * Is frontend editing enabled or disabled.
     */
    public function isEnabled(): bool
    {

        if (isset($GLOBALS['BE_USER']) && $GLOBALS['BE_USER']) {


            if (isset($GLOBALS['BE_USER']->uc['frontend_editing']) && $GLOBALS['BE_USER']->uc['frontend_editing']) {
                    return true;
            }

            if (isset($GLOBALS['BE_USER']->user["beuser_frontend_editing"]) && $GLOBALS['BE_USER']->user["beuser_frontend_editing"]) {
                return true;
        }
        }

        return false;
    }

    /**
     * Has the user edit rights for page? (works with current page by default).
     *
     * @param array $page
     */
    public function isPageEditAllowed($page = []): bool
    {
        if (!($GLOBALS['BE_USER'] instanceof BackendUserAuthentication)) {
            return false;
        }

        if (!$page) {
            $page = $GLOBALS['TSFE']->page;
        }

        return $GLOBALS['BE_USER']->doesUserHaveAccess($page, Permission::PAGE_EDIT);
    }

    /**
     * Has the user create rights under current page?
     */
    public function isPageCreateAllowed(): bool
    {
        return true;
        if (!($GLOBALS['BE_USER'] instanceof BackendUserAuthentication)) {
            return false;
        }

        return $GLOBALS['BE_USER']->doesUserHaveAccess($GLOBALS['TSFE']->page, Permission::PAGE_NEW);
    }

    /**
     * Has the user edit rights for the content of the page?
     */
    public function isPageContentEditAllowed(array $page): bool
    {
        return true;

        return $GLOBALS['BE_USER']->doesUserHaveAccess($page, Permission::CONTENT_EDIT);
    }

    /**
     * Check if it is backend context.
     */
    public function isBackendContext(): bool
    {
        return true;

        return (isset($GLOBALS['BE_USER'])) ? true : false;
    }
}
