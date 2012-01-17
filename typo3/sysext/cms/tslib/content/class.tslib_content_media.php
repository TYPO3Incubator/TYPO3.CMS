<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Xavier Perseguers <typo3@perseguers.ch>
 *  (c) 2010-2011 Steffen Kamper <steffen@typo3.org>
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
 * Contains MEDIA class object.
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 * @author Steffen Kamper <steffen@typo3.org>
 */
class tslib_content_Media extends tslib_content_Abstract {

	/**
	 * Rendering the cObject, MEDIA
	 *
	 * @param $conf array Array of TypoScript properties
	 * @return string Output
	 */
	public function render($conf = array()) {
		$content = '';
			// Add flex parameters to configuration
		$flexParams = isset($conf['flexParams.'])
			? $this->cObj->stdWrap($conf['flexParams'], $conf['flexParams.'])
			: $conf['flexParams'];
		if (substr($flexParams, 0, 1) === '<') {
				// It is a content element rather a TS object
			$this->cObj->readFlexformIntoConf($flexParams, $conf['parameter.']);
		}
			// Type is video or audio
		$mmType = isset($conf['parameter.']['mmType.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmType'], $conf['parameter.']['mmType.'])
			: $conf['parameter.']['mmType'];
		$type = isset($conf['type.'])
			? $this->cObj->stdWrap($conf['type'], $conf['type.'])
			: $conf['type'];
		$conf['type'] = $mmType ? $mmType : $type;

			// Video sources
		$sources = isset($conf['sources.'])
			? $this->cObj->stdWrap($conf['sources'], $conf['sources.'])
			: $conf['sources'];
		$mmSources = isset($conf['parameter.']['mmSources.']['mmSourcesContainer.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmSources.']['mmSourcesContainer'], $conf['parameter.']['mmSources.']['mmSourcesContainer.'])
			: $conf['parameter.']['mmSources']['mmSourcesContainer'];

		$sources = $mmSources ? $mmSources : $sources;
		if (is_array($sources) && count($sources)) {
			$conf['sources'] = array();
			foreach ($sources as $key => $source) {
				if (isset($source['mmSource'])) {
					$conf['sources'][$key] = $this->retrieveMediaUrl($source['mmSource']);
				}
			}
		} else {
			unset($conf['sources']);
		}

		$previewImage = isset($conf['previewImage.'])
			? $this->cObj->stdWrap($conf['previewImage'], $conf['previewImage.'])
			: $conf['previewImage'];
		$mmPreviewImage = isset($conf['parameter.']['mmPreviewImage.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmPreviewImage'], $conf['parameter.']['mmPreviewImage.'])
			: $conf['parameter.']['mmPreviewImage'];
		$previewImage = $mmPreviewImage ? $mmPreviewImage : $previewImage;
		if ($previewImage) {
			$conf['attributes.']['poster'] = $this->retrieveMediaUrl($previewImage);
		}

			// Video fallback and backward compatibility file
		$videoFallback = isset($conf['file.'])
			? $this->cObj->stdWrap($conf['file'], $conf['file.'])
			: $conf['file'];
		$mmVideoFallback = isset($conf['parameter.']['mmFile.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmFile'], $conf['parameter.']['mmFile.'])
			: $conf['parameter.']['mmFile'];
		$videoFallback = $mmVideoFallback ? $mmVideoFallback : $videoFallback;
			// Backward compatibility file
		$url = $videoFallback;
		if ($videoFallback) {
			$conf['file'] = $this->retrieveMediaUrl($videoFallback);
		} else {
			unset($conf['file']);
		}

			// Audio sources
		$audioSources = isset($conf['audioSources.'])
			? $this->cObj->stdWrap($conf['audioSources'], $conf['audioSources.'])
			: $conf['audioSources'];
		$mmAudioSources = isset($conf['parameter.']['mmAudioSources.']['mmAudioSourcesContainer.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmAudioSources.']['mmAudioSourcesContainer'], $conf['parameter.']['mmAudioSources.']['mmAudioSourcesContainer.'])
			: $conf['parameter.']['mmAudioSources']['mmAudioSourcesContainer'];
		$audioSources = $mmAudioSources ? $mmAudioSources : $audioSources;
		if (is_array($audioSources) && count($audioSources)) {
			$conf['audioSources'] = array();
			foreach ($audioSources as $key => $source) {
				if (isset($source['mmAudioSource'])) {
					$conf['audioSources'][$key] = $this->retrieveMediaUrl($source['mmAudioSource']);
				}
			}
		} else {
			unset($conf['audioSources']);
		}

			// Audio fallback
		$audioFallback = isset($conf['audioFallback.'])
			? $this->cObj->stdWrap($conf['audioFallback'], $conf['audioFallback.'])
			: $conf['audioFallback'];
		$mmAudioFallback = isset($conf['parameter.']['mmAudioFallback.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmAudioFallback'], $conf['parameter.']['mmAudioFallback.'])
			: $conf['parameter.']['mmAudioFallback'];
		$audioFallback = $mmAudioFallback ? $mmAudioFallback : $audioFallback;
		if ($audioFallback) {
			$conf['audioFallback'] = $this->retrieveMediaUrl($audioFallback);
		} else {
			unset($conf['audioFallback']);
		}
			// Backward compatibility
		if ($conf['type'] === 'audio' && !isset($conf['audioFallback'])) {
			$conf['audioFallback'] = $conf['file'];
		}

			// Caption file
		$caption = isset($conf['caption.'])
			? $this->cObj->stdWrap($conf['caption'], $conf['caption.'])
			: $conf['caption'];
		$mmCaption = isset($conf['parameter.']['mmCaption.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmCaption'], $conf['parameter.']['mmCaption.'])
			: $conf['parameter.']['mmCaption'];
		$caption = $mmCaption ? $mmCaption : $caption;
		if ($caption) {
			$conf['caption'] = $this->retrieveMediaUrl($caption);
		} else {
			unset($conf['caption']);
		}


			// Data-* attributes for HTML5
		$dataAttributes = isset($conf['dataAttributes.'])
			? $this->cObj->stdWrap($conf['dataAttributes'], $conf['dataAttributes.'])
			: $conf['dataAttributes'];
		$mmDataAttributes = isset($conf['parameter.']['mmDataAttributes.']['mmDataAttributesContainer.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmDataAttributes.']['mmDataAttributesContainer'], $conf['parameter.']['mmDataAttributes.']['mmDataAttributesContainer.'])
			: $conf['parameter.']['mmDataAttributes']['mmDataAttributesContainer'];

		$dataAttributes = $mmDataAttributes ? $mmDataAttributes : $dataAttributes;
		if (is_array($dataAttributes) && count($dataAttributes)) {
			foreach ($dataAttributes as $key => $value) {
				$conf['attributes.']['data-' . trim($value['mmParamName'])] = trim($value['mmParamValue']);
			}
		}


			// Establish render type
		$renderType = isset($conf['renderType.'])
			? $this->cObj->stdWrap($conf['renderType'], $conf['renderType.'])
			: $conf['renderType'];
		$mmRenderType = isset($conf['parameter.']['mmRenderType.'])
			? $this->cObj->stdWrap($conf['parameter.']['mmRenderType'], $conf['parameter.']['mmRenderType.'])
			: $conf['parameter.']['mmRenderType'];
		$renderType = $mmRenderType ? $mmRenderType : $renderType;
		if ($renderType === 'preferFlashOverHtml5') {
			$conf['preferFlashOverHtml5'] = 1;
			$renderType = 'auto';
		}
		if ($renderType === 'auto') {
				// Default renderType is swf
			$renderType = 'swf';
			$handler = array_keys($conf['fileExtHandler.']);
			if ($conf['type'] === 'video') {
				$fileinfo = t3lib_div::split_fileref($conf['file']);
			} else {
				$fileinfo = t3lib_div::split_fileref($conf['audioFallback']);
			}
			if (in_array($fileinfo['fileext'], $handler)) {
				$renderType = strtolower($conf['fileExtHandler.'][$fileinfo['fileext']]);
			}
		}

		$mime = $renderType . 'object';
		$typeConf = $conf['mimeConf.'][$mime . '.'][$conf['type'] . '.'] ? $conf['mimeConf.'][$mime . '.'][$conf['type'] . '.'] : array();
		$conf['predefined'] = array();

			// Width and height
		$width = isset($conf['width.'])
			? intval($this->cObj->stdWrap($conf['width'], $conf['width.']))
			: intval($conf['width']);
		$width = $width ? $width : $typeConf['defaultWidth'];
		$mmWidth = isset($conf['parameter.']['mmWidth.'])
			? intval($this->cObj->stdWrap($conf['parameter.']['mmWidth'], $conf['parameter.']['mmWidth.']))
			: intval($conf['parameter.']['mmWidth']);
		$conf['width'] = $mmWidth ? $mmWidth : $width;
		$height = isset($conf['height.'])
			? intval($this->cObj->stdWrap($conf['height'], $conf['height.']))
			: intval($conf['height']);
		$height = $height ? $height : $typeConf['defaultHeight'];
		$mmHeight = isset($conf['parameter.']['mmHeight.'])
			? intval($this->cObj->stdWrap($conf['parameter.']['mmHeight'], $conf['parameter.']['mmHeight.']))
			: intval($conf['parameter.']['mmHeight']);
		$conf['height'] = $mmHeight ? $mmHeight : $height;

		if (is_array($conf['parameter.']['mmMediaOptions'])) {
			$params = array();
			foreach ($conf['parameter.']['mmMediaOptions'] as $key => $value) {
				if ($key == 'mmMediaCustomParameterContainer') {
					foreach ($value as $val) {
							//custom parameter entry
						$rawTS = $val['mmParamCustomEntry'];
							//read and merge
						$tmp = t3lib_div::trimExplode(LF, $rawTS);
						if (count($tmp)) {
							foreach ($tmp as $tsLine) {
								if (substr($tsLine, 0, 1) != '#' && $pos = strpos($tsLine, '.')) {
									$parts[0] = substr($tsLine, 0, $pos);
									$parts[1] = substr($tsLine, $pos + 1);
									$valueParts = t3lib_div::trimExplode('=', $parts[1], TRUE);

									switch (strtolower($parts[0])) {
										case 'flashvars' :
											$conf['flashvars.'][$valueParts[0]] = $valueParts[1];
										break;
										case 'params' :
											$conf['params.'][$valueParts[0]] = $valueParts[1];
										break;
										case 'attributes' :
											$conf['attributes.'][$valueParts[0]] = $valueParts[1];
										break;
									}
								}
							}
						}
					}
				} elseif ($key == 'mmMediaOptionsContainer') {
					foreach ($value as $val) {
						if (isset($val['mmParamSet'])) {
							$pName = $val['mmParamName'];
							$pSet = $val['mmParamSet'];
							$pValue = $pSet == 2 ? $val['mmParamValue'] : ($pSet == 0 ? 'false' : 'true');
							$conf['predefined'][$pName] = $pValue;
						}
					}
				}
			}
		}

		switch ($renderType) {
			case 'swf' :
				$conf[$conf['type'] . '.'] = array_merge((array) $conf['mimeConf.']['swfobject.'][$conf['type'] . '.'], $typeConf);
				$conf = array_merge((array) $conf['mimeConf.']['swfobject.'], $conf);
				unset($conf['mimeConf.']);
				$conf['attributes.'] = array_merge((array) $conf['attributes.'], $conf['predefined']);
				$conf['params.'] = array_merge((array) $conf['params.'], $conf['predefined']);
				$conf['flashvars.'] = array_merge((array) $conf['flashvars.'], $conf['predefined']);
				$content = $this->cObj->SWFOBJECT($conf);
			break;
			case 'qt' :
				$conf[$conf['type'] . '.'] = array_merge($conf['mimeConf.']['swfobject.'][$conf['type'] . '.'], $typeConf);
				$conf = array_merge($conf['mimeConf.']['qtobject.'], $conf);
				unset($conf['mimeConf.']);
				$conf['params.'] = array_merge((array) $conf['params.'], $conf['predefined']);
				$content = $this->cObj->QTOBJECT($conf);
			break;
			case 'embed' :
				$paramsArray = array_merge((array) $typeConf['default.']['params.'], (array) $conf['params.'], $conf['predefined']);
				$conf['params'] = '';
				foreach ($paramsArray as $key => $value) {
					$conf['params'] .= $key . '=' . $value . LF;
				}
				$content = $this->cObj->MULTIMEDIA($conf);
			break;
			default :
				if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/hooks/class.tx_cms_mediaitems.php']['customMediaRender'])) {
					foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/hooks/class.tx_cms_mediaitems.php']['customMediaRender'] as $classRef) {
						$hookObj = t3lib_div::getUserObj($classRef);
						$conf['file'] = $url;
						$conf['mode'] = $this->fileFactory->getFileObjectFromCombinedIdentifier($url) !== 0 ? 'file' : 'url';
						$content = $hookObj->customMediaRender($renderType, $conf, $this);
					}
				}
				if (isset($conf['stdWrap.'])) {
					$content = $this->cObj->stdWrap($content, $conf['stdWrap.']);
				}
		}

		return $content;
	}

	/**
	 * Retrieves an File-Identifier or URL and returns the public URL of the
	 * Media-Element in Charge
	 *
	 * @param string $identifier
	 * @return string
	 */
	protected function retrieveMediaUrl($identifier) {
		$returnValue = NULL;

			// check if the URL is a "FAL" url
		if (t3lib_div::isFirstPartOfStr($identifier, 'file:')) {
			$combinedIdentifier = substr($identifier, 5);
			if (t3lib_utility_Math::canBeInterpretedAsInteger($combinedIdentifier)) {
				$fileObject = $this->fileFactory->getFileObject($combinedIdentifier);
			} else {
				$fileObject = $this->fileFactory->getFileObjectFromCombinedIdentifier($combinedIdentifier);
			}
		}

		if ($fileObject !== NULL) {
			$returnValue = $fileObject->getPublicUrl();
		} else {
				// Use media wizard to extract file from URL
			$mediaWizard = tslib_mediaWizardManager::getValidMediaWizardProvider($identifier);
			if ($mediaWizard !== NULL) {
				$returnValue = $mediaWizard->rewriteUrl($identifier);
				$returnValue = $this->cObj->typoLink_URL(array(
					'parameter' => $returnValue
				));
			}
		}

		return $returnValue;
	}
}


if (defined('TYPO3_MODE') && isset($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['tslib/content/class.tslib_content_media.php'])) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['tslib/content/class.tslib_content_media.php']);
}

?>