# TYPO3 frontend editing

[![TYPO3](https://img.shields.io/badge/TYPO3-11-orange.svg?style=flat-square)](https://typo3.org/) [![TYPO3](https://img.shields.io/badge/TYPO3-10-orange.svg?style=flat-square)](https://typo3.org/) [![TYPO3](https://img.shields.io/badge/TYPO3-9-orange.svg?style=flat-square)](https://typo3.org/) [![Build Status](https://travis-ci.org/FriendsOfTYPO3/frontend_editing.svg?branch=master)](https://travis-ci.org/FriendsOfTYPO3/frontend_editing) 

## TYPO3 frontend editing (frontend_editing)
Extended From [Original Extension](https://docs.typo3.org/p/friendsoftypo3/frontend-editing/master/en-us/)
 This package gives frontend editing capability to TYPO3 CMS, the editor used is [Ckeditor](http://ckeditor.com/).

## Documentation

For all kind of documentation which covers install to how to develop the extension:

[Local Documentation](Documentation/Index.rst)

[Online Documentation](https://docs.typo3.org/p/friendsoftypo3/frontend-editing/master/en-us/)

[Donate to the Frontend Editing for TYPO3 project](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=WPXRSUTAJNRES&source=url)

### To Add JS / CSS Files To Front End Editing
    plugin.tx_frontend_editing {
		settings {
			enableDefaultRightBar.50 = 10
			cssFiles{
				10 = EXT:extension/Resources/Public/Css/Backend/frontend_editing_override.css
			}
			jsFiles{
				10 = EXT:extension/Resources/Public/Css/Backend/frontend_editing_override.js
			}
		}
	}
enableDefaultRightBar is To enable or Disable the default style