/* MG Membership – admin */
( function ( $ ) {
	'use strict';

	/* Membership terms: show fields by billing type. */
	function billing() {
		var v = $( '.mgm-billing-type' ).val();
		if ( ! v ) { return; }
		$( '.mgm-show-paid' ).toggle( v !== 'free' );
		$( '.mgm-show-recurring' ).toggle( v === 'recurring' );
		$( '.mgm-show-onetime' ).toggle( v === 'one-time' );
	}
	$( document ).on( 'change', '.mgm-billing-type', billing );

	/* Lesson type toggles. */
	function lessonType() {
		var v = $( '.mgm-lesson-type' ).val();
		if ( ! v ) { return; }
		$( '.mgm-lesson-video' ).toggle( v === 'video' );
		$( '.mgm-lesson-quiz' ).toggle( v === 'quiz' );
	}
	$( document ).on( 'change', '.mgm-lesson-type', lessonType );

	/* Rule type: hide target for "all" types. */
	function ruleType() {
		var v = $( '.mgm-rule-type' ).val();
		if ( ! v ) { return; }
		$( '.mgm-rule-target' ).toggle( [ 'all_content', 'all_posts', 'all_pages' ].indexOf( v ) === -1 );
	}
	$( document ).on( 'change', '.mgm-rule-type', ruleType );

	/* Add-on cards toggle class. */
	$( document ).on( 'change', '.mgm-addon input', function () {
		$( this ).closest( '.mgm-addon' ).toggleClass( 'is-on', this.checked );
	} );

	/* ---------------- Curriculum builder ---------------- */
	function Builder( $root ) {
		this.$root = $root;
		this.lessons = $root.data( 'lessons' ) || {};
		this.sections = $root.data( 'curriculum' ) || [];
		this.$list = $root.find( '.mgm-sections' );
		this.$input = $root.find( 'input[name="mgm_curriculum_json"]' );
		this.render();
		var self = this;
		$root.on( 'click', '.mgm-add-section', function () {
			self.read();
			self.sections.push( { title: 'Section ' + ( self.sections.length + 1 ), lessons: [] } );
			self.render();
		} );
		$root.on( 'click', '.mgm-cb-remove-section', function () {
			if ( ! window.confirm( 'Remove this section? Lessons are not deleted.' ) ) { return; }
			$( this ).closest( '.mgm-cb-section' ).remove();
			self.sync();
		} );
		$root.on( 'click', '.mgm-cb-remove-lesson', function () {
			$( this ).closest( 'li' ).remove();
			self.sync();
		} );
		$root.on( 'click', '.mgm-cb-add-existing', function () {
			var $sec = $( this ).closest( '.mgm-cb-section' );
			var id = $sec.find( '.mgm-cb-existing' ).val();
			if ( id ) {
				$sec.find( '.mgm-cb-lessons' ).append( self.lessonItem( id ) );
				self.sync();
				self.refreshSelects();
			}
		} );
		$root.on( 'click', '.mgm-cb-create', function () {
			var $sec = $( this ).closest( '.mgm-cb-section' );
			var title = $.trim( $sec.find( '.mgm-cb-new-title' ).val() );
			var type = $sec.find( '.mgm-cb-new-type' ).val();
			if ( ! title ) { return; }
			var $btn = $( this ).prop( 'disabled', true );
			$.post( window.ajaxurl, {
				action: 'mgm_quick_lesson',
				_ajax_nonce: $root.data( 'nonce' ),
				course: $root.data( 'course' ),
				title: title,
				type: type
			} ).done( function ( res ) {
				if ( res && res.success ) {
					self.lessons[ res.data.id ] = { title: res.data.title, type: res.data.type, edit: res.data.edit };
					$sec.find( '.mgm-cb-lessons' ).append( self.lessonItem( res.data.id ) );
					$sec.find( '.mgm-cb-new-title' ).val( '' );
					self.sync();
					self.refreshSelects();
				}
			} ).always( function () { $btn.prop( 'disabled', false ); } );
		} );
		$root.on( 'input', '.mgm-cb-head input', function () { self.sync(); } );
		$root.closest( 'form' ).on( 'submit', function () { self.sync(); } );
	}

	Builder.prototype.used = function () {
		var ids = [];
		this.$list.find( '.mgm-cb-lessons li' ).each( function () { ids.push( String( $( this ).data( 'id' ) ) ); } );
		return ids;
	};

	Builder.prototype.options = function () {
		var used = this.used();
		var html = '<option value="">— Add existing lesson —</option>';
		var self = this;
		Object.keys( this.lessons ).forEach( function ( id ) {
			if ( used.indexOf( String( id ) ) === -1 ) {
				html += '<option value="' + id + '">' + $( '<div>' ).text( self.lessons[ id ].title ).html() + '</option>';
			}
		} );
		return html;
	};

	Builder.prototype.refreshSelects = function () {
		this.$list.find( '.mgm-cb-existing' ).html( this.options() );
	};

	Builder.prototype.lessonItem = function ( id ) {
		var l = this.lessons[ id ] || { title: '#' + id, type: 'text', edit: '' };
		var $li = $( '<li/>' ).attr( 'data-id', id );
		$li.append( '<span class="dashicons dashicons-menu"></span>' );
		$li.append( $( '<span class="mgm-cb-title"/>' ).text( l.title ) );
		$li.append( $( '<span class="mgm-cb-type"/>' ).text( l.type || 'text' ) );
		if ( l.edit ) {
			$li.append( $( '<a target="_blank" class="button-link"/>' ).attr( 'href', l.edit ).text( 'Edit' ) );
		}
		$li.append( '<button type="button" class="button-link mgm-cb-remove-lesson" aria-label="Remove">✕</button>' );
		return $li;
	};

	Builder.prototype.render = function () {
		var self = this;
		this.$list.empty();
		this.sections.forEach( function ( s ) {
			var $sec = $( '<div class="mgm-cb-section"/>' );
			var $head = $( '<div class="mgm-cb-head"><span class="dashicons dashicons-move"></span><input type="text" class="regular-text"><button type="button" class="button-link mgm-cb-remove-section">Remove</button></div>' );
			$head.find( 'input' ).val( s.title );
			var $ul = $( '<ul class="mgm-cb-lessons"/>' );
			( s.lessons || [] ).forEach( function ( id ) { $ul.append( self.lessonItem( id ) ); } );
			var $add = $( '<div class="mgm-cb-add"><select class="mgm-cb-existing"></select><button type="button" class="button mgm-cb-add-existing">Add</button>' +
				'<input type="text" class="mgm-cb-new-title" placeholder="New lesson title"><select class="mgm-cb-new-type"><option value="text">Text</option><option value="video">Video</option><option value="quiz">Quiz</option></select>' +
				'<button type="button" class="button button-primary mgm-cb-create">Create lesson</button></div>' );
			$sec.append( $head, $ul, $add );
			self.$list.append( $sec );
		} );
		this.refreshSelects();
		this.$list.sortable( { handle: '.mgm-cb-head .dashicons-move', update: function () { self.sync(); } } );
		this.$list.find( '.mgm-cb-lessons' ).sortable( { connectWith: '.mgm-cb-lessons', placeholder: 'ui-sortable-placeholder', update: function () { self.sync(); } } );
		this.sync();
	};

	Builder.prototype.read = function () {
		var out = [];
		this.$list.find( '.mgm-cb-section' ).each( function () {
			var ids = [];
			$( this ).find( '.mgm-cb-lessons li' ).each( function () { ids.push( parseInt( $( this ).data( 'id' ), 10 ) ); } );
			out.push( { title: $( this ).find( '.mgm-cb-head input' ).val(), lessons: ids } );
		} );
		this.sections = out;
		return out;
	};

	Builder.prototype.sync = function () {
		this.$input.val( JSON.stringify( this.read() ) );
	};

	$( function () {
		billing();
		lessonType();
		ruleType();
		var $cb = $( '#mgm-curriculum-builder' );
		if ( $cb.length ) {
			new Builder( $cb );
		}
	} );
	/* Settings → Appearance: colour pickers + live preview. */
	$( function () {
		var $preview = $( '#mgm-ui-preview' );
		var el = $preview[ 0 ];
		var fonts = $preview.data( 'fonts' ) || {};
		var colorVars = {
			ui_accent: '--mgm-accent', ui_accent_text: '--mgm-accent-contrast', ui_text: '--mgm-text', ui_muted: '--mgm-muted',
			ui_border: '--mgm-border', ui_surface: '--mgm-surface', ui_soft: '--mgm-soft', ui_success: '--mgm-success', ui_danger: '--mgm-danger'
		};
		var shadows = {
			none: 'none',
			soft: '0 1px 2px rgba(16,24,40,.05), 0 1px 3px rgba(16,24,40,.06)',
			strong: '0 4px 6px -2px rgba(16,24,40,.06), 0 12px 24px -6px rgba(16,24,40,.14)'
		};
		function setVar( name, value ) {
			if ( el && value !== undefined && value !== null && value !== '' ) {
				el.style.setProperty( name, value );
			}
		}
		function clamp( v, min, max, fallback ) {
			v = parseInt( v, 10 );
			return isNaN( v ) ? fallback : Math.max( min, Math.min( max, v ) );
		}
		function shape() {
			var r = clamp( $( '#ui_radius' ).val(), 0, 32, 12 );
			var s = $( '#ui_button_shape' ).val();
			setVar( '--mgm-radius', r + 'px' );
			setVar( '--mgm-btn-radius', 'pill' === s ? '999px' : ( 'square' === s ? '0px' : Math.round( r * 0.7 ) + 'px' ) );
		}
		function font() {
			var key = $( '#ui_font' ).val();
			var stack = 'custom' === key ? ( $( '#ui_font_custom' ).val() || 'inherit' ) : ( fonts[ key ] || 'inherit' );
			setVar( '--mgm-font', stack );
		}

		if ( $.fn.wpColorPicker ) {
			$( '.mgm-color' ).each( function () {
				var id = this.id;
				$( this ).wpColorPicker( {
					change: function ( e, ui ) {
						setVar( colorVars[ id ], ui.color.toString() );
					},
					clear: function () {
						setVar( colorVars[ id ], $( '#' + id ).data( 'default-color' ) );
					}
				} );
			} );
		}
		if ( ! el ) {
			return;
		}
		$( '#ui_radius, #ui_button_shape' ).on( 'input change', shape );
		$( '#ui_font, #ui_font_custom' ).on( 'input change', font );
		$( '#ui_font_size' ).on( 'input change', function () {
			setVar( '--mgm-font-size', clamp( this.value, 12, 22, 16 ) + 'px' );
		} );
		$( '#ui_heading_weight' ).on( 'change', function () {
			setVar( '--mgm-heading-weight', this.value );
		} );
		$( '#ui_shadow' ).on( 'change', function () {
			setVar( '--mgm-shadow', shadows[ this.value ] || shadows.soft );
		} );
	} );
	/* Forms & Fields builder: reorder, open/close, add/remove, live label. */
	$( function () {
		var $list = $( '.mgm-fb-list' );
		if ( ! $list.length ) {
			return;
		}
		if ( $.fn.sortable ) {
			$list.sortable( { handle: '.mgm-fb-handle', axis: 'y', placeholder: 'mgm-fb-placeholder', forcePlaceholderSize: true } );
		}
		$list.on( 'click', '.mgm-fb-toggle', function () {
			var $item = $( this ).closest( '.mgm-fb-item' ).toggleClass( 'is-open' );
			$( this ).attr( 'aria-expanded', $item.hasClass( 'is-open' ) ? 'true' : 'false' );
		} );
		$list.on( 'input', '.mgm-fb-label-input', function () {
			$( this ).closest( '.mgm-fb-item' ).find( '.mgm-fb-label' ).text( this.value || '…' );
		} );
		$list.on( 'change', '.mgm-fb-visible input[type=checkbox]', function () {
			$( this ).closest( '.mgm-fb-item' ).toggleClass( 'is-off', ! this.checked );
		} );
		$list.on( 'change', '.mgm-fb-type', function () {
			var choice = /^(select|radio)$/.test( this.value );
			$( this ).closest( '.mgm-fb-item' ).find( '.mgm-fb-choices' ).closest( 'p' ).toggle( choice );
		} ).find( '.mgm-fb-type' ).trigger( 'change' );
		$list.on( 'click', '.mgm-fb-remove', function () {
			$( this ).closest( '.mgm-fb-item' ).remove();
		} );
		$( '.mgm-fb-add' ).on( 'click', function () {
			var tpl = document.getElementById( 'mgm-fb-template' );
			if ( ! tpl ) {
				return;
			}
			var html = tpl.innerHTML.replace( /__N__/g, 'n' + Date.now() );
			var $item = $( html ).appendTo( $list );
			$item.find( '.mgm-fb-type' ).trigger( 'change' );
			$item.find( '.mgm-fb-label-input' ).trigger( 'focus' );
			$item[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
		} );
	} );
} )( jQuery );
