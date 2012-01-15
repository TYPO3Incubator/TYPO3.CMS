<?php

class Tx_File_Service_VidiDragAndDrop implements Tx_Vidi_Service_DragAndDrop {

	/**
	 * @var t3lib_extFileFunctions
	 */
	protected $fileProcessor;

	public function dropGridRecordOnTree($gridTable, $gridRecord, $treeTable, $treeRecord, $copy = false) {
		$target = t3lib_div::trimExplode(':', $treeRecord->id);

		$this->fileFactory = t3lib_div::makeInstance('t3lib_file_Factory');

			// Initializing:
		$this->fileProcessor = t3lib_div::makeInstance('t3lib_extFileFunctions');
		$this->fileProcessor->init($GLOBALS['FILEMOUNTS'], $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']);
		$this->fileProcessor->init_actionPerms($GLOBALS['BE_USER']->getFileoperationPermissions());
		$this->fileProcessor->dontCheckForUnique = t3lib_div::_GP('overwriteExistingFiles') ? 1 : 0; // @todo change this to fit Vidi UI

		if($copy) {
			$fileValues = array(
				'copy' => array(
					array(
						'data' => $gridRecord->id,
						'target' => $treeRecord->id,
					)
				)
			);

		} else {
			$fileValues = array(
				'move' => array(
					array(
						'data' => $gridRecord->id,
						'target' => $treeRecord->id,
					)
				)
			);
		}
		$this->fileProcessor->start($fileValues);
		$this->fileProcessor->processData();
	}

}