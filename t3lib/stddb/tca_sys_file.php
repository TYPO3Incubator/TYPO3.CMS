<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$TCA['sys_file'] = array (
	'ctrl' => $TCA['sys_file']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'name,type,size'
	),
	'feInterface' => $TCA['sys_file']['feInterface'],
	'columns' => array (
		't3ver_label' => array (
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.versionLabel',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'max'  => '30',
			)
		),
		'sys_language_uid' => array (
			'exclude' => 1,
			'label'  => 'LLL:EXT:lang/locallang_general.xml:LGL.language',
			'config' => array (
				'type'                => 'select',
				'foreign_table'       => 'sys_language',
				'foreign_table_where' => 'ORDER BY sys_language.title',
				'items' => array(
					array('LLL:EXT:lang/locallang_general.xml:LGL.allLanguages', -1),
					array('LLL:EXT:lang/locallang_general.xml:LGL.default_value', 0)
				)
			)
		),
		'l10n_parent' => array (
			'displayCond' => 'FIELD:sys_language_uid:>:0',
			'exclude'     => 1,
			'label'       => 'LLL:EXT:lang/locallang_general.xml:LGL.l18n_parent',
			'config'      => array (
				'type'  => 'select',
				'items' => array (
					array('', 0),
				),
				'foreign_table'       => 'sys_file',
				'foreign_table_where' => 'AND sys_file.pid=###CURRENT_PID### AND sys_file.sys_language_uid IN (-1,0)',
			)
		),
		'l10n_diffsource' => array (
			'config' => array (
				'type' => 'passthrough'
			)
		),
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'starttime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.starttime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'default'  => '0',
				'checkbox' => '0'
			)
		),
		'endtime' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.endtime',
			'config'  => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '20',
				'eval'     => 'date',
				'checkbox' => '0',
				'default'  => '0',
				'range'    => array (
					'upper' => mktime(3, 14, 7, 1, 19, 2038),
					'lower' => mktime(0, 0, 0, date('m')-1, date('d'), date('Y'))
				)
			)
		),
		'storage' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file.storage',
			'config' => array (
				'type' => 'select',
				'items' => array (
					array('',0),
				),
				'foreign_table' => 'sys_file_storage',
				'foreign_table_where' => 'ORDER BY sys_file_storage.name',
				'size' => 1,
				'minitems' => 0,
				'maxitems' => 1,
			)
		),
		'name' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file.name',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'type' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file.type',
			'config' => array (
				'type' => 'select',
				'size' => '1',
				'items' => array(
					array('LLL:EXT:lang/locallang_tca.xlf:sys_file.type.unknown',  0),
					array('LLL:EXT:lang/locallang_tca.xlf:sys_file.type.text',     1),
					array('LLL:EXT:lang/locallang_tca.xlf:sys_file.type.image',    2),
					array('LLL:EXT:lang/locallang_tca.xlf:sys_file.type.audio',    3),
					array('LLL:EXT:lang/locallang_tca.xlf:sys_file.type.video',    4),
					array('LLL:EXT:lang/locallang_tca.xlf:sys_file.type.software', 5),
				),
			)
		),
		'mime_type' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file.mime_type',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'sha1' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file.sha1',
			'config' => array (
				'type' => 'input',
				'size' => '30',
				'readOnly' => 1,
			)
		),
		'size' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:lang/locallang_tca.xlf:sys_file.size',
			'config' => array (
				'type'     => 'input',
				'size'     => '8',
				'max'      => '30',
				'eval'     => 'int',
				'checkbox' => '0',
				'default' => 0
			)
		),
		'usage_count' => array (
			'exclude'     => 1,
			'label'       => 'LLL:EXT:lang/locallang_general.xml:usage_count',
			'config' => array (
				'type' => 'group',
				'internal_type' => 'db',
				'allowed' => '*',
				'size' => 5,
				'minitems' => 0,
				'maxitems' => 100,
				"MM_hasUidField" => TRUE,
				"MM" => "sys_file_reference",
			)
		),
	),
	'types' => array (
		'1' => array('showitem' => 'sys_language_uid;;;;1-1-1, l10n_parent, l10n_diffsource, hidden;;1, storage, name, type, mime_type, sha1, size')
	),
	'palettes' => array (
		'1' => array('showitem' => 'starttime, endtime')
	)
);

?>