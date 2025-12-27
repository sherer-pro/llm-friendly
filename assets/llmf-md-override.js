( function ( wp ) {
	if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.data || ! wp.element || ! wp.components ) {
		return;
	}

	var settings = window.LLMF_MD_OVERRIDE || {};
	var metaKey = settings.metaKey || '_llmf_md_content_override';
	var allowedPostTypes = Array.isArray( settings.postTypes ) ? settings.postTypes : [];

	var el = wp.element.createElement;
	var registerPlugin = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var TextareaControl = wp.components.TextareaControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;

	function OverridePanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );

		if ( allowedPostTypes.length && allowedPostTypes.indexOf( postType ) === -1 ) {
			return null;
		}

		var value = useSelect( function ( select ) {
			var meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			return meta[ metaKey ] || '';
		}, [] );

		var dispatch = useDispatch( 'core/editor' );

		function onChange( nextValue ) {
			var metaUpdate = {};
			metaUpdate[ metaKey ] = nextValue;
			dispatch.editPost( { meta: metaUpdate } );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name: 'llmf-md-override',
				title: settings.panelTitle || 'Markdown override',
				className: 'llmf-md-override-panel',
			},
			el( 'p', null, settings.help || 'If filled, this text will replace the post content in the Markdown export.' ),
			el( TextareaControl, {
				label: settings.label || 'Override content for Markdown export',
				value: value,
				onChange: onChange,
				rows: 12,
			} )
		);
	}

	registerPlugin( 'llmf-md-override', {
		render: OverridePanel,
		icon: null,
	} );
} )( window.wp );
