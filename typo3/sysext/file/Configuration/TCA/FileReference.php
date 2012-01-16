<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$TCA['sys_file_reference'] = array (
	'ctrl' => $TCA['sys_file_reference']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,uid_local,uid_foreign,tablenames,fieldname,sorting_foreign,table_local,title,description,downloadname'
	),
	'feInterface' => $TCA['sys_file_reference']['feInterface'],
	'columns' => array (
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'uid_local' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.uid_local',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'size' => 1,
				'maxitems' => 1,
				'minitems' => 0,
				'allowed' => 'sys_file',
				'filter' => array(

					/*

					Documentation:
						The filter has two main items "fileType" and "fileExtension".
						Each of them as the keys "allowed" and "disallowed".

						=> fileType can contain types from the t3lib_file_File::FILETYPE_* class constants
						=> fileExtension can contain file extensions like jpg, bmp, etc.

						The fileType directive is always processed first, then fileExtension.
						Disallowed takes precedence over allowed.
						If something is specified to be disallowed, everything else is assumed to be allowed.
						If something is specified to be allowed, everything else is assumed to be disallowed.

						If no filter is set, everything is assumed to be allowed.

					Example:

					// Enable only Images or Videos, but exclude "*.bmp" files.

					'fileType' => array(
						'allowed' => array(t3lib_file_File::FILETYPE_IMAGE, t3lib_file_File::FILETYPE_VIDEO),
						'disallowed' => array(),
					),
					'fileExtension' =>  array(
						'allowed' => array(),
						'disallowed' => array('bmp'),
					),

					*/

					'fileType' => array(
						'allowed' => array(),
						'disallowed' => array(),
					),
				), // TODO: Verify that this filter is respected in both TCEForms/ElementBrowser and TCEMain
			),
		),
		'uid_foreign' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.uid_foreign',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('',0),
				),
				'foreign_table' => 'tt_content',
				'foreign_table_where' => 'ORDER BY tt_content.uid',
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'tablenames' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.tablenames',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'eval' => 'trim',
			)
		),
		'fieldname' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.fieldname',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'default' => 'images',
			)
		),
		'sorting_foreign' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.sorting_foreign',
			'config' => array (
				'type'     => 'input',
				'size'     => '4',
				'max'      => '4',
				'eval'     => 'int',
				'checkbox' => '0',
				'range'    => array (
					'upper' => '1000',
					'lower' => '10'
				),
				'default' => 0
			)
		),
		'table_local' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.table_local',
			'config' => array (
				'type' => 'input',
				'size' => '20',
				'default' => 'sys_file',
			)
		),
		'title' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.title',
			'config' => array (
				'type' => 'input',
				'size' => '22',
				'placeholder' => '__row|__foreign|name',
			)
		),
		'link' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.link',
			'config' => array(
				'type' => 'input',
				'size' => '20',
				'wizards' => array(
					'_PADDING' => 2,
					'link' => array(
						'type' => 'popup',
						'title' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.link',
						'icon' => 'link_popup.gif',
						'script' => 'browse_links.php?mode=wizard',
						'JSopenParams' => 'height=300,width=500,status=0,menubar=0,scrollbars=1',
					),
				),
			),
		),
		'description' => array ( // This is used for captions in the frontend
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.description',
			'config' => array (
				'type' => 'text',
				'cols' => '24',
				'rows' => '5',
			)
		),
		'alternative' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.alternative',
			'config' => array (
				'type' => 'input',
				'size' => '22',
				'placeholder' => '__row|__foreign|name',
			)
		),
		'downloadname' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.downloadname',
			'config' => array (
				'type' => 'input',
				'size' => '20',
				'placeholder' => '__row|__foreign|name',
			)
		),
	),
	'types' => array (
		'0' => array(
			'showitem' => '
				--palette--;LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.overlayPalette;overlayPalette,
				--palette--;;filePalette',
		),
		t3lib_file_File::FILETYPE_TEXT => array(
			'showitem' => '
				--palette--;LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.overlayPalette;overlayPalette,
				--palette--;;filePalette',
		),
		t3lib_file_File::FILETYPE_IMAGE => array(
			'showitem' => '
				--palette--;LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.overlayPalette;overlayPalette,
				--palette--;;filePalette',
		),
		t3lib_file_File::FILETYPE_AUDIO => array(
			'showitem' => '
				--palette--;LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.overlayPalette;overlayPalette,
				--palette--;;filePalette',
		),
		t3lib_file_File::FILETYPE_VIDEO => array(
			'showitem' => '
				--palette--;LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.overlayPalette;overlayPalette,
				--palette--;;filePalette',
		),
		t3lib_file_File::FILETYPE_SOFTWARE => array(
			'showitem' => '
				--palette--;LLL:EXT:file/Resources/Private/Language/db.xlf:sys_file_reference.overlayPalette;overlayPalette,
				--palette--;;filePalette',
		),
	),
	'palettes' => array (
		'overlayPalette' => array(
			'showitem' => '
				title,alternative;;;;3-3-3,--linebreak--,
				link,description
				',
			'canNotCollapse' => true,
		),
		'filePalette' => array(
			'showitem' => 'uid_local',
			'isHiddenPalette' => true,
		),
	)
);

?>
