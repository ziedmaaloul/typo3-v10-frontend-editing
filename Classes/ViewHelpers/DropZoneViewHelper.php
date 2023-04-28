<?php

namespace TYPO3\CMS\FrontendEditing\ViewHelpers;

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

use TYPO3\CMS\FrontendEditing\Service\ContentEditableWrapperService;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

class DropZoneViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Disable the escaping of children.
     *
     * @var bool
     */
    protected $escapeChildren = false;

    /**
     * Disable that the content itself isn't escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initialize arguments.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();

        $this->registerArgument(
            'colPos',
            'int',
            0,
            false,
        );
        $this->registerArgument(
            'uid',
            'int',
            0,
            false,
        );
        $this->registerArgument(
            'table',
            'string',
            'tt_content',
            false,
        );
        $this->registerArgument(
            'defaultValues',
            'array',
            [],
            false,
        );
        $this->registerArgument(
            'parentUid',
            'int',
            0,
            false,
        );
    }

    /**
     * Add a content-editable div around the content.
     *
     * @return string Rendered email link
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $colPos = isset($arguments['colPos']) && $arguments['colPos'] > 0 ? $arguments['colPos'] : 0;
        $parentUid = isset($arguments['parentUid']) && $arguments['parentUid'] > 0 ? $arguments['parentUid'] : 0;

        $uid = isset($arguments['uid']) && $arguments['uid'] > 0 ? $arguments['uid'] : 0;
        $table = isset($arguments['table']) && $arguments['table'] !== '' ? $arguments['table'] : 'tt_content';
        $defaultValues = isset($arguments['defaultValues']) && $arguments['defaultValues'] !== null ? $arguments['defaultValues'] : [];
        $contentEditableWrapperService = new  ContentEditableWrapperService();

        if ($colPos) {
            $defaultValues['colPos'] = $colPos;
        }
        if ($parentUid) {
            $defaultValues['tx_container_parent'] = $parentUid;
        }

        $url = $contentEditableWrapperService->renderEditOnClickReturnUrl($contentEditableWrapperService->renderNewUrl($table, (int) $uid, (int) $colPos, $defaultValues));

        return '<div class="t3-frontend-editing__dropzone" ondrop="window.parent.F.dropCe(event)"
        ondragover="window.parent.F.dragCeOver(event)" ondragleave="window.parent.F.dragCeLeave(event)"
        data-new-url="'.$url.'"
        data-colpos="'.$colPos.'" data-defvals="[]"></div>';
    }
}
