<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$extDirectPath = 'EXT:file/Classes/Service/ExtDirect/';

t3lib_extMgm::registerExtDirectComponent('TYPO3.FileList.Service.ExtDirect.CollectionManagement', $extDirectPath . 'CollectionManagement.php:Tx_File_Service_ExtDirect_CollectionManagement');
t3lib_extMgm::registerExtDirectComponent('TYPO3.FileList.Service.ExtDirect.FilterManagement', $extDirectPath . 'FilterManagement.php:Tx_File_Service_ExtDirect_FilterManagement');

// register scheduler task for indexing
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['Tx_File_Tasks_Indexing'] = array(
    'extension'        => $_EXTKEY,
    'title'            => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_file.xlf:tasks.indexing.name',
    'description'      => 'LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_file.xlf:tasks.indexing.description',
    'additionalFields' => 'Tx_File_Tasks_IndexingAdditionalFieldProvider'
);

// migrations of tt_content.image DB fields and captions, alt texts, etc. into sys_file_reference records.
$TYPO3_CONF_VARS['SC_OPTIONS']['ext/install']['update']['sysext_file_images'] = 'Tx_File_UpgradeWizard_TtContentUpgradeWizard';
$TYPO3_CONF_VARS['SC_OPTIONS']['ext/install']['update']['sysext_file_uploads'] = 'Tx_File_UpgradeWizard_TtContentUploadsUpgradeWizard';

?>