<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2011 Kasper Skårhøj (kasperYYYY@typo3.com)
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Generate a folder tree
 *
 * Revised for TYPO3 3.6 November/2003 by Kasper Skårhøj
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @coauthor	René Fritz <r.fritz@colorcube.de>
 */


/**
 * Extension class for the t3lib_treeView class, specially made for browsing folders in the File module
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @coauthor	René Fritz <r.fritz@colorcube.de>
 * @package TYPO3
 * @subpackage t3lib
 * @see class t3lib_treeView
 */
class t3lib_folderTree extends t3lib_treeView {

	/**
	 * @var t3lib_file_storage
	 * the users' file Storages
	 */
	protected $storages = NULL;

	protected $ajaxStatus = FALSE; // Indicates, whether the ajax call was successful, i.e. the requested page has been found

	/**
	 * Constructor function of the class
	 *
	 * @return	void
	 */
	public function __construct() {
		parent::init();

		$this->storages = $GLOBALS['BE_USER']->getFileStorages();

		$this->treeName = 'folder';
		$this->titleAttrib = ''; //don't apply any title
		$this->domIdPrefix = 'folder';
	}

	/**
	 * Compatibility constructor.
	 *
	 * @deprecated since TYPO3 4.6 and will be removed in TYPO3 4.8. Use __construct() instead.
	 */
	public function t3lib_folderTree() {
		t3lib_div::logDeprecatedFunction();
			// Note: we cannot call $this->__construct() here because it would call the derived class constructor and cause recursion
			// This code uses official PHP behavior (http://www.php.net/manual/en/language.oop5.basic.php) when $this in the
			// statically called non-static method inherits $this from the caller's scope.
		t3lib_folderTree::__construct();
	}

	/**
	 * Generate the plus/minus icon for the browsable tree.
	 *
	 * @param	array		record for the entry
	 * @param	integer		The current entry number
	 * @param	integer		The total number of entries. If equal to $a, a "bottom" element is returned.
	 * @param	integer		The number of sub-elements to the current element.
	 * @param	boolean		The element was expanded to render subelements if this flag is set.
	 * @return	string		Image tag with the plus/minus icon.
	 * @access private
	 * @see t3lib_pageTree::PMicon()
	 */
	public function PMicon($folderObject, $subFolderCounter, $totalSubFolders, $nextCount, $isExpanded) {
		$PM   = $nextCount ? ($isExpanded ? 'minus' : 'plus') : 'join';
		$BTM  = ($subFolderCounter == $totalSubFolders) ? 'bottom' : '';
		$icon = '<img' . t3lib_iconWorks::skinImg($this->backPath, 'gfx/ol/' . $PM . $BTM . '.gif','width="18" height="16"') . ' alt="" />';

		if ($nextCount) {
			$cmd = $this->generateExpandCollapseParameter($this->bank, !$isExpanded, $folderObject);
			$icon = $this->PMiconATagWrap($icon, $cmd, !$isExpanded);
		}
		return $icon;
	}


	/**
	 * Wrap the plus/minus icon in a link
	 *
	 * @param	string		HTML string to wrap, probably an image tag.
	 * @param	string		Command for 'PM' get var
	 * @return	string		Link-wrapped input string
	 * @access private
	 */
	public function PMiconATagWrap($icon, $cmd, $isExpand = TRUE) {
		if ($this->thisScript) {
				// activate dynamic ajax-based tree
			$js = htmlspecialchars('Tree.load(\'' . $cmd . '\', ' . intval($isExpand) . ', this);');
			return '<a class="pm" onclick="' . $js . '">' . $icon . '</a>';
		} else {
			return $icon;
		}
	}

	/**
	 * Wrapping the folder icon
	 *
	 * @param	string		The image tag for the icon
	 * @param	array		The row for the current element
	 * @return	string		The processed icon input value.
	 * @access private
	 */
	function wrapIcon($icon, $folderObject) {
			// Add title attribute to input icon tag
		$theFolderIcon = $this->addTagAttributes($icon, ($this->titleAttrib ? $this->titleAttrib . '="' . $this->getTitleAttrib($folderObject) . '"' : ''));

			// Wrap icon in click-menu link.
		if (!$this->ext_IconMode) {
			$theFolderIcon = $GLOBALS['TBE_TEMPLATE']->wrapClickMenuOnIcon($theFolderIcon, $folderObject->getCombinedIdentifier(), '', 0);
		} elseif (!strcmp($this->ext_IconMode, 'titlelink')) {
			$aOnClick = 'return jumpTo(\'' . $this->getJumpToParam($folderObject) . '\',this,\'' . $this->domIdPrefix . $this->getId($folderObject) . '\',' . $this->bank . ');';
			$theFolderIcon = '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $theFolderIcon . '</a>';
		}
		return $theFolderIcon;
	}

	/**
	 * Wrapping $title in a-tags.
	 *
	 * @param	string		Title string
	 * @param	t3lib_file_Folder	$folderObject the folder record
	 * @param	integer		Bank pointer (which mount point number)
	 * @return	string
	 * @access private
	 */
	function wrapTitle($title, $folderObject, $bank = 0) {
		$aOnClick = 'return jumpTo(\'' . $this->getJumpToParam($folderObject) . '\', this, \'' . $this->domIdPrefix . $this->getId($folderObject) . '\', ' . $bank . ');';
		$CSM = ' oncontextmenu="'.htmlspecialchars($GLOBALS['TBE_TEMPLATE']->wrapClickMenuOnIcon('', $folderObject->getCombinedIdentifier(), '', 0, '&bank=' . $this->bank, '', TRUE)) . '"';
		return '<a href="#" title="' . htmlspecialchars($title) . '" onclick="' . htmlspecialchars($aOnClick) . '"' . $CSM . '>' . $title . '</a>';
	}

	/**
	 * Returns the id from the record - for folders, this is an md5 hash.
	 *
	 * @param	t3lib_file_Folder		The folder object
	 * @return	integer		The "uid" field value.
	 */
	public function getId($folderObject) {
		return t3lib_div::md5Int($folderObject->getCombinedIdentifier());
	}

	/**
	 * Returns jump-url parameter value.
	 *
	 * @param	t3lib_file_Folder	The folder object
	 * @return	string	The jump-url parameter.
	 */
	public function getJumpToParam($folderObject) {
		return rawurlencode($folderObject->getCombinedIdentifier());
	}

	/**
	 * Returns the title for the input record. If blank, a "no title" labele (localized) will be returned.
	 * '_title' is used for setting an alternative title for folders.
	 *
	 * @param	array		The input row array (where the key "_title" is used for the title)
	 * @param	integer		Title length (30)
	 * @return	string		The title.
	 */
	public function getTitleStr($row, $titleLen = 30) {
		return $row['_title'] ? $row['_title'] : parent::getTitleStr($row, $titleLen);
	}

	/**
	 * Will create and return the HTML code for a browsable tree of folders.
	 * Is based on the mounts found in the internal array ->MOUNTS (set in the constructor)
	 *
	 * @return	string		HTML code for the browsable tree
	 */
	public function getBrowsableTree() {

			// Get stored tree structure AND updating it if needed according to incoming PM GET var.
		$this->initializePositionSaving();

			// Init done:
		$treeItems = array();

			// Traverse mounts:
		foreach ($this->storages as $storageObject) {
			$this->getBrowseableTreeForStorage($storageObject);

				// Add tree:
			$treeItems = array_merge($treeItems, $this->tree);


				// if this is an AJAX call, don't run through all mounts, only
				// show the expansion of the current one, not the rest of the mounts
			if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_AJAX) {
				// @todo: currently the AJAX script runs through all storages thus, if something is expanded on storage #2, it does not work, the break stops this, the goal should be that only the $this->storages iterates over the selected storage/bank
				// break;
			}
		}

		return $this->printTree($treeItems);
	}


	/**
	 * internal function to get a tree for one storage
	 * separated so it's easier to use this functionality in subclasses
	 *
	 * @param $storageObject
	 *
	 */
	public function getBrowseableTreeForStorage($storageObject) {
		
			// if there are filemounts, show each, otherwise just the rootlevel folder
		$fileMounts = $storageObject->getFileMounts();
		$rootLevelFolders = array();
		if (count($fileMounts)) {
			foreach ($fileMounts as $fileMountInfo) {
				$rootLevelFolders[] = array(
					'folder' => $fileMountInfo['folder'],
					'name' => $fileMountInfo['title']
				);
			}
		} else {
			$rootLevelFolders[] = array(
				'folder' => $storageObject->getRootLevelFolder(),
				'name' => $storageObject->getName()
			);
		}

			// clean the tree
		$this->reset();
		
			// go through all "root level folders" of this tree (can be the rootlevel folder or any file mount points)
		foreach ($rootLevelFolders as $rootLevelFolderInfo) {
			$rootLevelFolder = $rootLevelFolderInfo['folder'];
			$rootLevelFolderName = $rootLevelFolderInfo['name'];
			$folderHashSpecUID = t3lib_div::md5int($rootLevelFolder->getCombinedIdentifier());
			$this->specUIDmap[$folderHashSpecUID] = $rootLevelFolder->getCombinedIdentifier();

				// hash key
			$storageHashNumber = $this->getShortHashNumberForStorage($storageObject, $rootLevelFolder);

				// Set first:
			$this->bank = $storageHashNumber;
			$isOpen = $this->stored[$storageHashNumber][$folderHashSpecUID] || $this->expandFirst;


				// Set PM icon:
			$cmd = $this->generateExpandCollapseParameter($this->bank, !$isOpen, $rootLevelFolder);
			$icon = '<img' . t3lib_iconWorks::skinImg($this->backPath, 'gfx/ol/' . ($isOpen ? 'minus' : 'plus') . 'only.gif') . ' alt="" />';
			$firstHtml = $this->PM_ATagWrap($icon, $cmd);

				// @todo: check the type
				// @todo: create sprite icons for user/group mounts, readonly mounts etc
			switch ($val['type']) {
				case 'user':
					$icon = 'gfx/i/_icon_ftp_user.gif';
					$icon = 'apps-filetree-root';
				break;
				case 'group':
					$icon = 'gfx/i/_icon_ftp_group.gif';
					$icon = 'apps-filetree-root';
				break;
				case 'readonly':
					$icon = 'gfx/i/_icon_ftp_readonly.gif';
					$icon = 'apps-filetree-root';
				break;
				default:
					$icon = 'gfx/i/_icon_ftp.gif';
					$icon = 'apps-filetree-root';
				break;
			}

				// Preparing rootRec for the mount
			$firstHtml .= $this->wrapIcon(t3lib_iconWorks::getSpriteIcon($icon), $rootLevelFolder);
			$row = array(
				'uid'    => $folderHashSpecUID,
				'title'  => $rootLevelFolderName,
				'path'   => $rootLevelFolder->getCombinedIdentifier(),
				'folder' => $rootLevelFolder
			);

				// Add the storage root to ->tree
			$this->tree[] = array(
				'HTML'   => $firstHtml,
				'row'    => $row,
				'bank'   => $this->bank,
					// hasSub is TRUE when the root of the storage is expanded
				'hasSub' => ($isOpen ? TRUE : FALSE)
			);

				// If the mount is expanded, go down:
			if ($isOpen) {
					// Set depth:
				$depthD = '<img' . t3lib_iconWorks::skinImg($this->backPath, 'gfx/ol/blank.gif', 'width="18" height="16"') . ' alt="" />';
				$this->getFolderTree($rootLevelFolder, 999, $val['type']);
			}
		}

	}


	/**
	 * Fetches the data for the tree
	 *
	 * @param	t3lib_file_Folder $folderObject	the folderobject
	 * @param	integer		Max depth (recursivity limit)
	 * @param	string		HTML-code prefix for recursive calls.
	 * @return	integer		The count of items on the level
	 * @see getBrowsableTree()
	 */
	public function getFolderTree($folderObject, $depth = 999, $type = '') {
		$depth = intval($depth);

			// This generates the directory tree
		$subFolders = $folderObject->getSubfolders();

		sort($subFolders);
		$totalSubFolders = count($subFolders);

		$HTML = '';
		$subFolderCounter = 0;

		foreach ($subFolders as $subFolder) {
			$subFolderCounter++;
				// Reserve space.
			$this->tree[] = array();
				// Get the key for this space
			end($this->tree);
			$treeKey = key($this->tree);

			$specUID = t3lib_div::md5int($subFolder->getCombinedIdentifier());
			$this->specUIDmap[$specUID] = $subFolder->getCombinedIdentifier();

			$row = array(
				'uid'    => $specUID,
				'path'   => $subFolder->getCombinedIdentifier(),
				'title'  => $subFolder->getName(),
				'folder' => $subFolder
			);

				// Make a recursive call to the next level
			if ($depth > 1 && $this->expandNext($specUID)) {
				$nextCount = $this->getFolderTree(
					$subFolder,
					$depth-1,
					$type
				);

					// Set "did expand" flag
				$isOpen = 1;

			} else {

				$nextCount = $this->getNumberOfSubfolders($subFolder);
					// Clear "did expand" flag
				$isOpen = 0;
			}

				// Set HTML-icons, if any:
			if ($this->makeHTML) {
				$HTML = $this->PMicon($subFolder, $subFolderCounter, $totalSubFolders, $nextCount, $isOpen);
				if ($subFolder->checkActionPermission('write')) {
					$type = '';
					$overlays = array();
				} else {
					$type = 'readonly';
					$overlays = array('status-overlay-locked' => array());
				}

				if ($isOpen) {
					$icon = 'apps-filetree-folder-opened';
				} else {
					$icon = 'apps-filetree-folder-default';
				}

				if ($subFolder->getIdentifier() == '_temp_') {
					$icon = 'apps-filetree-folder-temp';
					$row['title'] = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xml:temp', TRUE);
					$row['_title'] = '<strong>' . $row['title'] . '</strong>';
				}
				if ($subFolder->getIdentifier() == '_recycler_') {
					$icon = 'apps-filetree-folder-recycler';
					$row['title'] = $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_file_list.xml:recycler', TRUE);
					$row['_title'] = '<strong>' . $row['title'] . '</strong>';
				}
				$icon = t3lib_iconWorks::getSpriteIcon($icon, array('title' => $subFolder->getIdentifier()), $overlays);
				$HTML .= $this->wrapIcon($icon, $subFolder);
			}

				// Finally, add the row/HTML content to the ->tree array in the reserved key.
			$this->tree[$treeKey] = array(
				'row'    => $row,
				'HTML'   => $HTML,
				'hasSub' => $nextCount && $this->expandNext($specUID),
				'isFirst'=> ($subFolderCounter == 1),
				'isLast' => FALSE,
				'invertedDepth'=> $depth,
				'bank'   => $this->bank
			);
		}

		if ($subFolderCounter > 0) {
			$this->tree[$treeKey]['isLast'] = TRUE;
		}
		return $totalSubFolders;

	}

	/**
	 * Compiles the HTML code for displaying the structure found inside the ->tree array
	 *
	 * @param	array		"tree-array" - if blank string, the internal ->tree array is used.
	 * @return	string		The HTML code for the tree
	 */
	function printTree($treeItems='') {
		$titleLength = intval($this->BE_USER->uc['titleLen']);
		if (!is_array($treeItems)) {
			$treeItems = $this->tree;
		}

		$out = '
			<!-- TYPO3 folder tree structure. -->
			<ul class="tree" id="treeRoot">
		';

			// -- evaluate AJAX request
		if (TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_AJAX) {
			list(, $expandCollapseCommand, $expandedFolderHash, ) = $this->evaluateExpandCollapseParameter();
			if ($expandCollapseCommand == 1) {
				$ajaxOutput = '';
					// We don't know yet. Will be set later.
				$invertedDepthOfAjaxRequestedItem = 0;
				$doExpand = TRUE;
			} else	{
				$doCollapse = TRUE;
			}
		}


		// we need to count the opened <ul>'s every time we dig into another level,
		// so we know how many we have to close when all children are done rendering
		$closeDepth = array();

		foreach ($treeItems as $treeItem) {
			$folderObject = $treeItem['row']['folder'];
			$classAttr = $treeItem['row']['_CSSCLASS'];
			$folderIdentifier = $folderObject->getCombinedIdentifier();
				// this is set if the AJAX request has just opened this folder (via the PM command)
			$isExpandedFolderIdentifier = ($expandedFolderHash == t3lib_div::md5int($folderIdentifier));
			$idAttr	= htmlspecialchars($this->domIdPrefix . $this->getId($folderObject) . '_' . $treeItem['bank']);
			$itemHTML  = '';

			// if this item is the start of a new level,
			// then a new level <ul> is needed, but not in ajax mode
			if($treeItem['isFirst'] && !($doCollapse) && !($doExpand && $isExpandedFolderIdentifier)) {
				$itemHTML = "<ul>\n";
			}

			// add CSS classes to the list item
			if ($treeItem['hasSub']) { $classAttr .= ' expanded'; }
			if ($treeItem['isLast']) { $classAttr .= ' last';  }

			$itemHTML .='
				<li id="' . $idAttr . '" ' .($classAttr ? ' class="' . trim($classAttr) . '"' : '').'><div class="treeLinkItem">'.
					$treeItem['HTML'].
					$this->wrapTitle($this->getTitleStr($treeItem['row'], $titleLength), $folderObject, $treeItem['bank']) . '</div>';

			if (!$treeItem['hasSub']) {
				$itemHTML .= "</li>\n";
			}

			// we have to remember if this is the last one
			// on level X so the last child on level X+1 closes the <ul>-tag
			if ($treeItem['isLast'] && !($doExpand && $isExpandedFolderIdentifier)) {
				$closeDepth[$treeItem['invertedDepth']] = 1;
			}


			// if this is the last one and does not have subitems, we need to close
			// the tree as long as the upper levels have last items too
			if ($treeItem['isLast'] && !$treeItem['hasSub'] && !$doCollapse && !($doExpand && $isExpandedFolderIdentifier)) {
				for ($i = $treeItem['invertedDepth']; $closeDepth[$i] == 1; $i++) {
					$closeDepth[$i] = 0;
					$itemHTML .= "</ul></li>\n";
				}
			}

				// ajax request: collapse
			if ($doCollapse && $isExpandedFolderIdentifier) {
				$this->ajaxStatus = TRUE;
				return $itemHTML;
			}

				// ajax request: expand
			if ($doExpand && $isExpandedFolderIdentifier) {
				$ajaxOutput .= $itemHTML;
				$invertedDepthOfAjaxRequestedItem = $treeItem['invertedDepth'];
			} elseif ($invertedDepthOfAjaxRequestedItem) {
				if ($treeItem['invertedDepth'] < $invertedDepthOfAjaxRequestedItem) {
					$ajaxOutput .= $itemHTML;
				} else {
					$this->ajaxStatus = TRUE;
					return $ajaxOutput;
				}
			}

			$out .= $itemHTML;
		}

			// if this is a AJAX request, output directly
		if ($ajaxOutput) {
			$this->ajaxStatus = TRUE;
			return $ajaxOutput;
		}

			// finally close the first ul
		$out .= "</ul>\n";
		return $out;
	}



	/**
	 * Counts the number of directories in a file path.
	 *
	 * @param	string		File path.
	 * @return	integer
	 * @deprecated since TYPO3 4.7, as the folder objects do the counting automatically
	 */
	public function getCount($file) {
		t3lib_div::logDeprecatedFunction();
			// This generates the directory tree
		$dirs = t3lib_div::get_dirs($file);
		$c = 0;
		if (is_array($dirs)) {
			$c = count($dirs);
		}
		return $c;
	}

	/**
	 * Counts the number of directories in a file path.
	 *
	 * @param	t3lib_file_Folder	$folderObject		File path.
	 * @return	integer
	 */
	public function getNumberOfSubfolders($folderObject) {
		$subFolders = $folderObject->getSubfolders();
		return count($subFolders);
	}

	/**
	 * Get stored tree structure AND updating it if needed according to incoming PM GET var.
	 *
	 * @return	void
	 * @access private
	 */
	function initializePositionSaving() {
			// Get stored tree structure:
		$this->stored = unserialize($this->BE_USER->uc['browseTrees'][$this->treeName]);

		$this->getShortHashNumberForStorage();

			// PM action:
			// (If an plus/minus icon has been clicked, 
			// the PM GET var is sent and we must update the stored positions in the tree):
			// 0: mount key, 1: set/clear boolean, 2: item ID (cannot contain "_"), 3: treeName
		list($storageHashNumber, $doExpand, $numericFolderHash, $treeName) = $this->evaluateExpandCollapseParameter();
		if ($treeName && $treeName == $this->treeName) {
			if (in_array($storageHashNumber, $this->storageHashNumbers)) {
				if ($doExpand == 1) {
				 		// set
					$this->stored[$storageHashNumber][$numericFolderHash] = 1;
				} else {
					 	// clear
					unset($this->stored[$storageHashNumber][$numericFolderHash]);
				}
				$this->savePosition();
			}
		}
	}
	
	/**
	 * helper function to map md5-hash to shorter number
	 *
	 * @param $storage
	 * @return integer
	 */
	protected function getShortHashNumberForStorage(t3lib_file_Storage $storageObject = NULL, t3lib_file_Folder $startingPointFolder = NULL) {
		if (!$this->storageHashNumbers) {
			$this->storageHashNumbers = array();
				// Mapping md5-hash to shorter number:
			$hashMap = array();
			foreach ($this->storages as $storageUid => $storage) {
				$fileMounts = $storage->getFileMounts();
				if (count($fileMounts)) {
					foreach ($fileMounts as $fileMount) {
						$nkey = hexdec(substr(t3lib_div::md5int($fileMount['folder']->getCombinedIdentifier()), 0, 4));
						$this->storageHashNumbers[$storageUid . $fileMount['folder']->getCombinedIdentifier()] = $nkey;
					}
				} else {
					$folder = $storage->getRootLevelFolder();
					$nkey = hexdec(substr(t3lib_div::md5int($folder>getCombinedIdentifier()), 0, 4));
					$this->storageHashNumbers[$storageUid . $folder->getCombinedIdentifier()] = $nkey;
				}
			}
		}
		if ($storageObject) {
			if ($startingPointFolder) {
				return $this->storageHashNumbers[$storageObject->getUid() . $startingPointFolder->getCombinedIdentifier()];
			} else {
				return $this->storageHashNumbers[$storageObject->getUid()];
			}
		} else {
			return NULL;
		}
	}


	/**
	 * get the values from the Expand/Collapse Parameter (&PM)
	 * previously known as "PM" (plus/minus)
	 * PM action:
	 * (If an plus/minus icon has been clicked,
	 * the PM GET var is sent and we must update the stored positions in the tree):
	 * 0: mount key, 1: set/clear boolean, 2: item ID (cannot contain "_"), 3: treeName
	 */
	protected function evaluateExpandCollapseParameter($PM = NULL) {
		if ($PM === NULL) {
			$PM = t3lib_div::_GP('PM');
				// IE takes anchor as parameter
			if (($PMpos = strpos($PM, '#')) !== FALSE) {
				$PM = substr($PM, 0, $PMpos);
			}
		}

		// take the first three parameters
		list($mountKey, $doExpand, $folderIdentifier) = explode('_', $PM, 3);

		// in case the folder identifier contains "_", we just need to get the fourth/last parameter
		list($folderIdentifier, $treeName) = t3lib_div::revExplode('_', $folderIdentifier, 2);
		return array(
			$mountKey,
			$doExpand,
			$folderIdentifier,
			$treeName
		);
	}

	/**
	 * generates the "PM" string to sent to expand/collapse items
	 *
	 * @param $mountKey	the mount key / storage UID
	 * @param $doExpand	whether to expand/collapse
	 * @param $folderObject	the folder object
	 * @param $treeName	the name of the tree
	 * @return string
	 */
	protected function generateExpandCollapseParameter($mountKey = NULL, $doExpand = NULL, $folderObject = NULL, $treeName = NULL) {
		$parts = array(
			($mountKey !== NULL ? $mountKey : $this->bank),
			($doExpand == 1 ? 1 : 0),
			($folderObject !== NULL ? t3lib_div::md5int($folderObject->getCombinedIdentifier()) : ''),
			($treeName !== NULL ? $treeName : $this->treeName)
		);
		return implode('_', $parts);
	}
}

if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_foldertree.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_foldertree.php']);
}

?>