/* MG Membership – Gutenberg blocks (no build step required). */
( function ( wp, data ) {
	if ( ! wp || ! wp.blocks || ! data ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor || wp.editor;
	var InspectorControls = be.InspectorControls;
	var InnerBlocks = be.InnerBlocks;
	var useBlockProps = be.useBlockProps;
	var C = wp.components;
	var SSR = wp.serverSideRender;
	var i18n = data.i18n;

	var icon = el( 'svg', { width: 24, height: 24, viewBox: '0 0 24 24' },
		el( 'path', { d: 'M12 2a5 5 0 0 1 5 5v3h1a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h1V7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v3h6V7a3 3 0 0 0-3-3z', fill: 'currentColor' } )
	);

	function listToArray( v ) {
		return ( v || '' ).split( ',' ).filter( function ( x ) { return x !== ''; } );
	}

	function membershipChecklist( value, onChange ) {
		var selected = listToArray( value );
		if ( ! data.memberships.length ) {
			return el( 'p', { className: 'description' }, 'No memberships yet.' );
		}
		return el( Fragment, null, data.memberships.map( function ( m ) {
			return el( C.CheckboxControl, {
				key: m.value,
				label: m.label,
				checked: selected.indexOf( m.value ) !== -1,
				onChange: function ( on ) {
					var next = selected.filter( function ( s ) { return s !== m.value; } );
					if ( on ) { next.push( m.value ); }
					onChange( next.join( ',' ) );
				}
			} );
		} ) );
	}

	function fieldControl( f, attrs, setAttributes ) {
		var value = attrs[ f.key ];
		var set = function ( v ) { var o = {}; o[ f.key ] = String( v ); setAttributes( o ); };
		switch ( f.type ) {
			case 'select':
				return el( C.SelectControl, { key: f.key, label: f.label, value: value, options: f.options, onChange: set } );
			case 'memberships':
				return el( C.BaseControl, { key: f.key, label: f.label }, membershipChecklist( value, set ) );
			case 'number':
				return el( C.TextControl, { key: f.key, type: 'number', label: f.label, value: value, onChange: set } );
			case 'textarea':
			case 'editor':
				return el( C.TextareaControl, { key: f.key, label: f.label, value: value, onChange: set } );
			default:
				return el( C.TextControl, { key: f.key, label: f.label, value: value, onChange: set } );
		}
	}

	/* Registry blocks (server-side rendered). */
	data.blocks.forEach( function ( b ) {
		wp.blocks.registerBlockType( b.name, {
			apiVersion: 2,
			title: b.title,
			icon: icon,
			category: 'mg-membership',
			edit: function ( props ) {
				var blockProps = useBlockProps ? useBlockProps() : {};
				return el( 'div', blockProps,
					el( InspectorControls, null,
						b.fields.length ? el( C.PanelBody, { title: i18n.settings, initialOpen: true },
							b.fields.map( function ( f ) { return fieldControl( f, props.attributes, props.setAttributes ); } )
						) : null
					),
					el( C.Disabled, null, el( SSR, { block: b.name, attributes: props.attributes } ) )
				);
			},
			save: function () { return null; }
		} );
	} );

	/* Members-only container block. */
	wp.blocks.registerBlockType( 'mg-membership/protected', {
		apiVersion: 2,
		title: i18n.protected,
		icon: icon,
		category: 'mg-membership',
		edit: function ( props ) {
			var a = props.attributes;
			var blockProps = useBlockProps ? useBlockProps( { className: 'mgm-protected-block' } ) : { className: 'mgm-protected-block' };
			return el( 'div', blockProps,
				el( InspectorControls, null,
					el( C.PanelBody, { title: i18n.settings, initialOpen: true },
						el( C.SelectControl, {
							label: i18n.showTo,
							value: a.condition,
							options: data.conditions.filter( function ( c ) { return c.value !== ''; } ),
							onChange: function ( v ) { props.setAttributes( { condition: v } ); }
						} ),
						el( C.BaseControl, { label: i18n.memberships }, membershipChecklist( a.memberships, function ( v ) { props.setAttributes( { memberships: v } ); } ) ),
						el( C.ToggleControl, { label: i18n.showMessage, checked: !! a.showMessage, onChange: function ( v ) { props.setAttributes( { showMessage: v } ); } } )
					)
				),
				el( 'div', { className: 'mgm-protected-label' }, '🔒 ' + i18n.protected + ' — ' + ( data.conditions.filter( function ( c ) { return c.value === a.condition; } )[ 0 ] || {} ).label ),
				el( InnerBlocks, null )
			);
		},
		save: function () {
			var blockProps = useBlockProps && useBlockProps.save ? useBlockProps.save() : {};
			return el( 'div', blockProps, el( InnerBlocks.Content, null ) );
		}
	} );

	/* Visibility panel on every block. */
	var withVisibility = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var a = props.attributes;
			return el( Fragment, null,
				el( BlockEdit, props ),
				props.isSelected ? el( InspectorControls, null,
					el( C.PanelBody, { title: i18n.visibility, initialOpen: !! a.mgmCondition, icon: icon },
						el( C.SelectControl, {
							label: i18n.showTo,
							value: a.mgmCondition || '',
							options: data.conditions,
							onChange: function ( v ) { props.setAttributes( { mgmCondition: v } ); }
						} ),
						a.mgmCondition && a.mgmCondition !== 'logged_in' && a.mgmCondition !== 'guest' ?
							el( C.BaseControl, { label: i18n.memberships }, membershipChecklist( a.mgmMemberships, function ( v ) { props.setAttributes( { mgmMemberships: v } ); } ) ) : null
					)
				) : null
			);
		};
	}, 'mgmWithVisibility' );

	wp.hooks.addFilter( 'blocks.registerBlockType', 'mg-membership/visibility-attrs', function ( settings ) {
		settings.attributes = Object.assign( {}, settings.attributes, {
			mgmCondition: { type: 'string', default: '' },
			mgmMemberships: { type: 'string', default: '' }
		} );
		return settings;
	} );
	wp.hooks.addFilter( 'editor.BlockEdit', 'mg-membership/visibility-panel', withVisibility );
} )( window.wp, window.MGMBlocks );
