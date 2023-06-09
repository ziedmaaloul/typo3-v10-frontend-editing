[backend.user.isLoggedIn && request.getQueryParams()['frontend_editing'] == true]

lib.fluidContent {
    stdWrap {
        editIcons = tt_content:header
    }
}

lib.contentElement.stdWrap < lib.fluidContent.stdWrap

tt_content.bullets.stdWrap < lib.fluidContent.stdWrap
tt_content.div.stdWrap < lib.fluidContent.stdWrap
tt_content.header.stdWrap < lib.fluidContent.stdWrap
tt_content.html.stdWrap < lib.fluidContent.stdWrap
tt_content.image.stdWrap < lib.fluidContent.stdWrap
tt_content.list.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_abstract.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_categorized_pages.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_pages.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_recently_updated.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_related_pages.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_section.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_section_pages.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_sitemap.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_sitemap_pages.stdWrap < lib.fluidContent.stdWrap
tt_content.menu_subpages.stdWrap < lib.fluidContent.stdWrap
tt_content.shortcut.stdWrap < lib.fluidContent.stdWrap
tt_content.table.stdWrap < lib.fluidContent.stdWrap
tt_content.text.stdWrap < lib.fluidContent.stdWrap
tt_content.textmedia.stdWrap < lib.fluidContent.stdWrap
tt_content.textpic.stdWrap < lib.fluidContent.stdWrap
tt_content.uploads.stdWrap < lib.fluidContent.stdWrap
tt_content.mailform.stdWrap < lib.fluidContent.stdWrap



plugin.tx_frontend_editing {
    settings {
        enableDefaultRightBar.10 = 1
        enableExcludeStartWithValue.10 = 0
        excludeStartWithValue.10 = content-
        domain.10 = http://fe.local/
        defaultColors{
            primaryColor.0=#0089C5
            secondaryColor.0=#A3D10C
        }
        cssFiles{
            1 = EXT:frontend_editing/Resources/Public/Css/frontend_editing.css
            2 = EXT:backend/Resources/Public/Css/backend.css
        }
        jsFiles{
            1 = EXT:core/Resources/Public/JavaScript/Contrib/jquery/jquery.js
        }
        dropzoneDefaultParams {
            tx_gridelements_container = 0
            tx_gridelements_columns = 0
        }
    }
}



config {
    tx_extbase {
        objects {
            TYPO3\CMS\Extbase\Mvc\View\NotFoundView.className = TYPO3\CMS\FrontendEditing\Mvc\View\NotFoundView
        }
    }
    tx_frontendediting {
        # These transformations are applied to the page being edited to ensure features work as expected and inceptions
        # are avoided.
        pageContentPreProcessing {
            parseFunc {
                tags {
                    form = TEXT
                    form {
                        current = 1

                        # Add frontend_editing=true if this is a GET form (rather than POST)
                        innerWrap = <input type="hidden" name="frontend_editing" value="true">|
                            innerWrap.if {
                            value.data = parameters : method
                            value.case = lower
                            equals = get
                        }

                        dataWrap = <form { parameters : allParams }>|</form>
                    }
                }
            }

            HTMLparser = 1
            HTMLparser {
                keepNonMatchedTags = 1

                tags {
                    a.fixAttrib {
                        href.userFunc = TYPO3\CMS\FrontendEditing\UserFunc\HtmlParserUserFunc->removeFrontendEditingInUrl

                        target.list = _self
                    }

                    form.fixAttrib {
                        action.userFunc = TYPO3\CMS\FrontendEditing\UserFunc\HtmlParserUserFunc->addFrontendEditingInUrl

                        target.list = _self
                    }
                }
            }
        }

        customRecordEditing {
            tx_news_pi1 {
                actionName = detail
                recordName = news
                tableName = tx_news_domain_model_news
                listTypeName = news_pi1
            }
        }
    }
}

# Prevent links from being parsed to FE url
lib.parseFunc_RTE.tags.a >

# Disable spam protection
config.spamProtectEmailAddresses = 0

[global]
