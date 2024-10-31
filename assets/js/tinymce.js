;(function($) {
	'use strict';

	tinymce.PluginManager.add('PhanesDonorschoose', function(editor, url) {
		// Add a button that opens a window
		editor.addButton('PhanesDonorschoose', {
			// icon: ' Donorschoose__icon',
			text: 'Donorschoose',
			tooltip: 'Click to open Donorschoose shrotcode generator',
			onclick: function() {
				// Open window
				editor.windowManager.open({
					title: 'Donorschoose Shrotcode Generator',
					width: 415,
					height: 100,
					body: [
						{
							type: 'textbox',
							name: 'keywords',
							label: 'Keywords',
							value: '',
							tooltip: 'Add your query keywords here. Default is "3d printing".'
						},
					],
					onsubmit: function(e) {
						removeEmptyProp(e.data);
						var sc = wp.shortcode.string({
							tag: 'phanes_ds',
							attrs: e.data,
							type: 'single'
						});
						editor.insertContent(sc);
					}
				});
			}
		});
	});

	function removeEmptyProp( props ) {
		for( var prop in props ) {
			if (!props[prop] || props[prop] === '0') {
				delete props[prop];
			}       
		}
		return props;
	}

}(jQuery));
