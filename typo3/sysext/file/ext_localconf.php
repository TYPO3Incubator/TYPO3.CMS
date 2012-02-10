<?php
if (!defined('TYPO3_MODE')) {
	die ('Access denied.');
}

$extDirectPath = 'EXT:file/Classes/Service/ExtDirect/';

t3lib_extMgm::registerExtDirectComponent('TYPO3.FileList.Service.ExtDirect.CollectionManagement', $extDirectPath . 'CollectionManagement.php:Tx_File_Service_ExtDirect_CollectionManagement');
t3lib_extMgm::registerExtDirectComponent('TYPO3.FileList.Service.ExtDirect.FilterManagement', $extDirectPath . 'FilterManagement.php:Tx_File_Service_ExtDirect_FilterManagement');
?>