<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

/**
 * Table "sys_file_storage":
 * defines a root-point of a file storage, that is like a mount point.
 * each storage is attached to a driver (local, webdav, amazons3) and thus is the entry-point
 * for all files
 */
$TCA['sys_file_storage'] = array(
	'ctrl' => array(
		'title'     => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_storage',
		'label'     => 'name',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => 'ORDER BY name',
		'delete' => 'deleted',
		'rootLevel' => TRUE,
		'enablecolumns' => array(
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/FileStorage.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/FileStorage.gif',
	),
);


/**
 * Table "sys_file":
 * Represents all files that are tracked by TYPO3
 * which are assets, single entries of files with additional metadata
 */
$TCA['sys_file'] = array(
	'ctrl' => array(
		'title'     => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file',
		'label'     => 'name',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'type'		=> 'type',
		'versioningWS'             => TRUE,
		'origUid'                  => 't3_origuid',
		'languageField'            => 'sys_language_uid',
		'transOrigPointerField'    => 'l10n_parent',
		'transOrigDiffSourceField' => 'l10n_diffsource',
		'default_sortby' => 'ORDER BY crdate DESC',
		'delete' => 'deleted',
		'enablecolumns' => array(
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
		),
		'dividers2tabs' => TRUE,
		'typeicon_column' => 'type',
		'typeicon_classes' => array(
			'1' => 'mimetypes-text-text',
			'2' => 'mimetypes-media-image',
			'3' => 'mimetypes-media-audio',
			'4' => 'mimetypes-media-video',
			'5' => 'mimetypes-application',
			'default' => 'mimetypes-other-other',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/File.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/File.gif',
	),
);
t3lib_extMgm::allowTableOnStandardPages('sys_file');


/**
 * Table "sys_file_reference":
 * Is a single usage of a sys_file record somewhere in the installation
 * Is kind of like a MM-table between sys_file and e.g. tt_content:image that is shown up
 * in TCA so additional metadata can be added for this specific kind of usage
 */
$TCA['sys_file_reference'] = array(
	'ctrl' => array(
		'title'     => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference',
		'label'     => 'uid',
		#'label_userFunc' => 'EXT:file/class.tx_file_userFunc.php:&tx_file_userFunc->getReferenceRecordLabel',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'type' => 'uid_local:type',
		'sortby' => 'sorting',	
		'delete' => 'deleted',	
		'enablecolumns' => array(		
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/FileReference.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/FileReference.gif',
	),
);

t3lib_extMgm::addPageTSConfig('mod.web_list.hideTables := addToList(sys_file_reference)');
t3lib_extMgm::allowTableOnStandardPages('sys_file_reference');




/**
 * Table "sys_file_collection":
 * Represents a list of sys_file records
 */
$TCA['sys_file_collection'] = array(
	'ctrl' => array(
		'title'     => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_collection',
		'label'     => 'title',
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'versioningWS' => TRUE,
		'origUid' => 't3_origuid',
		'languageField'            => 'sys_language_uid',
		'transOrigPointerField'    => 'l10n_parent',
		'transOrigDiffSourceField' => 'l10n_diffsource',
		'default_sortby' => 'ORDER BY crdate',
		'delete' => 'deleted',
		'rootlevel' => -1,
		'type' => 'type',
		'typeicon_column' => 'type',
		'typeicon_classes' => array(
			'default' => 'apps-filetree-folder-media',
			'static'  => 'apps-clipboard-images',
			'filter'  => 'actions-system-tree-search-open',
			'folder'  => 'apps-filetree-folder-media'
		),
		'enablecolumns' => array(
			'disabled' => 'hidden',
			'starttime' => 'starttime',
			'endtime' => 'endtime',
			'fe_group' => 'fe_group',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY) . 'Configuration/TCA/FileCollection.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY) . 'Resources/Public/Icons/FileCollection.gif',
	),
);
t3lib_extMgm::allowTableOnStandardPages('sys_file_collection');

?>