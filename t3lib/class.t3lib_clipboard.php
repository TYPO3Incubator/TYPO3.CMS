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
 * Contains class for TYPO3 clipboard for records and files
 *
 * Revised for TYPO3 3.6 July/2003 by Kasper Skårhøj
 * XHTML compliant
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 */


/**
 * TYPO3 clipboard for records and files
 *
 * @author	Kasper Skårhøj <kasperYYYY@typo3.com>
 * @package TYPO3
 * @subpackage t3lib
 */
class t3lib_clipboard implements t3lib_Singleton {

	/**
	 * @var t3lib_clipboard_Clipboard
	 */
	protected $clipboardInstance;
	var $backPath = '';
	var $fileMode = 0; // If set, clipboard is displaying files.

	/**
	 * The name of the clipboard currently being active.
	 * (public variable is available for backward compatibility)
	 *
	 * @deprecated since TYPO3 4.7
	 * @var string
	 */
	public $current;

	/*****************************************
	 *
	 * Initialize
	 *
	 ****************************************/

	/**
	 * Initialize the clipboard from the be_user session
	 *
	 * @deprecated since TYPO3 4.7 (is part of constructor)
	 * @return	void
	 */
	public function initializeClipboard() {
		$this->backPath = $GLOBALS['BACK_PATH'];
	}

	/**
	 * Creates this object.
	 */
	public function __construct() {
		$this->backPath = $GLOBALS['BACK_PATH'];
		$this->clipboardInstance = t3lib_div::makeInstance('t3lib_clipboard_Clipboard');

		$current = $this->clipboardInstance->getActivePadId();

		if ($current >= 0) {
			$this->setCurrent($current);
		} else {
			$this->setCurrent('normal');
		}
	}

	/**
	 * Sets the current active clipboard.
	 *
	 * @param string $current
	 */
	public function setCurrent($current) {
		$this->current = $current;
	}

	/**
	 * Gets the current active clipboard.
	 *
	 * @return string
	 */
	public function getCurrent() {
		return $this->current;
	}

	/**
	 * Call this method after initialization if you want to lock the clipboard to operate on the normal pad only. Trying to switch pad through ->setCmd will not work
	 * This is used by the clickmenu since it only allows operation on single elements at a time (that is the "normal" pad)
	 *
	 * @return	void
	 */
	public function lockToNormal() {
		$this->clipboardInstance->lockToNormal();
	}

	/**
	 * The array $cmd may hold various keys which notes some action to take.
	 * Normally perform only one action at a time.
	 * In scripts like db_list.php / file_list.php the GET-var CB is used to control the clipboard.
	 *
	 *		 Selecting / Deselecting elements
	 *		 Array $cmd['el'] has keys = element-ident, value = element value (see description of clipData array in header)
	 *		 Selecting elements for 'copy' should be done by simultaneously setting setCopyMode.
	 *
	 * @param array $cmd Array of actions, see function description
	 * @return void
	 */
	public function setCmd(array $cmd) {
		if (is_array($cmd['el'])) {
			foreach ($cmd['el'] AS $element => $selected) {
				list($type, $identifier) = t3lib_div::trimExplode('|', $element);
				if ($this->clipboardInstance->getPad()->has($type, $identifier)) {
					$this->clipboardInstance->getPad()->remove($type, $identifier);
				} else {
						// current clipboard behaves, as on pad 0 only one item can be present
					if ($this->clipboardInstance->getActivePadId() == 0) {
						$this->clipboardInstance->getPad()->clear();
					}

					$this->clipboardInstance->getPad()->add($type, $identifier, $selected == 1);
				}
			}
		}
			// Change clipboard pad (if not locked to normal)
		if (isset($cmd['setP'])) {
			$this->setCurrentPad($cmd['setP']);
		}
			// Remove element	(value = item ident: DB; '[tablename]|[uid]'    FILE: '_FILE|[identifier]'     FILE: '_FOLDER|[identifier]'
		if ($cmd['remove']) {
			$this->removeElement($cmd['remove']);
		}
			// Remove all on current pad (value = pad-ident)
		if ($cmd['removeAll']) {
			$padId = intval($cmd['removeAll']);
			$this->clipboardInstance->getPad($padId)->clear();
		}
			// Set copy mode of the tab
		if (isset($cmd['setCopyMode'])) {
			$copyMode = ($cmd['setCopyMode'] ? t3lib_clipboard_Pad::MODE_COPY : t3lib_clipboard_Pad::MODE_CUT);
			$this->clipboardInstance->getPad()->setMode($copyMode);
		}
			// Toggle thumbnail display for files on/off
		if (isset($cmd['setThumb'])) {
			$this->clipboardInstance->setDisplayProperty('showThumbnail', (bool) $cmd['setThumb']);
		}
	}

	/**
	 * Setting the current pad on clipboard
	 *
	 * @param string $padId Key in the array $this->clipData
	 * @return void
	 */
	public function setCurrentPad($padId) {
		$padId = str_replace('tab_', '', $padId);

		if ($padId == 0) {
			$this->setCurrent('normal');
		} else {
			$this->setCurrent($padId);
		}

		if ($padId === 'normal') {
			$this->clipboardInstance->switchPad(0);
		} else {
			$this->clipboardInstance->switchPad(intval($padId));
		}
	}

	/**
	 * Call this after initialization and setCmd in order to save the clipboard to the user session.
	 * The function will check if the internal flag ->changed has been set and if so, save the clipboard. Else not.
	 *
	 * @return	void
	 */
	public function endClipboard() {
		$this->clipboardInstance->persist();
	}

	/**
	 * Cleans up an incoming element array $CBarr (Array selecting/deselecting elements)
	 *
	 * @param	array	$CBarr	Element array from outside ("key" => "selected/deselected")
	 * @param	string	$table	$table is the 'table which is allowed'. Must be set.
	 * @param	boolean	$removeDeselected	$removeDeselected can be set in order to remove entries which are marked for deselection.
	 * @return	array		Processed input $CBarr
	 */
	public function cleanUpCBC($CBarr, $table, $removeDeselected = FALSE) {
		if (is_array($CBarr)) {
			foreach ($CBarr as $k => $v) {
				$p = explode('|', $k);
				if ((string) $p[0] != (string) $table || ($removeDeselected && !$v)) {
					unset($CBarr[$k]);
				}
			}
		}
		return $CBarr;
	}


	/*****************************************
	 *
	 * Clipboard HTML renderings
	 *
	 ****************************************/

	/**
	 * Prints the clipboard
	 *
	 * @return	string		HTML output
	 */
	function printClipboard() {
		$out = array();
		$elCount = count($this->elFromTable($this->fileMode ? '_FILE' : ''));

			// Upper header
		$out[] = '
			<tr class="t3-row-header">
				<td colspan="3">' . t3lib_BEfunc::wrapInHelp('xMOD_csh_corebe', 'list_clipboard', $this->clLabel('clipboard', 'buttons')) . '</td>
			</tr>';

			// Button/menu header:
		$thumb_url = t3lib_div::linkThisScript(
			array('CB' => array('setThumb' => $this->clipboardInstance->getDisplayProperty('showThumbnail') ? 0 : 1))
		);
		$rmall_url = t3lib_div::linkThisScript(
			array('CB' => array('removeAll' => $this->clipboardInstance->getActivePadId()))
		);

			// Copymode Selector menu
		$copymode_url = t3lib_div::linkThisScript();
		$moveLabel = htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_misc.php:moveElements'));
		$copyLabel = htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_misc.php:copyElements'));
		$opt = array();
		$opt[] = '<option style="padding-left: 20px; background-image: url(\'' . t3lib_iconWorks::skinImg($this->backPath, 'gfx/clip_cut.gif', '', 1) . '\'); background-repeat: no-repeat;" value="" ' . (($this->currentMode() == 'copy') ? '' : 'selected="selected"') . '>' . $moveLabel . '</option>';
		$opt[] = '<option style="padding-left: 20px; background-image: url(\'' . t3lib_iconWorks::skinImg($this->backPath, 'gfx/clip_copy.gif', '', 1) . '\'); background-repeat: no-repeat;" value="1" ' . (($this->currentMode() == 'copy') ? 'selected="selected"' : '') . '>' . $copyLabel . '</option>';

		$copymode_selector = ' <select name="CB[setCopyMode]" onchange="this.form.method=\'POST\'; this.form.action=\'' . htmlspecialchars($copymode_url . '&CB[setCopyMode]=') . '\'+(this.options[this.selectedIndex].value); this.form.submit(); return true;" >' . implode('', $opt) . '</select>';

			// Selector menu + clear button
		$opt = array();
		$opt[] = '<option value="" selected="selected">' . $this->clLabel('menu', 'rm') . '</option>';
			// Import / Export link:
		if ($elCount && t3lib_extMgm::isLoaded('impexp')) {
			$opt[] = '<option value="' . htmlspecialchars("window.location.href='" . $this->backPath . t3lib_extMgm::extRelPath('impexp') . 'app/index.php' . $this->exportClipElementParameters() . '\';') . '">' . $this->clLabel('export', 'rm') . '</option>';
		}
			// Edit:
		if (!$this->fileMode && $elCount) {
			$opt[] = '<option value="' . htmlspecialchars("window.location.href='" . $this->editUrl() . "&returnUrl='+top.rawurlencode(window.location.href);") . '">' . $this->clLabel('edit', 'rm') . '</option>';
		}
			// Delete:
		if ($elCount) {
			if ($GLOBALS['BE_USER']->jsConfirmation(4)) {
				$js = "
			if(confirm(" . $GLOBALS['LANG']->JScharCode(sprintf($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:mess.deleteClip'), $elCount)) . ")){
				window.location.href='" . $this->deleteUrl(0, $this->fileMode ? 1 : 0) . "&redirect='+top.rawurlencode(window.location.href);
			}
					";
			} else {
				$js = " window.location.href='" . $this->deleteUrl(0, $this->fileMode ? 1 : 0) . "&redirect='+top.rawurlencode(window.location.href); ";
			}
			$opt[] = '<option value="' . htmlspecialchars($js) . '">' . $this->clLabel('delete', 'rm') . '</option>';
		}
		$selector_menu = '<select name="_clipMenu" onchange="eval(this.options[this.selectedIndex].value);this.selectedIndex=0;">' . implode('', $opt) . '</select>';

		$out[] = '
			<tr class="typo3-clipboard-head">
				<td nowrap="nowrap">' .
				'<a href="' . htmlspecialchars($thumb_url) . '#clip_head">' .
				'<img' . t3lib_iconWorks::skinImg($this->backPath, 'gfx/thumb_' . ($this->clipboardInstance->getDisplayProperty('showThumbnail') ? 's' : 'n') . '.gif', 'width="21" height="16"') . ' vspace="2" border="0" title="' . $this->clLabel('thumbmode_clip') . '" alt="" />' .
				'</a>' .
				'</td>
				<td width="95%" nowrap="nowrap">' .
				$copymode_selector . ' ' .
				$selector_menu .
				'</td>
				<td>' .
				'<a href="' . htmlspecialchars($rmall_url) . '#clip_head">' .
				t3lib_iconWorks::getSpriteIcon('actions-document-close', array('title' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:buttons.clear', TRUE))) .
				'</a></td>
			</tr>';


			// Print header and content for the NORMAL tab:
		$out[] = '
			<tr class="bgColor5">
				<td colspan="3"><a href="' . htmlspecialchars(t3lib_div::linkThisScript(array('CB' => array('setP' => 0)))) . '#clip_head">' .
				t3lib_iconWorks::getSpriteIcon('actions-view-table-' . (($this->clipboardInstance->getActivePadId() == 0) ? 'collapse' : 'expand')) .
				$this->padTitleWrap('Normal', 'normal') .
				'</a></td>
			</tr>';
		if ($this->clipboardInstance->getActivePadId() === 0) {
			$out = array_merge($out, $this->renderPad(0));
		}

			// Print header and content for the NUMERIC tabs:
		for ($a = 1; $a < $this->clipboardInstance->getNumberOfPads(); $a++) {
			$out[] = '
				<tr class="bgColor5">
					<td colspan="3"><a href="' . htmlspecialchars(t3lib_div::linkThisScript(array('CB' => array('setP' => $a)))) . '#clip_head">' .
					t3lib_iconWorks::getSpriteIcon('actions-view-table-' . (($this->clipboardInstance->getActivePadId() == $a) ? 'collapse' : 'expand')) .
					$this->padTitleWrap($this->clLabel('cliptabs') . $a, $a) .
					'</a></td>
				</tr>';
			if ($this->clipboardInstance->getActivePadId() == $a) {
				$out = array_merge($out, $this->renderPad($a));
			}
		}

			// Wrap accumulated rows in a table:
		$output = '<a name="clip_head"></a>

			<!--
				TYPO3 Clipboard:
			-->
			<table cellpadding="0" cellspacing="1" border="0" width="290" id="typo3-clipboard">
				' . implode('', $out) . '
			</table>';

			// Wrap in form tag:
		$output = '<form action="">' . $output . '</form>';

			// Return the accumulated content:
		return $output;
	}

	/**
	 * Print the content on a pad. Called from ->printClipboard()
	 *
	 * @deprecated since TYPO3 4.7 (use protected method renderPad() instead)
	 * @param integer $pad Pad reference
	 * @return string
	 */
	public function printContentFromTab($pad) {
		t3lib_div::logDeprecatedFunction();
		$this->renderPad($pad);
	}

	/**
	 * Renders content of a pad.
	 *
	 * @param integer $pad Pad reference
	 * @return string
	 */
	protected function renderPad($pad) {
		/** @var t3lib_file_Factory $fileFactory */
		$fileFactory = t3lib_div::makeInstance('t3lib_file_Factory');
		$lines = array();
		foreach ($this->clipboardInstance->getPad() AS $element) {
			list($type, $identifier) = t3lib_div::trimExplode(t3lib_clipboard_Pad::SPLIT_CHAR, $element);
			if ($this->clipboardInstance->getPad()->isSelected($type, $identifier)) {
				$bgColClass = ($type == '_FILE' && $this->fileMode) || ($type != '_FILE' && !$this->fileMode) ? 'bgColor4-20' : 'bgColor4';

				if ($type == '_FILE' || $type == '_FOLDER') { // Rendering files/directories on the clipboard:
					$error = FALSE;

					$thumb = '';
					if ($type == '_FOLDER') {
						$folder = $fileFactory->getFolderObjectFromCombinedIdentifier($identifier);
						$name = $folder->getName();
						$icon = t3lib_iconWorks::getSpriteIconForFile('folder', array('style' => 'margin: 0 20px;', 'title' => htmlspecialchars($name)));
					} else {
						$file = $fileFactory->getFileObjectFromCombinedIdentifier($identifier);
						$name = $file->getName();
						$icon = t3lib_iconWorks::getSpriteIconForFile($file->getExtension(), array('style' => 'margin: 0 20px;', 'title' => htmlspecialchars($name)));
						if ($this->clipboardInstance->getDisplayProperty('showThumbnail')
								&& t3lib_div::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], $file->getExtension())) {
							$thumb = t3lib_BEfunc::getThumbNail($this->backPath . 'thumbs.php', $file->getIdentifier(), ' vspace="4"');
						}
					}

					if(!$error) {
						$lines[] = '
							<tr>
								<td class="' . $bgColClass . '">' . $icon . '</td>
								<td class="' . $bgColClass . '" nowrap="nowrap" width="95%">&nbsp;' . $this->linkItemText(htmlspecialchars(t3lib_div::fixed_lgd_cs($name, $GLOBALS['BE_USER']->uc['titleLen'])), $identifier, $type) .
								($pad == 'normal' ? (' <strong>(' . ($this->clipboardInstance->getPad()->getMode() == t3lib_clipboard_Pad::MODE_COPY ? $this->clLabel('copy', 'cm') : $this->clLabel('cut', 'cm')) . ')</strong>') : '') . '&nbsp;' . ($thumb ? '<br />' . $thumb : '') . '</td>
								<td class="' . $bgColClass . '" align="center" nowrap="nowrap">' .
								'<a href="#" onclick="' . htmlspecialchars('top.launchView(\'' . $identifier . '\', \'\'); return false;') . '">' . t3lib_iconWorks::getSpriteIcon('actions-document-info', array('title' => $this->clLabel('info', 'cm'))) . '</a>' .
								'<a href="' . htmlspecialchars($this->removeUrl($type, $identifier)) . '#clip_head">' . t3lib_iconWorks::getSpriteIcon('actions-selection-delete', array('title' => $this->clLabel('removeItem'))) . '</a>' .
								'</td>
							</tr>';
					} else {
						$this->clipboardInstance->getPad()->remove($type, $identifier);
					}
				} else { // Rendering records:
					$rec = t3lib_BEfunc::getRecordWSOL($type, $identifier);
					if (is_array($rec)) {
						$lines[] = '
							<tr>
								<td class="' . $bgColClass . '">' . $this->linkItemText(t3lib_iconWorks::getSpriteIconForRecord($type, $rec, array('style' => 'margin: 0 20px;', 'title' => htmlspecialchars(t3lib_BEfunc::getRecordIconAltText($rec, $type)))), $rec, $type) . '</td>
								<td class="' . $bgColClass . '" nowrap="nowrap" width="95%">&nbsp;' . $this->linkItemText(htmlspecialchars(t3lib_div::fixed_lgd_cs(t3lib_BEfunc::getRecordTitle($type, $rec), $GLOBALS['BE_USER']->uc['titleLen'])), $rec, $type) .
								($pad == 'normal' ? (' <strong>(' . ($this->clipboardInstance->getPad()->getMode() == t3lib_clipboard_Pad::MODE_COPY ? $this->clLabel('copy', 'cm') : $this->clLabel('cut', 'cm')) . ')</strong>') : '') . '&nbsp;</td>
								<td class="' . $bgColClass . '" align="center" nowrap="nowrap">' .
								'<a href="#" onclick="' . htmlspecialchars('top.launchView(\'' . $type . '\', \'' . intval($identifier) . '\'); return false;') . '">' . t3lib_iconWorks::getSpriteIcon('actions-document-info', array('title' => $this->clLabel('info', 'cm'))) . '</a>' .
								'<a href="' . htmlspecialchars($this->removeUrl($type, $identifier)) . '#clip_head">' . t3lib_iconWorks::getSpriteIcon('actions-selection-delete', array('title' => $this->clLabel('removeItem'))) . '</a>' .
								'</td>
							</tr>';

						$localizationData = $this->getLocalizations($type, $rec, $bgColClass, $pad);
						if ($localizationData) {
							$lines[] = $localizationData;
						}

					} else {
						$this->clipboardInstance->getPad()->remove($type, $identifier);
					}
				}
			}
		}
		if (!count($lines)) {
			$lines[] = '
								<tr>
									<td class="bgColor4"><img src="clear.gif" width="56" height="1" alt="" /></td>
									<td colspan="2" class="bgColor4" nowrap="nowrap" width="95%">&nbsp;<em>(' . $this->clLabel('clipNoEl') . ')</em>&nbsp;</td>
								</tr>';
		}

		$this->endClipboard();
		return $lines;
	}


	/**
	 * Gets all localizations of the current record.
	 *
	 * @param	string	$table	the table
	 * @param	array	$parentRec	the current record
	 * @param	string	$bgColClass
	 * @param	int		$pad
	 * @return	string		HTML table rows
	 */
	public function getLocalizations($table, $parentRec, $bgColClass, $pad) {
		$lines = array();
		$tcaCtrl = $GLOBALS['TCA'][$table]['ctrl'];

		if ($table != 'pages' && t3lib_BEfunc::isTableLocalizable($table) && !$tcaCtrl['transOrigPointerTable']) {
			$where = array();
			$where[] = $tcaCtrl['transOrigPointerField'] . '=' . intval($parentRec['uid']);
			$where[] = $tcaCtrl['languageField'] . '<>0';

			if (isset($tcaCtrl['delete']) && $tcaCtrl['delete']) {
				$where[] = $tcaCtrl['delete'] . '=0';
			}

			if (isset($tcaCtrl['versioningWS']) && $tcaCtrl['versioningWS']) {
				$where[] = 't3ver_wsid=' . $parentRec['t3ver_wsid'];
			}

			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', $table, implode(' AND ', $where));

			if (is_array($rows)) {
				$modeData = '';
				if ($pad == 'normal') {
					$mode = ($this->clipboardInstance->getPad()->getMode() == t3lib_clipboard_Pad::MODE_COPY ? 'copy' : 'cut');
					$modeData = ' <strong>(' . $this->clLabel($mode, 'cm') . ')</strong>';
				}

				foreach ($rows as $rec) {
					$lines[] = '
					<tr>
						<td class="' . $bgColClass . '">' .
							t3lib_iconWorks::getSpriteIconForRecord($table, $rec, array('style' => "margin-left: 38px;")) . '</td>
						<td class="' . $bgColClass . '" nowrap="nowrap" width="95%">&nbsp;' . htmlspecialchars(
						t3lib_div::fixed_lgd_cs(t3lib_BEfunc::getRecordTitle($table, $rec), $GLOBALS['BE_USER']->uc['titleLen'])) .
							$modeData . '&nbsp;</td>
						<td class="' . $bgColClass . '" align="center" nowrap="nowrap">&nbsp;</td>
					</tr>';
				}
			}
		}
		return implode('', $lines);
	}


	/**
	 * Wraps title of pad in bold-tags and maybe the number of elements if any.
	 *
	 * @param	string	$str	String (already htmlspecialchars()'ed)
	 * @param	string	$pad	Pad reference
	 * @return	string		HTML output (htmlspecialchar'ed content inside of tags.)
	 */
	public function padTitleWrap($str, $pad) {
		$el = count($this->elFromTable($this->fileMode ? '_FILE' : '', $pad));
		if ($el) {
			return '<strong>' . $str . '</strong> (' . ($this->clipboardInstance->getActivePadId() == 0 ? ($this->clipboardInstance->getPad()->getMode() == t3lib_clipboard_Pad::MODE_COPY ? $this->clLabel('copy', 'cm') : $this->clLabel('cut', 'cm')) : htmlspecialchars($el)) . ')';
		} else {
			return $GLOBALS['TBE_TEMPLATE']->dfw($str);
		}
	}

	/**
	 * Wraps the title of the items listed in link-tags. The items will link to the page/folder where they originate from
	 *
	 * @param	string		Title of element - must be htmlspecialchar'ed on beforehand.
	 * @param	mixed		If array, a record is expected. If string, its a path
	 * @param	string		Table name
	 * @return	string
	 */
	function linkItemText($str, $rec, $table = '') {
		if (is_array($rec) && $table) {
			if ($this->fileMode) {
				$str = $GLOBALS['TBE_TEMPLATE']->dfw($str);
			} else {
				if (t3lib_extMgm::isLoaded('recordlist')) {
					$str = '<a href="' . htmlspecialchars(
						t3lib_BEfunc::getModuleUrl(
							'web_list',
							array('id' => $rec['pid']),
							$this->backPath)
					) . '">' . $str . '</a>';
				}
			}
		} else {
			$folderIdentifier = FALSE;
			if ($table == '_FILE' && $file = t3lib_div::makeInstance('t3lib_file_Factory')->getFileObjectFromCombinedIdentifier($rec) !== null) {
				$folderIdentifier = $file->getFolder()->getIdentifier();
			} elseif ($table == '_FOLDER' && $folder = t3lib_div::makeInstance('t3lib_file_Factory')->getFolderObjectFromCombinedIdentifier($rec) !== null) {
				$folderIdentifier = $rec;
			}
			if ($folderIdentifier !== FALSE) {
				if (!$this->fileMode) {
					$str = $GLOBALS['TBE_TEMPLATE']->dfw($str);
				} else {
					if (t3lib_extMgm::isLoaded('filelist')) {
						$str = '<a href="' . htmlspecialchars(
							$this->backPath . t3lib_extMgm::extRelPath('filelist') . 'mod1/file_list.php?id=' . $folderIdentifier
						) . '">' . $str . '</a>';
					}
				}
			}

		}
		return $str;
	}

	/**
	 * Returns the select-url for database elements
	 *
	 * @param	string		Table name
	 * @param	integer		Uid of record
	 * @param	boolean		If set, copymode will be enabled
	 * @param	boolean		If set, the link will deselect, otherwise select.
	 * @param	array		The base array of GET vars to be sent in addition. Notice that current GET vars WILL automatically be included.
	 * @return	string		URL linking to the current script but with the CB array set to select the element with table/uid
	 */
	function selUrlDB($table, $uid, $copy = 0, $deselect = 0, $baseArray = array()) {
		$CB = array('el' => array(rawurlencode($table . '|' . $uid) => $deselect ? 0 : 1));
		if ($copy) {
			$CB['setCopyMode'] = 1;
		}
		$baseArray['CB'] = $CB;
		return t3lib_div::linkThisScript($baseArray);
	}

	/**
	 * Returns the select-url for files
	 *
	 * @param	string		Filepath
	 * @param	boolean		If set, copymode will be enabled
	 * @param	boolean		If set, the link will deselect, otherwise select.
	 * @param	array		The base array of GET vars to be sent in addition. Notice that current GET vars WILL automatically be included.
	 * @return	string		URL linking to the current script but with the CB array set to select the path
	 */
	function selUrlFile($path, $copy = 0, $deselect = 0, $baseArray = array()) {
		$CB = array('el' => array(rawurlencode('_FILE|' . t3lib_div::shortmd5($path)) => $deselect ? '' : $path));
		if ($copy) {
			$CB['setCopyMode'] = 1;
		}
		$baseArray['CB'] = $CB;
		return t3lib_div::linkThisScript($baseArray);
	}

	/**
	 * pasteUrl of the element (database and file)
	 * For the meaning of $table and $uid, please read from ->makePasteCmdArray!!!
	 * The URL will point to tce_file or tce_db depending in $table
	 *
	 * @param	string	$table			Tablename (_FILE for files)
	 * @param	mixed	$uid			"destination": can be positive or negative indicating how the paste is done (paste into / paste after)
	 * @param	boolean	$setRedirect	If set, then the redirect URL will point back to the current script, but with CB reset.
	 * @return	string
	 */
	public function pasteUrl($table, $uid, $setRedirect = TRUE) {
		$rU = $this->backPath . ($table == '_FILE' || $table == '_FOLDER' ? 'tce_file.php' : 'tce_db.php') . '?' .
				($setRedirect ? 'redirect=' . rawurlencode(t3lib_div::linkThisScript(array('CB' => ''))) : '') .
				'&vC=' . $GLOBALS['BE_USER']->veriCode() .
				'&prErr=1&uPT=1' .
				'&CB[paste]=' . rawurlencode($table . '|' . $uid) .
				'&CB[pad]=' . $this->clipboardInstance->getActivePadId() .
				t3lib_BEfunc::getUrlToken('tceAction');
		return $rU;
	}

	/**
	 * deleteUrl for current pad
	 *
	 * @param	boolean	$setRedirect	If set, then the redirect URL will point back to the current script, but with CB reset.
	 * @param	boolean	$file			If set, then the URL will link to the tce_file.php script in the typo3/ dir.
	 * @return	string
	 */
	public function deleteUrl($setRedirect = TRUE, $file = FALSE) {
		$rU = $this->backPath . ($file ? 'tce_file.php' : 'tce_db.php') . '?' .
				($setRedirect ? 'redirect=' . rawurlencode(t3lib_div::linkThisScript(array('CB' => ''))) : '') .
				'&vC=' . $GLOBALS['BE_USER']->veriCode() .
				'&prErr=1&uPT=1' .
				'&CB[delete]=1' .
				'&CB[pad]=' . $this->clipboardInstance->getActivePadId() .
				t3lib_BEfunc::getUrlToken('tceAction');
		return $rU;
	}

	/**
	 * editUrl of all current elements
	 * ONLY database
	 * Links to alt_doc.php
	 *
	 * @return	string		The URL to alt_doc.php with parameters.
	 */
	function editUrl() {
		$elements = $this->elFromTable(''); // all records
		$editCMDArray = array();
		foreach ($elements as $tP => $value) {
			list($table, $uid) = explode('|', $tP);
			$editCMDArray[] = '&edit[' . $table . '][' . $uid . ']=edit';
		}

		$rU = $this->backPath . 'alt_doc.php?' . implode('', $editCMDArray);
		return $rU;
	}

	/**
	 * Returns the remove-url (file and db)
	 * for file $table='_FILE' and $uid = shortmd5 hash of path
	 *
	 * @param	string	$table	Tablename
	 * @param	string	$uid	uid integer/shortmd5 hash
	 * @return	string		URL
	 */
	public function removeUrl($table, $uid) {
		return t3lib_div::linkThisScript(array('CB' => array('remove' => $table . '|' . $uid)));
	}

	/**
	 * Returns confirm JavaScript message
	 *
	 * @param	string		Table name
	 * @param	mixed		For records its an array, for files its a string (path)
	 * @param	string		Type-code
	 * @param	array		Array of selected elements
	 * @return	string		JavaScript "confirm" message
	 */
	public function confirmMsg($table, $rec, $type, $clElements) {
		if ($GLOBALS['BE_USER']->jsConfirmation(2)) {
			$labelKey = 'LLL:EXT:lang/locallang_core.php:mess.' . ($this->currentMode() == 'copy' ? 'copy' : 'move') . ($this->clipboardInstance->getActivePadId() == 0 ? '' : 'cb') . '_' . $type;
			$msg = $GLOBALS['LANG']->sL($labelKey);

			if ($table == '_FILE') {
				$thisRecTitle = basename($rec);
				if ($this->clipboardInstance->getActivePadId() == 0) {
					$selItem = reset($clElements);
					$selRecTitle = basename($selItem);
				} else {
					$selRecTitle = count($clElements);
				}
			} else {
				$thisRecTitle = (
				$table == 'pages' && !is_array($rec) ?
						$GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] :
						t3lib_BEfunc::getRecordTitle($table, $rec)
				);

				if ($this->clipboardInstance->getActivePadId() == 0) {
					$selItem = $this->getSelectedRecord();
					$selRecTitle = $selItem['_RECORD_TITLE'];
				} else {
					$selRecTitle = count($clElements);
				}
			}

				// Message:
			$conf = 'confirm(' . $GLOBALS['LANG']->JScharCode(sprintf(
				$msg,
				t3lib_div::fixed_lgd_cs($selRecTitle, 30),
				t3lib_div::fixed_lgd_cs($thisRecTitle, 30)
			)) . ')';
		} else {
			$conf = '';
		}
		return $conf;
	}

	/**
	 * Clipboard label - getting from "EXT:lang/locallang_core.php:"
	 *
	 * @param	string		Label Key
	 * @param	string		Alternative key to "labels"
	 * @return	string
	 */
	function clLabel($key, $Akey = 'labels') {
		return htmlspecialchars($GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:' . $Akey . '.' . $key));
	}

	/**
	 * Creates GET parameters for linking to the export module.
	 *
	 * @return	string		GET parameters for current clipboard content to be exported.
	 */
	public function exportClipElementParameters() {

			// Init:
		$params = array();
		$params[] = 'tx_impexp[action]=export';

			// Traverse items:
		foreach ($this->clipboardInstance->getPad() AS $entry) {
			list($table, $uid) = explode('|', $entry);
			if ($this->clipboardInstance->getPad()->isSelected($table, $uid)) {
				if ($table == '_FILE') { // Rendering files/directories on the clipboard:
					$params[] = 'tx_impexp[file][]=' . rawurlencode($uid);
				} elseif ($table == '_FOLDER') {
					$params[] = 'tx_impexp[folder][]=' . rawurlencode($uid);
				} else { // Rendering records:
					$rec = t3lib_BEfunc::getRecord($table, $uid);
					if (is_array($rec)) {
						$params[] = 'tx_impexp[record][]=' . rawurlencode($table . ':' . $uid);
					}
				}
			}
		}

		return '?' . implode('&', $params);
	}


	/*****************************************
	 *
	 * Helper functions
	 *
	 ****************************************/

	/**
	 * Removes element on clipboard
	 *
	 * @param	string	$el	Key of element in ->clipData array
	 * @return	void
	 */
	public function removeElement($el) {
		list($type, $identifier) = t3lib_div::trimExplode('|', $el, FALSE, 2);
		$this->clipboardInstance->getPad()->remove($type, $identifier);
	}

	/**
	 * Saves the clipboard, no questions asked.
	 * Use ->endClipboard normally (as it checks if changes has been done so saving is necessary)
	 *
	 * @return	void
	 * @access private
	 */
	public function saveClipboard() {
		$this->clipboardInstance->persist();
	}

	/**
	 * Returns the current mode, 'copy' or 'cut'
	 *
	 * @return	string		"copy" or "cut"
	 */
	public function currentMode() {
		return $this->clipboardInstance->getPad()->getMode() == t3lib_clipboard_Pad::MODE_COPY ? 'copy' : 'cut';
	}

	/**
	 * This traverses the elements on the current clipboard pane
	 * and unsets elements which does not exist anymore or are disabled.
	 *
	 * @deprecated Deprecated since 4.7, will be removed in 4.9
	 * @return	void
	 */
	public function cleanCurrent() {
		t3lib_div::logDeprecatedFunction();
	}

	/**
	 * Counts the number of elements from the table $matchTable. If $matchTable is blank, all tables (except '_FILE' of course) is counted.
	 *
	 * @param	string	$matchTable	Table to match/count for.
	 * @param	int		$pad		$pad can optionally be used to set another pad than the current.
	 * @return	array		Array with keys from the CB.
	 */
	public function elFromTable($matchTable = '', $pad = -1) {
		$currentId = $this->clipboardInstance->getActivePadId();
		$this->clipboardInstance->switchPad(intval($pad));
		$list = array();
		foreach ($this->clipboardInstance->getPad() AS $entry) {
			list($type, $identifier) = t3lib_div::trimExplode('|', $entry);
			if ($type !== '_FILE' && $type !== '_FOLDER') {
				if ($matchTable === '' || ($type == $matchTable && isset($GLOBALS['TCA'][$type]))) {
					$list[] = $entry;
				}
			} else {
				if ((string)$type == (string)$matchTable) {
					$list[] = $entry;
				}
			}
		}
		$this->clipboardInstance->switchPad($currentId);
		return $list;
	}

	/**
	 * Verifies if the item $table/$uid is on the current pad.
	 * If the pad is "normal", the mode value is returned if the element existed. Thus you'll know if the item was copy or cut moded...
	 *
	 * @param	string	$table	Table name, (_FILE for files...)
	 * @param	integer	$uid	Element uid (path for files)
	 * @return	string
	 */
	public function isSelected($table, $uid) {
		return $this->clipboardInstance->getPad()->isSelected($table, $uid);
	}

	/**
	 * Returns item record $table,$uid if selected on current clipboard
	 * If table and uid is blank, the first element is returned.
	 * Makes sense only for DB records - not files!
	 *
	 * @param	string	$table	Table name
	 * @param	integer	$uid	Element uid
	 * @return	array		Element record with extra field _RECORD_TITLE set to the title of the record...
	 */
	public function getSelectedRecord($table = '', $uid = '') {
		if ((!$table && !$uid) || ! $this->isSelected($table, $uid)) {
			$element = current($this->clipboardInstance->getPad()->getSelected());
			list($table, $uid) = explode('|', $element);
		}

		$selRec = t3lib_BEfunc::getRecordWSOL($table, $uid);
		$selRec['_RECORD_TITLE'] = t3lib_BEfunc::getRecordTitle($table, $selRec);
		return $selRec;
	}

	/**
	 * Reports if the current pad has elements (does not check file/DB type OR if file/DBrecord exists or not. Only counting array)
	 *
	 * @return	boolean		TRUE if elements exist.
	 */
	public function isElements() {
		return $this->clipboardInstance->getPad()->count() > 0;
	}


	/*****************************************
	 *
	 * FOR USE IN tce_db.php:
	 *
	 ****************************************/

	/**
	 * Applies the proper paste configuration in the $cmd array send to tce_db.php.
	 * $ref is the target, see description below.
	 * The current pad is pasted
	 *
	 *		 $ref: [tablename]:[paste-uid].
	 *		 tablename is the name of the table from which elements *on the current clipboard* is pasted with the 'pid' paste-uid.
	 *		 No tablename means that all items on the clipboard (non-files) are pasted. This requires paste-uid to be positive though.
	 *		 so 'tt_content:-3'	means 'paste tt_content elements on the clipboard to AFTER tt_content:3 record
	 *		 'tt_content:30'	means 'paste tt_content elements on the clipboard into page with id 30
	 *		 ':30'	means 'paste ALL database elements on the clipboard into page with id 30
	 *		 ':-30'	not valid.
	 *
	 * @param	string	$ref	[tablename]:[paste-uid], see description
	 * @param	array	$CMD	Command-array
	 * @return	array		Modified Command-array
	 */
	public function makePasteCmdArray($ref, $CMD) {
		list($pTable, $pUid) = explode('|', $ref);
		$pUid = intval($pUid);
		if ($pTable || $pUid >= 0) { // pUid must be set and if pTable is not set (that means paste ALL elements) the uid MUST be positive/zero (pointing to page id)
			$elements = $this->elFromTable($pTable);

			$elements = array_reverse($elements); // So the order is preserved.
			$mode = $this->currentMode() == 'copy' ? 'copy' : 'move';

				// Traverse elements and make CMD array
			foreach ($elements as $tP) {
				list($table, $uid) = explode('|', $tP);
				if (!is_array($CMD[$table])) {
					$CMD[$table] = array();
				}
				$CMD[$table][$uid][$mode] = $pUid;
				if ($mode == 'move') {
					$this->removeElement($tP);
				}
			}
			$this->endClipboard();
		}
		return $CMD;
	}

	/**
	 * Delete record entries in CMD array
	 *
	 * @param	array	$CMD	Command-array
	 * @return	array		Modified Command-array
	 */
	public function makeDeleteCmdArray($CMD) {
		$elements = $this->elFromTable(''); // all records
		foreach ($elements as $tP) {
			list($table, $uid) = explode('|', $tP);
			if (!is_array($CMD[$table])) {
				$CMD[$table] = array();
			}
			$CMD[$table][$uid]['delete'] = 1;
			$this->removeElement($tP);
		}
		$this->endClipboard();
		return $CMD;
	}


	/*****************************************
	 *
	 * FOR USE IN tce_file.php:
	 *
	 ****************************************/

	/**
	 * Applies the proper paste configuration in the $file array send to tce_file.php.
	 * The current pad is pasted
	 *
	 * @param	string	$ref	Reference to element (splitted by "|")
	 * @param	array	$FILE	Command-array
	 * @return	array		Modified Command-array
	 * @TODO adapt to FAL
	 */
	public function makePasteCmdArray_file($ref, $FILE) {
		list($pTable, $pUid) = explode('|', $ref);
		$elements = $this->elFromTable('_FILE');
		$mode = $this->currentMode() == 'copy' ? 'copy' : 'move';

			// Traverse elements and make CMD array
		foreach ($elements as $tP) {
			$FILE[$mode][] = array('data' => $path, 'target' => $pUid, 'altName' => 1);
			if ($mode == 'move') {
				$this->removeElement($tP);
			}
		}
		$this->endClipboard();

		return $FILE;
	}

	/**
	 * Delete files in CMD array
	 *
	 * @param	array	$FILE	Command-array
	 * @return	array		Modified Command-array
	 * @TODO adapt to FAL
	 */
	public function makeDeleteCmdArray_file($FILE) {
		$elements = $this->elFromTable('_FILE');
			// Traverse elements and make CMD array
		foreach ($elements as $tP) {
			$FILE['delete'][] = array('data' => $path);
			$this->removeElement($tP);
		}
		$this->endClipboard();

		return $FILE;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_clipboard.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['t3lib/class.t3lib_clipboard.php']);
}

?>