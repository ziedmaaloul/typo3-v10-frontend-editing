<?php

declare(strict_types=1);

namespace TYPO3\CMS\FrontendEditing\Hook;

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

use TYPO3\CMS\Backend\Controller\ContentElement\NewContentElementController;
use TYPO3\CMS\Backend\FrontendBackendUserAuthentication;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\Wizard\NewContentElementWizardHookInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DocumentTypeExclusionRestriction;
use TYPO3\CMS\Core\Exception\Page\RootLineException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconRegistry;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Frontend\Aspect\PreviewAspect;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\FrontendEditing\Backend\Controller\ContentElement\NewContentElementController as NCE;
use TYPO3\CMS\FrontendEditing\Core\Page\PageRenderer;
use TYPO3\CMS\FrontendEditing\Provider\Seo\CsSeoProvider;
use TYPO3\CMS\FrontendEditing\Service\AccessService;
use TYPO3\CMS\FrontendEditing\Service\ContentEditableWrapperService;
use TYPO3\CMS\FrontendEditing\Service\ExtensionManagerConfigurationService;
use TYPO3\CMS\FrontendEditing\Utility\ConfigurationUtility;
use TYPO3\CMS\FrontendEditing\Utility\FrontendEditingUtility;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
/**
 * Hook class using the "ContentPostProc" hook in TSFE for rendering the panels
 * and iframe for inline editing.
 */
class FrontendEditingInitializationHook
{
    const FRONTEND_EDITING_ALREADY_LOADED = AccessService::feEditingAlreadyLoaded;

    protected $contentController = null;
    /**
     * @var AccessService
     */
    protected $accessService;

    /**
     * @var TypoScriptFrontendController
     */
    protected $typoScriptFrontendController = null;

    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * @var IconRegistry
     */
    protected $iconRegistry;

    /**
     * @var PageRenderer
     */
    protected $pageRenderer;

    /**
     * @var array
     */
    protected $pluginConfiguration;

    /**
     * Flag if can use router for URL generation.
     *
     * @var bool
     */
    protected $isSiteConfigurationFound = false;

    protected $moduleTemplateFactory = null;
    protected $ttContent = 'tt_content';

    /**
     * ContentPostProc constructor.
     */
    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $this->iconRegistry = GeneralUtility::makeInstance(IconRegistry::class);
        $this->pluginConfiguration = [];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $this->accessService = GeneralUtility::makeInstance(AccessService::class);
        $this->pageRenderer = new PageRenderer();
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
        $this->contentController = GeneralUtility::makeInstance(NCE::class, $this->iconFactory, $this->pageRenderer, $uriBuilder, $this->moduleTemplateFactory);

        $this->user = $GLOBALS['TSFE']->fe_user->user;

        $this->isSiteConfigurationFound = true;

        $context = GeneralUtility::makeInstance(Context::class);
        // Set is preview for frontend
        $context->setAspect('isPreview', GeneralUtility::makeInstance(PreviewAspect::class, false));
        // Allow hidden pages for links generation
        $context->setAspect('visibility', GeneralUtility::makeInstance(VisibilityAspect::class, true));
    }

    /**
     * Check if this hook should actually replace the content and load the actual
     * page in an iframe.
     */
    protected function isFrontendEditingEnabled(TypoScriptFrontendController $tsfe): bool
    {
        $this->accessService = GeneralUtility::makeInstance(AccessService::class);

        if ($this->accessService->isEnabled() && $tsfe->type === 0) {
            $isFrontendEditing = GeneralUtility::_GET('frontend_editing');
            if (!isset($isFrontendEditing) && (bool) $isFrontendEditing !== true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hook to unset page setup before render the toolbars to speed up the render
     * the real frontend page will be called and rendered later
     * with query parameter 'frontend_editing=true'.
     */
    public function unsetPageSetup(array $params, TypoScriptFrontendController $parentObject)
    {
        if (!$this->isFrontendEditingEnabled($parentObject)) {
            return;
        }
        $parentObject->pSetup = [];
        $parentObject->config['config']['disableAllHeaderCode'] = true;
    }

    public function doIt(array $params, TypoScriptFrontendController $parentObject)
    {
        // debug('passed !');die;
        if (!GeneralUtility::_GP('id')) {
            $_GET['id'] = $parentObject->getRequestedId();
        }

        $this->typoScriptFrontendController = $parentObject;

        // Special content is about to be shown, so the cache must be disabled.
        $this->typoScriptFrontendController->set_no_cache('Display frontend editing', true);

        $requestUrl = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
        // Check if url has a ?, then decide on URL separator
        if (strpos($requestUrl, '?') !== false) {
            $urlSeparator = '&';
        } else {
            $urlSeparator = '?';
        }
        $requestUrl = $requestUrl.$urlSeparator.self::FRONTEND_EDITING_ALREADY_LOADED.'=1&no_cache=1';

        // If not language service is set then create one
        if (!isset($GLOBALS['LANG'])) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $GLOBALS['LANG']->init($GLOBALS['BE_USER']->uc['lang']);
        }

        // Initialize backend routes
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $endpointUrl = $uriBuilder->buildUriFromRoute(
            'ajax_frontendediting_process',
            ['page' => $this->typoScriptFrontendController->id]
        );
        $configurationEndpointUrl = $uriBuilder->buildUriFromRoute(
            'ajax_frontendediting_editorconfiguration',
            ['page' => $this->typoScriptFrontendController->id]
        );
        $ajaxUrlIcons = $uriBuilder->buildUriFromRoute(
            'ajax_icons'
        );

        $returnUrl = PathUtility::getAbsoluteWebPath(
            GeneralUtility::getFileAbsFileName(
                'EXT:frontend_editing/Resources/Public/Templates/Close.html'
            ).'?'
        );

        $pageEditUrl = $this->accessService->isPageEditAllowed() ? $uriBuilder->buildUriFromRoute(
            'record_edit',
            [
                'edit[pages]['.$this->typoScriptFrontendController->id.']' => 'edit',
                'returnUrl' => $returnUrl,
                'feEdit' => 1,
            ]
        ) : null;
        $pageNewUrl = $this->accessService->isPageCreateAllowed() ? $uriBuilder->buildUriFromRoute(
            'db_new',
            [
                'id' => $this->typoScriptFrontendController->id,
                'pagesOnly' => 1,
                'returnUrl' => $returnUrl,
            ]
        ) : null;

        // Define the window size of the popups within the RTE

        $rtePopupWindowSize = $GLOBALS['BE_USER']->getTSConfig()['options.']['rte.']['popupWindowSize'] ?? [];

        if (!empty($rtePopupWindowSize)) {
            list(, $rtePopupWindowHeight) = GeneralUtility::trimExplode('x', $rtePopupWindowSize);
        }
        $rtePopupWindowHeight = !empty($rtePopupWindowHeight) ? (int) $rtePopupWindowHeight : 600;

        // Define DateTimePicker dateformat
        $dateFormat = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['USdateFormat'] ?
            ['MM-DD-YYYY', 'HH:mm MM-DD-YYYY'] : ['DD-MM-YYYY', 'HH:mm DD-MM-YYYY']);

        // Load available content elements right here, because it adds too much stuff to PageRenderer,
        // so it has to be loaded before

        $availableContentElementTypes = $this->getContentItems();

        $baseUrl = ($this->getPluginConfiguration()['baseUrl']) ? $this->getPluginConfiguration()['baseUrl'] : '/';

        // PageRenderer needs to be completely reinitialized
        // Thus, this hack is necessary for now

        $this->pageRenderer->setBaseUrl($baseUrl);
        $this->pageRenderer->setCharset('utf-8');

        $this->pageRenderer->setMetaTag('name', 'viewport', 'width=device-width, initial-scale=1');
        $this->pageRenderer->setMetaTag('http-equiv', 'X-UA-Compatible', 'IE=edge');

        $this->pageRenderer->setHtmlTag('<!DOCTYPE html><html lang="en">');

        $resourcePath = 'EXT:frontend_editing/Resources/Public/';
        $this->loadStylesheets();
        $this->loadJavascriptResources();
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/FrontendEditing/GUI', 'function(FrontendEditing) {
            // The global F object for API calls
            window.F = new FrontendEditing();
            window.F.initGUI({
                content: '.GeneralUtility::quoteJSvalue($this->typoScriptFrontendController->content).',
                pageTree:'.json_encode($this->getPageTreeStructure()).',
                resourcePath: '.GeneralUtility::quoteJSvalue($this->getAbsolutePath($resourcePath)).',
                iframeUrl: '.GeneralUtility::quoteJSvalue($requestUrl).',
                editorConfigurationUrl: '.GeneralUtility::quoteJSvalue($configurationEndpointUrl).'
            });
            window.F.setEndpointUrl('.GeneralUtility::quoteJSvalue($endpointUrl).');
            window.F.setBESessionId('.GeneralUtility::quoteJSvalue($this->getBeSessionKey()).');
            window.F.setTranslationLabels('.json_encode($this->getLocalizedFrontendLabels()).');
            window.F.setDisableModalOnNewCe('.
                (int) ConfigurationUtility::getExtensionConfiguration()['enablePlaceholders'].
            ');
            window.FrontendEditingMode = true;
            window.TYPO3.settings = {
                Textarea: {
                    RTEPopupWindow: {
                        height: '.$rtePopupWindowHeight.'
                    }
                },
                ajaxUrls: {
                    icons: \''.$ajaxUrlIcons.'\'
                },
                DateTimePicker: {
                    DateFormat: '.json_encode($dateFormat).'
                }
            };
        }');

        $pageEditUrl = $uriBuilder->buildUriFromRoute(
            'record_edit',
            [
                'edit[pages]['.$this->typoScriptFrontendController->id.']' => 'edit',
                'returnUrl' => $returnUrl,
                'feEdit' => 1,
            ]
        );
        $pageNewUrl = $uriBuilder->buildUriFromRoute(
            'db_new',
            [
                'id' => $this->typoScriptFrontendController->id,
                'pagesOnly' => 1,
                'returnUrl' => $returnUrl,
            ]
        );

        $view = $this->initializeView();

        $configurationManager = GeneralUtility::makeInstance(
            ConfigurationManager::class
        );

        $settings = $configurationManager->getConfiguration($configurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $enableDefaultRightBarValues = $settings['plugin.']['tx_frontend_editing.']['settings.']['enableDefaultRightBar.'];
        $new_key = max(array_keys($enableDefaultRightBarValues));
        $enableDefaultRightBar = $enableDefaultRightBarValues[$new_key];

         // Start Working On Primary Color
        $primaryColorValues = $settings['plugin.']['tx_frontend_editing.']['settings.']['defaultColors.']['primaryColor.'];
        $newKeyPrimaryColor = max(array_keys($primaryColorValues));
        $primaryColor = $primaryColorValues[$newKeyPrimaryColor];


        if(isset($GLOBALS['BE_USER']->user["primary_color"]) && $GLOBALS['BE_USER']->user["primary_color"]){
            $primaryColor = $GLOBALS['BE_USER']->user["primary_color"];
        }

        // End Working On Primary Color

        // Start Working On Secondary Color
        $secondaryColorValues = $settings['plugin.']['tx_frontend_editing.']['settings.']['defaultColors.']['secondaryColor.'];
        $newKeysecondaryColor = max(array_keys($secondaryColorValues));
        $secondaryColor = $secondaryColorValues[$newKeysecondaryColor];
        // End Working On Secondary Color
        
        if(isset($GLOBALS['BE_USER']->user["secondary_color"]) && $GLOBALS['BE_USER']->user["secondary_color"]){
            $secondaryColor = $GLOBALS['BE_USER']->user["secondary_color"];
        }

        $view->assignMultiple([
            'primaryColor' => $primaryColor,
            'secondaryColor' => $secondaryColor,
            'enableDefaultRightBar' => $enableDefaultRightBar,
            'overlayOption' => isset($GLOBALS['BE_USER']->uc['frontend_editing_overlay']) ? $GLOBALS['BE_USER']->uc['frontend_editing_overlay'] : null,
            'currentUser' => $GLOBALS['BE_USER']->user,
            'currentTime' => $GLOBALS['EXEC_TIME'],
            'currentPage' => $this->typoScriptFrontendController->id,
            'contentItems' => $availableContentElementTypes,
            'contentElementsOnPage' => $this->getContentElementsOnPage((int) $this->typoScriptFrontendController->id),
            'customRecords' => $this->getCustomRecords(),
            'logoutUrl' => $uriBuilder->buildUriFromRoute('logout'),
            'backendUrl' => $uriBuilder->buildUriFromRoute('main'),
            'pageEditUrl' => $pageEditUrl,
            'pageNewUrl' => $pageNewUrl,
            'loadingIcon' => $this->iconFactory->getIcon('spinner-circle-dark', Icon::SIZE_LARGE)->render(),
            'mounts' => $this->getBEUserMounts(),
            'showHiddenItemsUrl' => $requestUrl.'&show_hidden_items='.$this->showHiddenItems(),
            'seoProviderData' => $this->getSeoProviderData((int) $this->typoScriptFrontendController->id),
        ]);

        // Assign the content
        $this->pageRenderer->setBodyContent($view->render());
        $parentObject->content = $this->pageRenderer->render();

        GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)
            ->flushCaches();
        unset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_previewInfo']);
    }

    /**
     * Hook to change page output to add the topbar.
     *
     * @throws \Exception
     */
    public function main(array $params, TypoScriptFrontendController $parentObject)
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        
        if (FrontendEditingUtility::isEnabled() &&
            $parentObject->getPageArguments()->get(self::FRONTEND_EDITING_ALREADY_LOADED) == null &&
            isset($GLOBALS['BE_USER'])
            ) {
            $this->doIt($params, $parentObject);
        }
    }

    /**
     * Returns an array with labels from translation file.
     */
    protected function getLocalizedFrontendLabels(): array
    {
        $languageFactory = GeneralUtility::makeInstance(LocalizationFactory::class);
        $parsedLocallang = $languageFactory->getParsedData(
            'EXT:frontend_editing/Resources/Private/Language/locallang.xlf',
            'default'
        );
        $localizedLabels = [];
        foreach (array_keys($parsedLocallang['default']) as $key) {
            $localizedLabels[$key] = LocalizationUtility::translate($key, 'FrontendEditing');
        }

        return $localizedLabels;
    }

    /**
     * Load the necessary CSS resources for the toolbars.
     */
    protected function loadStylesheets()
    {
        $configurationManager = GeneralUtility::makeInstance(
            ConfigurationManager::class
        );

        $settings = $configurationManager->getConfiguration($configurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $cssFiles = $settings['plugin.']['tx_frontend_editing.']['settings.']['cssFiles.'];

        foreach ($cssFiles as $file) {
            $this->pageRenderer->addCssFile($file);
        }
    }

    /**
     * Load the necessary Javascript-resources for the toolbars.
     */
    protected function loadJavascriptResources()
    {
        $configurationManager = GeneralUtility::makeInstance(
            ConfigurationManager::class
        );

        $settings = $configurationManager->getConfiguration($configurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $jsFiles = $settings['plugin.']['tx_frontend_editing.']['settings.']['jsFiles.'];

        foreach ($jsFiles as $file) {
            $this->pageRenderer->addJsFile($file);
        }

        $this->pageRenderer->loadRequireJs();

        $this->pageRenderer->addRequireJsConfiguration(
            [
                'shim' => [
                    'ckeditor' => ['exports' => 'CKEDITOR'],
                    'ckeditor-jquery-adapter' => ['jquery', 'ckeditor'],
                ],
                'paths' => [
                    'TYPO3/CMS/FrontendEditing/Contrib/toastr' => 'EXT:frontend_editing/Resources/Public/JavaScript/Contrib/toastr',
                    'TYPO3/CMS/FrontendEditing/Contrib/immutable' => 'EXT:frontend_editing/Resources/Public/JavaScript/Contrib/immutable',
                ],
            ]
        );

        $this->pageRenderer->addJsFile(
            'EXT:backend/Resources/Public/JavaScript/backend.js'
        );
        // Load CKEDITOR and CKEDITOR jQuery adapter independent for global access
        $this->pageRenderer->addJsFooterFile('EXT:rte_ckeditor/Resources/Public/JavaScript/Contrib/ckeditor.js');
        $this->pageRenderer->addJsFooterFile(
            'EXT:frontend_editing/Resources/Public/JavaScript/Contrib/ckeditor-jquery-adapter.js'
        );

        // Fixes issue #377, where CKEditor dependencies fail to load if the version number is added to the file name
        if ($GLOBALS['TYPO3_CONF_VARS']['FE']['versionNumberInFilename'] === 'embed') {
            $this->pageRenderer->addJsInlineCode(
                'ckeditor-basepath-config',
                'window.CKEDITOR_BASEPATH = '.GeneralUtility::quoteJSvalue(PathUtility::getRelativePathTo(
                    GeneralUtility::getFileAbsFileName(
                        'EXT:rte_ckeditor/Resources/Public/JavaScript/Contrib/'
                    )
                )).';',
                true,
                true
            );
        }

        $configuration = $this->getPluginConfiguration();

        // debug($configuration);die;
        if (isset($configuration['includeJS']) && is_array($configuration['includeJS'])) {
            foreach ($configuration['includeJS'] as $file) {
                $this->pageRenderer->addJsFile($file);
            }
        }
    }

    /**
     * Helper method to get the file name relative to the URL.
     */
    protected function getAbsolutePath(string $path): string
    {
        return PathUtility::getAbsoluteWebPath(GeneralUtility::getFileAbsFileName($path));
    }

    /**
     * Get the page tree structure of the current tree.
     *
     * @throws \Exception
     */
    protected function getPageTreeStructure(): array
    {
        $entryPoints = $this->getAllEntryPointPageTrees();

        foreach ($entryPoints as $entryPoint) {
            if ($entryPoint['uid'] === 0) {
                $children = $this->getStructureForSinglePageTree(
                    $this->typoScriptFrontendController->rootLine[0]['uid']
                );
            } else {
                $children[] = $this->getStructureForSinglePageTree($entryPoint['uid'])[0];
            }
        }

        $appsPagetreeRootIcon = $this->iconRegistry->getIconConfigurationByIdentifier('apps-pagetree-root');

        return [
            'name' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],
            'icon' => $this->getAbsolutePath($appsPagetreeRootIcon['options']['source']),
            'children' => $children,
        ];
    }

    /**
     * Fetches all entry points for the page tree that the user is allowed to see
     * This was taken from class TreeController (namespace TYPO3\CMS\Backend\Controller\Page);.
     */
    protected function getAllEntryPointPageTrees(): array
    {
        $backendUser = $GLOBALS['BE_USER'];

        $userTsConfig = $GLOBALS['BE_USER']->getTSConfig();
        $excludedDocumentTypes = GeneralUtility::intExplode(
            ',',
            $userTsConfig['options.']['pageTree.']['excludeDoktypes'] ?? '',
            true
        );

        $additionalPageTreeQueryRestrictions = [];
        if (!empty($excludedDocumentTypes)) {
            foreach ($excludedDocumentTypes as $excludedDocumentType) {
                $additionalPageTreeQueryRestrictions[] = new DocumentTypeExclusionRestriction(
                    (int) $excludedDocumentType
                );
            }
        }

        $repository = GeneralUtility::makeInstance(
            PageTreeRepository::class,
            (int) $backendUser->workspace,
            [],
            $additionalPageTreeQueryRestrictions
        );

        $entryPoints = (int) ($backendUser->uc['pageTree_temporaryMountPoint'] ?? 0);
        if ($entryPoints > 0) {
            $entryPoints = [$entryPoints];
        } else {
            $entryPoints = array_map('intval', $backendUser->returnWebmounts());
            $entryPoints = array_unique($entryPoints);
            if (empty($entryPoints)) {
                // Use a virtual root
                // The real mount points will be fetched in getNodes() then
                // since those will be the "sub pages" of the virtual root
                $entryPoints = [0];
            }
        }
        if (empty($entryPoints)) {
            return [];
        }

        $hiddenRecords = GeneralUtility::intExplode(
            ',',
            $userTsConfig['options.']['hideRecords.']['pages'] ?? '',
            true
        );
        foreach ($entryPoints as $k => &$entryPoint) {
            if (in_array($entryPoint, $hiddenRecords, true)) {
                unset($entryPoints[$k]);
                continue;
            }

            if (!empty($this->backgroundColors) && is_array($this->backgroundColors)) {
                try {
                    $entryPointRootLine = GeneralUtility::makeInstance(RootlineUtility::class, $entryPoint)->get();
                } catch (RootLineException $e) {
                    $entryPointRootLine = [];
                }
                foreach ($entryPointRootLine as $rootLineEntry) {
                    $parentUid = $rootLineEntry['uid'];
                    if (!empty($this->backgroundColors[$parentUid]) && empty($this->backgroundColors[$entryPoint])) {
                        $this->backgroundColors[$entryPoint] = $this->backgroundColors[$parentUid];
                    }
                }
            }

            $entryPoint = $repository->getTree($entryPoint, function ($page) use ($backendUser) {
                // Check each page if the user has permission to access it
                return $backendUser->doesUserHaveAccess($page, Permission::PAGE_SHOW);
            });
            if (!is_array($entryPoint)) {
                unset($entryPoints[$k]);
            }
        }

        return $entryPoints;
    }

    /**
     * Get the page tree structure of page.
     *
     * @throws \Exception
     */
    protected function getStructureForSinglePageTree(int $startingPoint): array
    {
        // Get page record for tree starting point
        // from where we currently are navigated
        $pageRecord = BackendUtility::getRecord('pages', $startingPoint);

        // Creating the icon for the current page and add it to the tree
        $html = $this->iconFactory->getIconForRecord(
            'pages',
            $pageRecord,
            Icon::SIZE_SMALL
        );

        // Create and initialize the tree object
        /** @var PageTreeView $tree */
        $tree = GeneralUtility::makeInstance(PageTreeView::class);
        $tree->init(' AND '.$GLOBALS['BE_USER']->getPagePermsClause(1));
        $tree->makeHTML = 0;
        $tree->tree[] = [
            'row' => $pageRecord,
            'HTML' => $html,
        ];

        // Create the page tree, from the starting point, infinite levels down
        $tree->getTree($startingPoint);

        $tree->tree[0] += [
            'uid' => $pageRecord['uid'],
            'invertedDepth' => 1000,
            'hasSub' => count($tree->tree) > 1,
        ];

        return $this->generateTreeData($tree->tree);
    }

    /**
     * Build array of page tree with children.
     *
     * @throws \Exception
     */
    protected function generateTreeData(array $tree, int $depth = 1000): array
    {
        $index = 0;
        $treeData = [];

        foreach ($tree as $item) {
            ++$index;
            if ($item['invertedDepth'] === $depth) {
                $treeItem = [
                    'uid' => $item['row']['uid'],
                    'name' => $item['row']['title'],
                    'doktype' => $item['row']['doktype'],
                    'link' => $this->getTreeItemLink($item),
                    'icon' => $this->getTreeItemIconPath($item['row']),
                    'iconOverlay' => $this->getTreeItemIconOverlayPath($item['row']),
                    'isActive' => $this->typoScriptFrontendController->id === $item['row']['uid'],
                ];

                if ($item['hasSub']) {
                    $treeItem['children'] = $this->generateTreeData(array_slice($tree, $index), $depth - 1);
                }

                $treeData[] = $treeItem;

                if (isset($item['isLast'])) {
                    break;
                }
            }
        }

        return $treeData;
    }

    /**
     * Generate url for single tree item.
     */
    protected function getTreeItemLink(array $item): string
    {
        if ($this->isSiteConfigurationFound) {
            /** @var Site $site */
            // @extensionScannerIgnoreLine
            $site = $GLOBALS['TYPO3_REQUEST']->getAttribute('site');

            try {
                return (string) $site->getRouter()->generateUri(
                    (int) $item['row']['uid'],
                    [],
                    '',
                    RouterInterface::ABSOLUTE_URL
                );
            } catch (InvalidRouteArgumentsException $exception) {
                // Just fallback to old format links
            }
        }

        return $this->fallbackTreeItemLinkFormat($item);
    }

    /**
     * Create "/index.php?id=" page url.
     */
    protected function fallbackTreeItemLinkFormat(array $item): string
    {
        return sprintf(
            '%sindex.php?id=%d',
            $this->typoScriptFrontendController->absRefPrefix ?: '/',
            $item['row']['uid']
        );
    }

    /**
     * Get path to page tree item icon.
     *
     * @throws \Exception
     */
    protected function getTreeItemIconPath(array $row): string
    {
        $iconIdentifier = $this->iconFactory->mapRecordTypeToIconIdentifier('pages', $row);

        if (!$iconIdentifier || !$this->iconRegistry->isRegistered($iconIdentifier)) {
            $iconIdentifier = $this->iconRegistry->getDefaultIconIdentifier();
        }
        $iconConfiguration = $this->iconRegistry->getIconConfigurationByIdentifier($iconIdentifier);

        $source = $iconConfiguration['options']['source'];

        if (strpos($source, 'EXT:') === 0 || strpos($source, '/') !== 0) {
            $source = GeneralUtility::getFileAbsFileName($source);
        }

        return PathUtility::getAbsoluteWebPath($source);
    }

    /**
     * Get path to page tree item icon.
     *
     * @throws \Exception
     */
    protected function getTreeItemIconOverlayPath(array $row): string
    {
        $overlayIdentifier = $this->iconFactory->getIconForRecord('pages', $row)->getOverlayIcon();
        $overlayPath = '';

        if ($overlayIdentifier) {
            $iconConfiguration = $this->iconRegistry->getIconConfigurationByIdentifier(
                $overlayIdentifier->getIdentifier()
            );
            $source = $iconConfiguration['options']['source'];

            if (strpos($source, 'EXT:') === 0 || strpos($source, '/') !== 0) {
                $source = GeneralUtility::getFileAbsFileName($source);
            }

            $overlayPath = PathUtility::getAbsoluteWebPath($source);
        }

        return $overlayPath;
    }

    /**
     * Get array of mount points
     * For admins only to get all root pages.
     */
    protected function getBEUserMounts(): array
    {
        /** @var FrontendBackendUserAuthentication $beUSER */
        $beUSER = $GLOBALS['BE_USER'];
        // Remove mountpoint if explicitly set in options.hideRecords.pages or is active
        $hideList = [$this->typoScriptFrontendController->rootLine[0]['uid']];
        $mounts = [];

        // If it's admin, return all root pages
        if ($beUSER->isAdmin()) {
            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
            $mounts = $queryBuilder
                ->select('uid', 'title')
                ->from('pages')
                ->where(
                    $queryBuilder->expr()->eq(
                        'is_siteroot',
                        $queryBuilder->createNamedParameter(true, \PDO::PARAM_BOOL)
                    ),
                    $queryBuilder->expr()->notIn(
                        'uid',
                        $queryBuilder->createNamedParameter($hideList, Connection::PARAM_INT_ARRAY)
                    )
                )
                ->orderBy('sorting')
                ->execute()
                ->fetchAll();
        } else {
            $allowedMounts = $beUSER->returnWebmounts();

            $hideRecordsPages = isset( $beUSER->getTSConfig()['options.']['hideRecords.']['pages']) ?  $beUSER->getTSConfig()['options.']['hideRecords.']['pages'] : [];

            if ($pidList = $hideRecordsPages) {
                $hideList += GeneralUtility::intExplode(',', $pidList, true);
            }

            $allowedMounts = array_diff($allowedMounts, $hideList);

            if (!empty($allowedMounts)) {
                /** @var QueryBuilder $queryBuilder */
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
                $mounts = $queryBuilder
                    ->select('uid', 'title')
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->in(
                            'uid',
                            $queryBuilder->createNamedParameter($allowedMounts, Connection::PARAM_INT_ARRAY)
                        )
                    )
                    ->orderBy('sorting')
                    ->execute()
                    ->fetchAll();
            }
        }

        $configurationManager = GeneralUtility::makeInstance(
            ConfigurationManager::class
        );
        $settings = $configurationManager->getConfiguration($configurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $domainTypoScriptValue = $settings['plugin.']['tx_frontend_editing.']['settings.']['domain.'];
        $new_key = max(array_keys($domainTypoScriptValue));
        $domainValueTs = $domainTypoScriptValue[$new_key];

        // Populate mounts with domains
        foreach ($mounts as $uid => &$mount) {
            $mount['domain'] = $domainValueTs;
        }

        return $mounts;
    }

    /**
     * Get the available content elements from TYPO3 backend.
     */
    protected function getContentItems(): array
    {

        $configurationManager = GeneralUtility::makeInstance(
            ConfigurationManager::class
        );

        $settings = $configurationManager->getConfiguration($configurationManager::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $settings = $settings['plugin.']['tx_frontend_editing.']['settings.'];
        $enableExcludeStartWithValue = $settings["enableExcludeStartWithValue."];
        $new_key = max(array_keys($enableExcludeStartWithValue));
        $enableExcludeStartWith = $enableExcludeStartWithValue[$new_key];

        $excludeStartWithValue = $settings["excludeStartWithValue."];
        $new_key = max(array_keys($excludeStartWithValue));
        $startWithValue = $excludeStartWithValue[$new_key];

        $startExclusion = $startWithValue;
        $contentController = $this->getNewContentElementController();

        $wizardItems = $contentController->publicGetWizards();

        $contentItems = [];
        $wizardTabKey = '';
        foreach ($wizardItems as $wizardKey => $wizardItem) {
            if (str_starts_with($wizardKey, 'common') || str_starts_with($wizardKey, 'container')) {
                if (isset($wizardItem['header'])) {
                    $wizardTabKey = $wizardKey;
                    $contentItems[$wizardTabKey]['description'] = $wizardItem['header'];
                } else {
                    

                    if(!$enableExcludeStartWith){
                        $contentItems[$wizardTabKey]['items'][] = array_merge(
                            $wizardItem,
                            [
                                'iconHtml' => $this->iconFactory->getIcon($wizardItem['iconIdentifier'])->render(),
                            ]
                        );
                    }

                    else {
                        if(!str_starts_with($wizardItem["iconIdentifier"], $startExclusion)){
                            $contentItems[$wizardTabKey]['items'][] = array_merge(
                                $wizardItem,
                                [
                                    'iconHtml' => $this->iconFactory->getIcon($wizardItem['iconIdentifier'])->render(),
                                ]
                            );
                        }
                    }
                    
                }
            }
        }
        return $contentItems;
    }

    /**
     * Call registered hooks to manipulate wizard items.
     *
     * @param array &$wizardItems
     */
    protected function wizardItemsHook(array &$wizardItems, NewContentElementController $contentController)
    {
        $newContentElement = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms']['db_new_content_el'];
        // Wrapper for wizards
        // Hook for manipulating wizardItems, wrapper, onClickEvent etc.
        if (is_array($newContentElement['wizardItemsHook'])) {
            foreach ($newContentElement['wizardItemsHook'] as $classData) {
                $hookObject = GeneralUtility::makeInstance($classData);
                if (!$hookObject instanceof NewContentElementWizardHookInterface) {
                    throw new \UnexpectedValueException(
                        $classData.' must implement interface '.NewContentElementWizardHookInterface::class,
                        1227834741
                    );
                }

                $hookObject->manipulateWizardItems($wizardItems, $contentController);
            }
        }
    }


    /**
     * Get the content elements on the page.
     *
     * @param int $pageId The page id to fetch content elements from
     */
    protected function getContentElementsOnPage(int $pageId): array
    {

        if (!$this->typoScriptFrontendController->cObj instanceof ContentObjectRenderer) {
            $this->typoScriptFrontendController->newCObj();
        }
        

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($this->ttContent);
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);

        $contentElements = $queryBuilder->select('*')->from($this->ttContent)->where(
            $queryBuilder->expr()->like('pid', $pageId),
        )->execute()->fetchAll();


        foreach ($contentElements as &$contentElement) {
            $contentElement['_recordTitle'] = BackendUtility::getRecordTitle('tt_content', $contentElement);
        }

        return $contentElements;
    }

    /**
     * Get custom records defined in TypoScript.
     */
    protected function getCustomRecords(): array
    {
        $records = [];
        $configuration = $this->getPluginConfiguration();
        if (isset($configuration['customRecords']) && is_array($configuration['customRecords'])) {
            /** @var ContentEditableWrapperService $wrapperService */
            $wrapperService = GeneralUtility::makeInstance(ContentEditableWrapperService::class);
            foreach ($configuration['customRecords'] as $record) {
                $pid = (int) $record['pid'] ?: $this->typoScriptFrontendController->id;
                $page = BackendUtility::getRecord('pages', $pid);
                if (is_array($record) &&
                    $record['table'] &&
                    isset($GLOBALS['TCA'][$record['table']]) &&
                    $GLOBALS['BE_USER']->check('tables_modify', $record['table']) &&
                    $page && $GLOBALS['BE_USER']->doesUserHaveAccess($page, 16)
                ) {
                    $records[] = [
                        'title' => $GLOBALS['TCA'][$record['table']]['ctrl']['title'],
                        'table' => $record['table'],
                        'url' => $wrapperService->renderEditOnClickReturnUrl($wrapperService->renderNewUrl(
                            $record['table'],
                            (int) $pid,
                            0,
                            is_array($record['defVals']) ? $record['defVals'] : [],
                            true
                        )),
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * Initializes the Fluid standalone view with the paths etc.
     */
    protected function initializeView(): StandaloneView
    {
        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $renderingContext = $view->getRenderingContext();
        $renderingContext->getTemplatePaths()->fillDefaultsByPackageName('frontend_editing');
        $renderingContext->setControllerName('Toolbars');
        $renderingContext->setControllerAction('Toolbars');

        return $view;
    }

    /**
     * Generate Be user session key to transfer between domains.
     */
    protected function getBeSessionKey(): string
    {
        return rawurlencode(
            $GLOBALS['BE_USER']->id.
            '-'.
            md5(
                $GLOBALS['BE_USER']->id.
                '/'.
                $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey']
            )
        );
    }

    /**
     * Get the SEO data based on chosen Provider from
     * Extension Manager settings.
     */
    protected function getSeoProviderData(int $pageId): array
    {
        $providerData = [];
        $settings = ExtensionManagerConfigurationService::getSettings();
        if (isset($settings['seoProvider']) && $settings['seoProvider'] !== 'none') {
            $extensionIsLoaded = ExtensionManagementUtility::isLoaded($settings['seoProvider']);
            if ($extensionIsLoaded === true) {
                if ($settings['seoProvider'] === 'cs_seo') {
                    $seoProvider = GeneralUtility::makeInstance(CsSeoProvider::class);
                    $providerData = $seoProvider->getSeoScores($pageId);
                }
            }
        }

        return $providerData;
    }

    /**
     * Get plugin configuration from TypoScript.
     */
    protected function getPluginConfiguration(): array
    {
        if (!$this->pluginConfiguration) {
            /** @var TypoScriptService $typoScriptService */
            $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
            $configuration = $typoScriptService->convertTypoScriptArrayToPlainArray(
                $this->typoScriptFrontendController->tmpl->setup
            );
            if (is_array($configuration['plugin']['tx_frontendediting'])) {
                $this->pluginConfiguration = $configuration['plugin']['tx_frontendediting'];
            }
        }

        return $this->pluginConfiguration;
    }

    /**
     * Get if to display hidden items or not in the rendering.
     */
    protected function showHiddenItems(): int
    {
        $defaultState = 1;
        $showHiddenItems = 0;
        if (GeneralUtility::_GET('show_hidden_items')) {
            $showHiddenItems = GeneralUtility::_GET('show_hidden_items');
            if ($showHiddenItems !== $defaultState) {
                $cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
                $cacheManager->flushCaches();
            }
        }

        $showHiddenItems = ($showHiddenItems === $defaultState) ? 0 : $defaultState;

        return $showHiddenItems;
    }

    /**
     * Return instance of NewContentElementController
     * For TYPO3 >= 9 init NewContentElementController with given server request.
     *
     * @return NewContentElementController|\TYPO3\CMS\FrontendEditing\Backend\Controller\ContentElement\NewContentElementController
     */
    protected function getNewContentElementController()
    {
        $contentController = $this->contentController;

        return $contentController;
    }

    /**
     * Simulate request with "id" and "sys_language_uid" parameters for NewContentElementController.
     */
    protected function requestWithSimulatedQueryParams(): \Psr\Http\Message\ServerRequestInterface
    {
        $languageUid = GeneralUtility::makeInstance(Context::class)->getAspect('language')->getId();

        // @extensionScannerIgnoreLine
        return $GLOBALS['TYPO3_REQUEST']->withQueryParams([
            'id' => (int) $this->typoScriptFrontendController->id,
            'sys_language_uid' => $languageUid,
        ]);
    }
}
