
Ext.define('TYPO3.File.FileToolbar', {
	extend: 'Ext.toolbar.Paging',
	alias: 'widget.TYPO3-File-FileToolbar',
	prependButtons: true,
	displayInfo: false,
	constructor: function(cfg) {
		this.items = [
			{
				xtype: 'button',
				action: 'delete',
				iconCls: 't3-icon t3-icon-actions t3-icon-actions-edit t3-icon-edit-delete',
				text: 'Delete Selected Files'
			},
			{
				xtype: 'button',
				action: 'upload',
				iconCls: 't3-icon t3-icon-actions t3-icon-actions-edit t3-icon-edit-upload',
				text: 'Upload Files'
			},
			{
				xtype: 'button',
				action: 'edit',
				iconCls: 't3-icon t3-icon-actions t3-icon-actions-edit',
				text: 'Edit File Information'
			},
			"|",
			{
				xtype: 'checkbox',
				boxLabel: 'Show Thumbnails',
				ui: 'custom',
				checked: true,
				listeners: {
					change: this.toggleThumbnails
				}
			},
			"->",
			{xtype: "thumbnailColumnResizer"}
		];
		this.callParent([cfg]);
	},
	afterRender: function() {
		this.callParent(arguments);
		this.down('button[action=delete]').setHandler(this.deleteRecords);
		this.down('button[action=edit]').setHandler(this.editRecords);
		this.down('button[action=upload]').setHandler(this.uploadFiles);
	},
	editRecords: function() {
		var selected = this.up('gridpanel').getSelectionModel().getSelection();
		console.debug(selected);
		//TYPO3.Vidi.Actions.File.startIndexing(selected, '_FILE');
	},
	uploadFiles: function() {
		var filteredDataString = Ext.ComponentManager.get('TYPO3-Vidi-Module-Grid').store.getProxy().extraParams.query;
		var pathIdentifier = '';
		if (filteredDataString) {
			var filteredData = Ext.decode(filteredDataString);
			pathIdentifier = filteredData[0].search;
		}
		TYPO3.Vidi.Actions.File.uploadFiles(pathIdentifier);
	},
	refreshGrid: function() {
		var store = Ext.ComponentManager.get('TYPO3-Vidi-Module-Grid').store;
		store.loadPage(store.currentPage);
	},
	toggleThumbnails: function(cb, newState, oldState, event) {
		grid = Ext.ComponentManager.get('TYPO3-Vidi-Module-Grid');
		Ext.each(grid.columns, function(column) {
			if (column.alias[0] == 'widget.thumbnailColumn') {
				column.setVisible(newState);
			}
		});
		grid.down('gridview').refresh();
	}
});

TYPO3.TYPO3.Core.Registry.set('vidi/mainModule/gridToolbar', 'TYPO3-File-FileToolbar', 99);