( function ( $, wp ) {
	'use strict';

	var config = window.mySliderProAdmin || {};

	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return new Promise( function ( resolve, reject ) {
			var textarea = document.createElement( 'textarea' );

			textarea.value = text;
			textarea.setAttribute( 'readonly', '' );
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				if ( document.execCommand( 'copy' ) ) {
					resolve();
				} else {
					reject();
				}
			} catch ( error ) {
				reject( error );
			}

			document.body.removeChild( textarea );
		} );
	}

	$( document ).on( 'click', '.psp-shortcode-copy', function () {
		var $button = $( this );
		var $status = $button.siblings( '.psp-shortcode-copy-status' );
		var originalText = $button.find( '.psp-shortcode-copy-label' ).text();
		var shortcode = String( $button.attr( 'data-shortcode' ) || '' );

		if ( ! shortcode ) {
			return;
		}

		copyText( shortcode ).then( function () {
			$button.find( '.psp-shortcode-copy-label' ).text( config.copiedText || 'Copied!' );
			$status.text( config.copiedText || 'Copied!' );
			window.setTimeout( function () {
				$button.find( '.psp-shortcode-copy-label' ).text( originalText || config.copyText || 'Copy' );
			}, 1800 );
		} ).catch( function () {
			$status.text( config.copyFailedText || 'Copy failed' );
		} );
	} );

	$( document ).on( 'submit', '.psp-delete-form', function ( event ) {
		var message = String( $( this ).attr( 'data-confirm' ) || '' );

		if ( message && ! window.confirm( message ) ) {
			event.preventDefault();
		}
	} );

	$( document ).on( 'click', '.psp-rename-toggle', function () {
		var target = String( $( this ).attr( 'data-psp-rename-target' ) || '' );
		var $form = target ? $( '#' + target ) : $();

		if ( ! $form.length ) {
			return;
		}

		$( this ).prop( 'hidden', true );
		$form.prop( 'hidden', false ).find( 'input[name="slider_title"]' ).trigger( 'focus' ).trigger( 'select' );
	} );

	$( document ).on( 'click', '.psp-rename-cancel', function () {
		var $form = $( this ).closest( '.psp-quick-rename-form' );

		$form.prop( 'hidden', true );
		$( '[data-psp-rename-target="' + $form.attr( 'id' ) + '"]' ).prop( 'hidden', false );
	} );

	// Overview: assign a card thumbnail from the Media Library, then submit the
	// per-card form so the choice persists as slider meta.
	var thumbnailFrame = null;
	var $thumbnailForm = null;
	$( document ).on( 'click', '.psp-set-thumbnail', function () {
		$thumbnailForm = $( this ).closest( '.psp-set-thumbnail-form' );

		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		if ( ! thumbnailFrame ) {
			thumbnailFrame = window.wp.media( {
				title: config.thumbnailFrameTitle || 'Choose slider thumbnail',
				button: { text: config.thumbnailFrameButton || 'Use as thumbnail' },
				library: { type: 'image' },
				multiple: false
			} );
			thumbnailFrame.on( 'select', function () {
				var attachment = thumbnailFrame.state().get( 'selection' ).first();
				var id = attachment ? parseInt( attachment.id, 10 ) : 0;

				if ( ! id || ! $thumbnailForm || ! $thumbnailForm.length ) {
					return;
				}
				$thumbnailForm.find( '.psp-thumbnail-id' ).val( id );
				$thumbnailForm.trigger( 'submit' );
			} );
		}
		thumbnailFrame.open();
	} );

	function syncImportForm( input ) {
		var $form = $( input ).closest( '.psp-import-form' );
		var $name = $form.find( '.psp-import-filename' );
		var hasFile = !!( input.files && input.files.length );
		var label = hasFile ? input.files[ 0 ].name : String( $name.attr( 'data-empty' ) || '' );

		$name.text( label ).toggleClass( 'is-selected', hasFile );
		$form.find( '.psp-import-submit' ).prop( 'disabled', ! hasFile );
	}

	$( document ).on( 'change', '.psp-import-file', function () {
		syncImportForm( this );
	} );

	// Sync on load so a file restored from bfcache does not leave Import disabled.
	$( '.psp-import-file' ).each( function () {
		syncImportForm( this );
	} );

	function syncAutoplayDelay() {
		var on = $( '#my-slider-pro-autoplay' ).prop( 'checked' );

		// The select stays enabled so its value always posts (a disabled
		// control is dropped from the form and would reset the saved delay);
		// dimming is a visual cue that the delay only applies while autoplay is on.
		$( '#my-slider-pro-interval' ).closest( '.psp-check-subfield' ).toggleClass( 'is-disabled', ! on );
	}

	$( document ).on( 'change', '#my-slider-pro-autoplay', syncAutoplayDelay );

	if ( $( '#my-slider-pro-autoplay' ).length ) {
		syncAutoplayDelay();
	}

	// Show the Max-width field only when the slider width is Boxed.
	$( document ).on( 'change', '#my-slider-pro-width', function () {
		$( '.psp-width-max' ).toggleClass( 'is-hidden', 'boxed' !== $( this ).val() );
	} );

	function openSettingsModal() {
		var $modal = $( '#psp-settings-modal' );

		if ( ! $modal.length ) {
			return;
		}
		$modal.prop( 'hidden', false );
		$( 'body' ).addClass( 'psp-modal-open' );
		$modal.find( 'select, input, button' ).not( '[data-psp-settings-close]' ).first().trigger( 'focus' );
	}

	function closeSettingsModal() {
		var $modal = $( '#psp-settings-modal' );

		if ( ! $modal.length || $modal.prop( 'hidden' ) ) {
			return;
		}
		$modal.prop( 'hidden', true );
		$( 'body' ).removeClass( 'psp-modal-open' );
		$( '.psp-open-settings' ).trigger( 'focus' );
	}

	$( document ).on( 'click', '.psp-open-settings', openSettingsModal );
	$( document ).on( 'click', '[data-psp-settings-close]', closeSettingsModal );
	$( document ).on( 'keydown', function ( event ) {
		if ( 'Escape' === event.key ) {
			closeSettingsModal();
		}
	} );

	// The locked background layer row jumps to the Background settings panel.
	$( document ).on( 'click', '[data-psp-focus-background]', function () {
		var $bg = $( '#psp-background-settings' );

		if ( ! $bg.length ) {
			return;
		}
		if ( $bg[0].scrollIntoView ) {
			$bg[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
		}
		$bg.addClass( 'psp-flash' );
		window.setTimeout( function () {
			$bg.removeClass( 'psp-flash' );
		}, 900 );
	} );

	if ( ! window.wp || ! window.wp.media ) {
		return;
	}

	var $list = $( '#my-slider-pro-images' );

	if ( ! $list.length ) {
		return;
	}

	var $addButton = $( '#my-slider-pro-add-images' );
	var $count = $( '#my-slider-pro-image-count' );
	var $empty = $( '#my-slider-pro-empty-images' );
	var $status = $( '#my-slider-pro-image-status' );
	var $emptyPreview = $( '#my-slider-pro-empty-preview' );
	var $preview = $( '#my-slider-pro-preview' );
	var $layerWorkspace = $( '#psp-preview-panel' ).add( '.psp-editor-sidebar' ).add( '#psp-background-settings' );
	var $previewViewport = $( '#my-slider-pro-preview-viewport' );
	var $layerInspectorName = $( '#psp-layer-inspector-name' );
	var $layerInspectorDevice = $( '#psp-layer-inspector-device' );
	var $layerInspectorX = $( '#psp-layer-inspector-x' );
	var $layerInspectorY = $( '#psp-layer-inspector-y' );
	var $slideSelect = $( '#my-slider-pro-active-slide' );
	var $activeSlideFields = $( '#psp-active-slide-fields' );
	var $editorForm = $( 'form.psp-editor-form' );
	var $inspectorTabs = $( '.psp-inspector-tab' );
	var $inspectorPanels = $( '[data-psp-inspector-panel]' );
	var $slideLayers = $( '.psp-slide-layers-panel' );
	var $slideLayersEmpty = $( '#psp-slide-layers-empty' );
	var $deviceButtons = $( '.psp-device-button' );
	var $settings = $( '#my-slider-pro-height, #my-slider-pro-tablet-height, #my-slider-pro-mobile-height, #my-slider-pro-content-position, #my-slider-pro-tablet-content-position, #my-slider-pro-mobile-content-position, #my-slider-pro-tablet-text-width, #my-slider-pro-mobile-text-width, #my-slider-pro-tablet-button-size, #my-slider-pro-mobile-button-size, #my-slider-pro-interval, input[name="slider_arrows"], input[name="slider_hide_arrows_on_phone"], input[name="slider_dots"], input[name="slider_autoplay"], input[name="slider_loop"], input[name="slider_pause_on_hover"]' );
	var maxImages = parseInt( config.maxImages, 10 ) || 200;
	var frame;
	var imageLayerFrame;
	var inspectorImageFrame;
	var addImageFrame;
	var addImageFieldset;
	var replaceImageFrame;
	var replaceImageTarget;
	var extraImageLayerFrame;
	var $activeImageLayerField;
	var activePreviewIndex = 0;
	var activePreviewDevice = 'desktop';
	var activeEditorLayer = 'heading';
	var showEditorOverlay = true;
	var dragState = null;
	var resizeState = null;

	function formatText( template, title, position, total ) {
		return String( template || '' )
			.replace( '%1$s', title )
			.replace( '%2$d', position )
			.replace( '%3$d', total );
	}

	function announce( message ) {
		$status.text( '' );
		window.setTimeout( function () {
			$status.text( message );
		}, 0 );
	}

	function selectedIds() {
		return $list.children( '.psp-media-item' ).map( function () {
			return parseInt( $( this ).attr( 'data-attachment-id' ), 10 );
		} ).get().filter( function ( id ) {
			return id > 0;
		} );
	}

	function itemTitle( $item ) {
		return $.trim( $item.find( '.psp-media-title' ).first().text() ) || config.imageFallback || 'Image';
	}

	function slideOptionLabel( $item, index ) {
		return 'Slide ' + ( index + 1 ) + ' - ' + itemTitle( $item );
	}

	function extraLayerLabel( type, index ) {
		var labels = {
			heading: config.headingLayerLabel || 'Heading',
			description: config.descriptionLayerLabel || 'Text',
			button: config.buttonLayerLabel || 'Button',
			image: config.imageLayerLabel || 'Image',
			shape: config.shapeLayerLabel || 'Shape'
		};

		return ( labels[ type ] || labels.heading ) + ' ' + ( index + 1 );
	}

	// Number overlay layers within their own type (Heading 1, Heading 2, Button 1…)
	// rather than by their global position, so a single layer of each type reads "1".
	function extraLayerLabels( extraLayers ) {
		var counts = {};

		return ( extraLayers || [] ).map( function ( layer ) {
			counts[ layer.type ] = ( counts[ layer.type ] || 0 ) + 1;
			return extraLayerLabel( layer.type, counts[ layer.type ] - 1 );
		} );
	}

	function extraLayerExists( layer ) {
		if ( 'shape' === layer.type ) {
			// A shape is defined by its box and fill, not by text or a URL, so it
			// always renders once added.
			return true;
		}
		return 'image' === layer.type ? !! layer.url : !! layer.text;
	}

	function extraLayerStyleKey( styleKey ) {
		var map = {
			text_color: 'color',
			description_color: 'color',
			button_text_color: 'color',
			button_background: 'background',
			font_family: 'font_family',
			description_font_family: 'font_family',
			button_font_family: 'font_family',
			heading_font_style: 'font_style',
			description_font_style: 'font_style',
			button_font_style: 'font_style',
			heading_size: 'size',
			description_size: 'size',
			button_font_size: 'size',
			image_width: 'width',
			heading_opacity: 'opacity',
			description_opacity: 'opacity',
			button_opacity: 'opacity',
			image_opacity: 'opacity',
			heading_link_url: 'link_url',
			description_link_url: 'link_url',
			button_url: 'link_url',
			image_link_url: 'link_url',
			image_layer_url: 'url',
			image_layer_alt: 'alt',
			shape_fill: 'background',
			shape_radius: 'radius',
			shape_width: 'width',
			shape_height: 'height',
			shape_opacity: 'opacity',
			shape_overlay_type: 'overlay_type',
			shape_overlay_color: 'overlay_color',
			shape_overlay_color2: 'overlay_color2',
			shape_overlay_opacity: 'overlay_opacity',
			shape_overlay_direction: 'overlay_direction'
		};

		return map[ styleKey ] || '';
	}

	function editableFieldForLayer( layer ) {
		var extraIndex = extraLayerIndex( layer );

		if ( extraIndex >= 0 ) {
			return {
				extraIndex: extraIndex,
				key: 'text',
				selector: '[name$="[text]"]'
			};
		}

		if ( 'heading' === layer ) {
			return { extraIndex: -1, key: 'title', selector: '[name$="[title]"]' };
		}
		if ( 'description' === layer ) {
			return { extraIndex: -1, key: 'description', selector: '[name$="[description]"]' };
		}
		if ( 'button' === layer ) {
			return { extraIndex: -1, key: 'button_label', selector: '[name$="[button_label]"]' };
		}

		return null;
	}

	function editableAttributes( layer, label ) {
		return {
			'class': 'psp-inline-editable',
			contenteditable: 'plaintext-only',
			'data-psp-edit-layer': layer,
			role: 'textbox',
			'aria-label': label
		};
	}

	function activeLayerStripItems() {
		return $slideLayers.find( '.psp-layer-strip-items' ).first();
	}

	function activeSlideScope() {
		return activeSlideItem().add( $activeSlideFields );
	}

	function slideScope( $item ) {
		return $item.is( activeSlideItem() ) ? $item.add( $activeSlideFields ) : $item;
	}

	function activateInspectorTab( name ) {
		if ( ! $inspectorTabs.length ) {
			return;
		}
		$inspectorTabs.each( function () {
			var isActive = name === String( $( this ).attr( 'data-psp-inspector-tab' ) || '' );

			$( this ).toggleClass( 'is-active', isActive ).attr( 'aria-selected', isActive ? 'true' : 'false' );
		} );
		$inspectorPanels.each( function () {
			$( this ).prop( 'hidden', name !== String( $( this ).attr( 'data-psp-inspector-panel' ) || '' ) );
		} );
	}

	function fieldName( attachmentId, key ) {
		return 'my_slider_pro_slide_content[' + attachmentId + '][' + key + ']';
	}

	function makeField( attachmentId, key, label, type, value, wide ) {
		var $label = $( '<label>', { 'class': 'psp-field' + ( wide ? ' psp-field-wide' : '' ) } );
		var $picker;
		var $input;

		$label.append( $( '<span>' ).text( label ) );
		if ( 'textarea' === type ) {
			$input = $( '<textarea>', {
				'class': 'psp-slide-content-input',
				name: fieldName( attachmentId, key ),
				rows: 3,
				maxlength: 280
			} ).val( value || '' );
		} else {
			$input = $( '<input>', {
				'class': 'psp-slide-content-input',
				type: type,
				name: fieldName( attachmentId, key ),
				value: value || '',
				maxlength: 'title' === key ? 120 : ( 'button_label' === key ? 80 : 2048 )
			} );
		}

		if ( 'image_layer_url' === key ) {
			$picker = $( '<span>', { 'class': 'psp-image-layer-picker' } );
			$picker.append( $input );
			$picker.append( $( '<button>', {
				type: 'button',
				'class': 'button psp-select-image-layer',
				text: value ? ( config.imageLayerChangeLabel || 'Change image layer' ) : ( config.imageLayerChooseLabel || 'Add image layer' )
			} ) );
			$label.append( $picker );
		} else {
			$label.append( $input );
		}
		return $label;
	}

	function extraLayerName( attachmentId, index, key ) {
		return 'my_slider_pro_slide_content[' + attachmentId + '][extra_layers][' + index + '][' + key + ']';
	}

	function deleteLayer( layerKey ) {
		var extraIndex = extraLayerIndex( layerKey );
		var clearKey;

		if ( ! layerKey ) {
			return;
		}
		if ( extraIndex >= 0 ) {
			extraLayerRow( extraIndex ).remove();
		} else {
			clearKey = 'heading' === layerKey ? 'title' : ( 'description' === layerKey ? 'description' : ( 'button' === layerKey ? 'button_label' : 'image_layer_url' ) );
			activeSlideScope().find( '[name$="[' + clearKey + ']"]' ).val( '' );
		}
		activeEditorLayer = 'heading';
		refreshPreview();
		announce( config.layerDeletedText || 'Layer removed.' );
	}

	// Count a slide's layers of one type (base content + overlay layers) so the
	// editor can enforce the same per-type cap the server does.
	function layerTypeCount( type ) {
		var slide = previewSlides()[ activePreviewIndex ];

		if ( ! slide ) {
			return 0;
		}
		var base = ( 'heading' === type && slide.title ) ||
			( 'description' === type && slide.description ) ||
			( 'button' === type && slide.buttonLabel ) ||
			( 'image' === type && slide.imageLayerUrl );
		var extras = ( slide.extraLayers || [] ).filter( function ( layer ) {
			return layer.type === type;
		} ).length;

		return ( base ? 1 : 0 ) + extras;
	}

	function canAddLayerType( type ) {
		var max = parseInt( config.maxLayersPerType, 10 ) || 2;

		return layerTypeCount( type ) < max;
	}

	// Grey out each Add Layer button whose type is already at the per-slide cap.
	function syncAddLayerButtons() {
		[ 'heading', 'description', 'button', 'image', 'shape' ].forEach( function ( type ) {
			var full = ! canAddLayerType( type );

			$layerWorkspace.find( '.psp-add-extra-layer[data-psp-extra-layer-type="' + type + '"]' )
				.prop( 'disabled', full )
				.attr( 'aria-disabled', full ? 'true' : 'false' );
		} );
	}

	function addExtraLayer( $fieldset, type, preset ) {
		var attachmentId = parseInt( $fieldset.attr( 'data-psp-extra-layers' ), 10 );
		var index = parseInt( $fieldset.attr( 'data-psp-next-extra-layer' ), 10 ) || 0;
		var $row = makeExtraLayerRow( attachmentId, index, type );

		$fieldset.find( '.psp-extra-layer-list' ).append( $row );
		$fieldset.attr( 'data-psp-next-extra-layer', index + 1 );
		if ( preset && preset.url ) {
			$row.find( '[name$="[url]"]' ).val( preset.url );
		}
		if ( preset && preset.alt ) {
			$row.find( '[name$="[alt]"]' ).val( preset.alt );
		}
		// Field names use the monotonic index to stay unique, but the canvas,
		// pickers, and inspector address layers by their position among the
		// current rows. Select by position so a layer added after a delete is
		// the one that becomes active.
		activeEditorLayer = 'extra-' + activeSlideScope().find( '[data-psp-extra-layer-row]' ).index( $row );
		activateInspectorTab( 'layer' );
		neutralizeSlideStore();
		refreshPreview();
		return $row;
	}

	function openAddImageLayer( $fieldset ) {
		addImageFieldset = $fieldset;

		if ( ! addImageFrame ) {
			addImageFrame = window.wp.media( {
				title: config.imageLayerFrameTitle || 'Choose image layer',
				button: { text: config.imageLayerFrameButton || 'Use image layer' },
				library: { type: 'image' },
				multiple: false
			} );
			addImageFrame.on( 'select', function () {
				var attachment = addImageFrame.state().get( 'selection' ).first();
				var data;

				if ( ! attachment || ! addImageFieldset || ! addImageFieldset.length ) {
					return;
				}
				data = attachment.toJSON();
				addExtraLayer( addImageFieldset, 'image', { url: data.url || '', alt: data.alt || data.title || '' } );
			} );
		}
		addImageFrame.open();
	}

	function makeExtraLayerRow( attachmentId, index, type ) {
		var isShape = 'shape' === type;
		var shapeWidth = isShape ? '320' : '220';
		var defaults = {
			type: type || 'heading',
			text: 'button' === type ? 'Button label' : ( 'image' === type || isShape ? '' : 'New ' + type ),
			url: '',
			link_url: '',
			alt: '',
			color: 'button' === type ? '#172033' : '#ffffff',
			background: isShape ? '#3858e9' : '#ffffff',
			font_family: 'montserrat',
			font_style: 'default',
			size: 'heading' === type ? '64' : ( 'button' === type ? '16' : '20' ),
			tablet_size: 'heading' === type ? '64' : ( 'button' === type ? '16' : '20' ),
			mobile_size: 'heading' === type ? '64' : ( 'button' === type ? '16' : '20' ),
			opacity: '100',
			desktop_x: '50',
			desktop_y: '50',
			tablet_x: '50',
			tablet_y: '50',
			mobile_x: '50',
			mobile_y: '50',
			width: shapeWidth,
			tablet_width: shapeWidth,
			mobile_width: shapeWidth,
			height: '200',
			radius: '0',
			ratio_locked: '',
			overlay_type: 'none',
			overlay_color: '#08101f',
			overlay_color2: '#000000',
			overlay_opacity: '50',
			overlay_direction: 'to bottom',
			size_linked: '1',
			pos_linked: '',
			animation: 'fade',
			animation_delay: '0',
			animation_duration: '600',
			animation_easing: 'ease-out'
		};
		var $row = $( '<details>', { 'class': 'psp-extra-layer-row', 'data-psp-extra-layer-row': '', open: true } );
		var $summary = $( '<summary>', { 'class': 'psp-extra-layer-summary' } );
		var $fields = $( '<div>', { 'class': 'psp-extra-layer-fields' } );
		var typeOptions = [
			[ 'heading', 'Heading' ],
			[ 'description', config.descriptionLayerLabel || 'Text' ],
			[ 'button', 'Button' ],
			[ 'image', 'Image' ],
			[ 'shape', config.shapeLayerLabel || 'Shape' ]
		];
		var fontOptions = [
			[ 'theme', 'Theme default' ],
			[ 'poppins', 'Poppins' ],
			[ 'montserrat', 'Montserrat' ],
			[ 'inter', 'Inter' ]
		];

		function label( text, control ) {
			return $( '<label>' ).append( $( '<span>' ).text( text ), control );
		}
		function input( key, inputType, attrs ) {
			return $( '<input>', $.extend( {
				'class': 'psp-slide-content-input',
				type: inputType,
				name: extraLayerName( attachmentId, index, key ),
				value: defaults[ key ] || ''
			}, attrs || {} ) );
		}
		function select( key, options ) {
			var $select = $( '<select>', {
				'class': 'psp-slide-content-input',
				name: extraLayerName( attachmentId, index, key )
			} );
			options.forEach( function ( option ) {
				$select.append( $( '<option>', { value: option[0], text: option[1], selected: option[0] === defaults[ key ] } ) );
			} );
			return $select;
		}

		$summary.append(
			$( '<span>', { 'class': 'psp-extra-layer-type-label', text: extraLayerLabel( defaults.type, index ) } ),
			$( '<span>', { 'class': 'psp-extra-layer-summary-note', text: 'Overlay layer' } ),
			$( '<button>', { type: 'button', 'class': 'button-link-delete psp-remove-extra-layer', text: 'Remove' } )
		);
		$fields.append(
			label( 'Type', select( 'type', typeOptions ) ),
			label( 'Text', input( 'text', 'text', { maxlength: 280 } ) ),
			label( 'Image URL', $( '<span>', { 'class': 'psp-image-layer-picker' } ).append( input( 'url', 'url', { maxlength: 2048 } ), $( '<button>', { type: 'button', 'class': 'button psp-select-extra-image-layer', text: 'Choose image' } ) ) ),
			label( 'Link URL', input( 'link_url', 'url', { maxlength: 2048 } ) ),
			label( 'Alt text', input( 'alt', 'text', { maxlength: 120 } ) ),
			label( 'Color', input( 'color', 'color' ) ),
			label( 'Background', input( 'background', 'color' ) ),
			label( 'Font', select( 'font_family', fontOptions ) ),
			label( 'Size', input( 'size', 'number', { min: 12, max: 96 } ) ),
			label( 'Opacity', input( 'opacity', 'number', { min: 10, max: 100 } ) ),
			label( 'Desktop X/Y', $( '<span>', { 'class': 'psp-extra-layer-pair' } ).append( input( 'desktop_x', 'number', { min: 5, max: 95 } ), input( 'desktop_y', 'number', { min: 5, max: 95 } ) ) ),
			label( 'Tablet X/Y', $( '<span>', { 'class': 'psp-extra-layer-pair' } ).append( input( 'tablet_x', 'number', { min: 5, max: 95 } ), input( 'tablet_y', 'number', { min: 5, max: 95 } ) ) ),
			label( 'Phone X/Y', $( '<span>', { 'class': 'psp-extra-layer-pair' } ).append( input( 'mobile_x', 'number', { min: 5, max: 95 } ), input( 'mobile_y', 'number', { min: 5, max: 95 } ) ) ),
			input( 'font_style', 'hidden' ),
			input( 'width', 'hidden' ),
			input( 'tablet_size', 'hidden' ),
			input( 'mobile_size', 'hidden' ),
			input( 'tablet_width', 'hidden' ),
			input( 'mobile_width', 'hidden' ),
			input( 'height', 'hidden' ),
			input( 'radius', 'hidden' ),
			input( 'ratio_locked', 'hidden' ),
			input( 'overlay_type', 'hidden' ),
			input( 'overlay_color', 'hidden' ),
			input( 'overlay_color2', 'hidden' ),
			input( 'overlay_opacity', 'hidden' ),
			input( 'overlay_direction', 'hidden' ),
			input( 'size_linked', 'hidden' ),
			input( 'pos_linked', 'hidden' ),
			input( 'animation', 'hidden' ),
			input( 'animation_delay', 'hidden' ),
			input( 'animation_duration', 'hidden' ),
			input( 'animation_easing', 'hidden' ),
			$( '<label>', { 'class': 'psp-check-field' } ).append( $( '<input>', { 'class': 'psp-slide-content-input', type: 'checkbox', name: extraLayerName( attachmentId, index, 'target' ), value: '1' } ), $( '<span>' ).text( 'Open in new tab' ) )
		);
		$row.append( $summary, $fields );

		return $row;
	}

	function positionOptions() {
		return Array.isArray( config.layerPositionOptions ) ? config.layerPositionOptions : [
			[ '5,12', 'Top left' ], [ '50,12', 'Top center' ], [ '95,12', 'Top right' ],
			[ '5,50', 'Middle left' ], [ '50,50', 'Middle center' ], [ '95,50', 'Middle right' ],
			[ '5,82', 'Bottom left' ], [ '50,82', 'Bottom center' ], [ '95,82', 'Bottom right' ]
		];
	}

	function makeLayerPositionSelect( device, layer, label, selectedValue ) {
		var $label = $( '<label>', { 'class': 'psp-field' } );
		var $select = $( '<select>', {
			'class': 'psp-layer-position-select',
			'data-psp-layer-device': device,
			'data-psp-layer-type': layer
		} );

		$label.append( $( '<span>' ).text( label ) );
		$select.append( $( '<option>', { value: 'custom', text: config.customPositionLabel || 'Custom (dragged)' } ) );
		positionOptions().forEach( function ( option ) {
			$select.append( $( '<option>', { value: option[ 0 ], text: option[ 1 ] } ) );
		} );
		$select.val( selectedValue );
		$label.append( $select );

		return $label;
	}

	function makeLayerControls( attachmentId ) {
		var defaults = {
			text_x: 5,
			text_y: 50,
			description_x: 5,
			description_y: 62,
			button_x: 5,
			button_y: 82,
			image_x: 50,
			image_y: 50,
			tablet_text_x: 50,
			tablet_text_y: 50,
			tablet_description_x: 50,
			tablet_description_y: 62,
			tablet_button_x: 50,
			tablet_button_y: 82,
			tablet_image_x: 50,
			tablet_image_y: 50,
			mobile_text_x: 50,
			mobile_text_y: 50,
			mobile_description_x: 50,
			mobile_description_y: 62,
			mobile_button_x: 50,
			mobile_button_y: 82,
			mobile_image_x: 50,
			mobile_image_y: 50,
			text_color: '#ffffff',
			heading_size: 64,
			tablet_heading_size: 64,
			mobile_heading_size: 64,
			heading_opacity: 100,
			description_color: '#ffffff',
			description_size: 20,
			tablet_description_size: 20,
			mobile_description_size: 20,
			description_opacity: 100,
			description_align: 'left',
			description_font_family: 'montserrat',
			text_align: 'left',
			font_family: 'montserrat',
			heading_font_style: 'default',
			description_font_style: 'default',
			button_font_style: 'default',
			button_text_color: '#172033',
			button_background: '#ffffff',
			button_font_family: 'montserrat',
			button_font_size: 16,
			tablet_button_font_size: 16,
			mobile_button_font_size: 16,
			button_opacity: 100,
			button_radius: 4,
			button_padding_x: 20,
			button_padding_y: 12,
			image_width: 220,
			tablet_image_width: 220,
			mobile_image_width: 220,
			image_opacity: 100,
			heading_size_linked: '1',
			description_size_linked: '1',
			button_size_linked: '1',
			image_size_linked: '1',
			heading_pos_linked: '',
			description_pos_linked: '',
			button_pos_linked: '',
			image_pos_linked: '',
			background_fill: 'cover',
			background_position: 'center',
			tablet_background_position: 'center',
			mobile_background_position: 'center',
			overlay_type: 'none',
			overlay_color: '#08101f',
			overlay_color2: '#000000',
			overlay_opacity: 50,
			overlay_direction: 'to bottom',
			heading_animation: 'fade',
			description_animation: 'fade',
			button_animation: 'fade',
			image_animation: 'fade',
			heading_animation_delay: 0,
			description_animation_delay: 120,
			button_animation_delay: 240,
			image_animation_delay: 0,
			heading_animation_duration: 600,
			description_animation_duration: 600,
			button_animation_duration: 600,
			image_animation_duration: 600,
			heading_animation_easing: 'ease-out',
			description_animation_easing: 'ease-out',
			button_animation_easing: 'ease-out',
			image_animation_easing: 'ease-out',
			layer_order: 'button,heading,description,image'
		};
		var $fieldset = $( '<fieldset>', { 'class': 'psp-layer-controls psp-field-wide' } );
		var $grid = $( '<div>', { 'class': 'psp-layer-controls-grid' } );
		var coordinateKeys = [ 'text_x', 'text_y', 'description_x', 'description_y', 'button_x', 'button_y', 'image_x', 'image_y', 'tablet_text_x', 'tablet_text_y', 'tablet_description_x', 'tablet_description_y', 'tablet_button_x', 'tablet_button_y', 'tablet_image_x', 'tablet_image_y', 'mobile_text_x', 'mobile_text_y', 'mobile_description_x', 'mobile_description_y', 'mobile_button_x', 'mobile_button_y', 'mobile_image_x', 'mobile_image_y' ];

		$fieldset.append( $( '<legend>' ).text( config.layerPositionsLabel || 'Layer positions' ) );
		$fieldset.append( $( '<p>' ).text( config.layerPositionsHelp || 'Choose a preset or drag each layer in Slider Preview. Desktop, Tablet, and Phone positions are saved independently.' ) );
		$grid.append( makeLayerPositionSelect( 'desktop', 'heading', config.desktopHeadingPositionLabel || 'Desktop heading position', '5,50' ) );
		$grid.append( makeLayerPositionSelect( 'desktop', 'description', config.desktopDescriptionPositionLabel || 'Desktop description position', '5,62' ) );
		$grid.append( makeLayerPositionSelect( 'desktop', 'button', config.desktopButtonPositionLabel || 'Desktop button position', '5,82' ) );
		$grid.append( makeLayerPositionSelect( 'desktop', 'image', config.desktopImagePositionLabel || 'Desktop image position', '50,50' ) );
		$grid.append( makeLayerPositionSelect( 'tablet', 'heading', config.tabletHeadingPositionLabel || 'Tablet heading position', '50,50' ) );
		$grid.append( makeLayerPositionSelect( 'tablet', 'description', config.tabletDescriptionPositionLabel || 'Tablet description position', '50,62' ) );
		$grid.append( makeLayerPositionSelect( 'tablet', 'button', config.tabletButtonPositionLabel || 'Tablet button position', '50,82' ) );
		$grid.append( makeLayerPositionSelect( 'tablet', 'image', config.tabletImagePositionLabel || 'Tablet image position', '50,50' ) );
		$grid.append( makeLayerPositionSelect( 'mobile', 'heading', config.mobileHeadingPositionLabel || 'Phone heading position', '50,50' ) );
		$grid.append( makeLayerPositionSelect( 'mobile', 'description', config.mobileDescriptionPositionLabel || 'Phone description position', '50,62' ) );
		$grid.append( makeLayerPositionSelect( 'mobile', 'button', config.mobileButtonPositionLabel || 'Phone button position', '50,82' ) );
		$grid.append( makeLayerPositionSelect( 'mobile', 'image', config.mobileImagePositionLabel || 'Phone image position', '50,50' ) );
		$fieldset.append( $grid );
		Object.keys( defaults ).forEach( function ( key ) {
			$fieldset.append( $( '<input>', {
				'class': coordinateKeys.indexOf( key ) >= 0 ? 'psp-layer-coordinate' : ( 'layer_order' === key ? 'psp-layer-order-value' : 'psp-layer-style-value' ),
				type: 'hidden',
				name: fieldName( attachmentId, key ),
				value: defaults[ key ]
			} ) );
		} );

		return $fieldset;
	}

	// Mirror the PHP render_extra_layer_controls() container so slides added in
	// the browser carry the [data-psp-extra-layers] fieldset the Add Layer tools
	// target; without it, addExtraLayer() finds no container and silently bails.
	function makeExtraLayersContainer( attachmentId ) {
		var $fieldset = $( '<fieldset>', {
			'class': 'psp-extra-layers psp-field-wide',
			'data-psp-extra-layers': attachmentId,
			'data-psp-next-extra-layer': 0
		} );
		var $actions = $( '<div>', { 'class': 'psp-extra-layer-actions' } );

		$fieldset.append( $( '<legend>' ).text( config.extraLayersLegend || 'Additional overlay layers' ) );
		[
			[ 'heading', config.addHeadingText || 'Add heading' ],
			[ 'description', config.addDescriptionText || 'Add description' ],
			[ 'button', config.addButtonLayerText || 'Add button' ],
			[ 'image', config.addImageLayerText || 'Add image' ],
			[ 'shape', config.addShapeLayerText || 'Add shape' ]
		].forEach( function ( entry ) {
			$actions.append( $( '<button>', {
				type: 'button',
				'class': 'button psp-add-extra-layer',
				'data-psp-extra-layer-type': entry[0],
				text: entry[1]
			} ) );
		} );
		$fieldset.append( $actions );
		$fieldset.append( $( '<div>', { 'class': 'psp-extra-layer-list' } ) );

		return $fieldset;
	}

	function makeSlideFields( attachmentId ) {
		var $details = $( '<details>', { 'class': 'psp-slide-details', 'data-attachment-id': attachmentId, open: true } );
		var $fields = $( '<div>', { 'class': 'psp-slide-fields' } );
		var $target = $( '<label>', { 'class': 'psp-check-field psp-slide-target-field' } );

		function makeTargetField( key, label ) {
			var $wrap = $( '<label>', { 'class': 'psp-check-field psp-slide-target-field' } );

			$wrap.append( $( '<input>', {
				'class': 'psp-slide-content-input',
				type: 'checkbox',
				name: fieldName( attachmentId, key ),
				value: '1'
			} ) );
			$wrap.append( $( '<span>' ).text( label ) );

			return $wrap;
		}

		$details.append( $( '<summary>' ).text( config.slideContentLabel || 'Selected slide properties' ) );
		$fields.append( makeField( attachmentId, 'title', config.headingLabel || 'Heading', 'text', '', false ) );
		$fields.append( makeField( attachmentId, 'description', config.descriptionLabel || 'Description', 'textarea', '', true ) );
		$fields.append( makeField( attachmentId, 'heading_link_url', config.headingLinkLabel || 'Heading link', 'url', '', false ) );
		$fields.append( makeField( attachmentId, 'description_link_url', config.descriptionLinkLabel || 'Description link', 'url', '', false ) );
		$fields.append( makeField( attachmentId, 'button_label', config.buttonLabel || 'Button label', 'text', '', false ) );
		$fields.append( makeField( attachmentId, 'button_url', config.buttonLinkLabel || 'Button link', 'url', '', false ) );
		$fields.append( makeField( attachmentId, 'image_layer_url', config.imageLayerUrlLabel || 'Image layer URL', 'url', '', false ) );
		$fields.append( makeField( attachmentId, 'image_layer_alt', config.imageLayerAltLabel || 'Image layer alt text', 'text', '', false ) );
		$fields.append( makeField( attachmentId, 'image_link_url', config.imageLinkLabel || 'Image layer link', 'url', '', false ) );
		$target.append( $( '<input>', {
			'class': 'psp-slide-content-input',
			type: 'checkbox',
			name: fieldName( attachmentId, 'button_target' ),
			value: '1'
		} ) );
		$target.append( $( '<span>' ).text( config.newTabLabel || 'Open button link in a new tab' ) );
		$fields.append(
			$target,
			makeTargetField( 'heading_target', 'Open heading link in a new tab' ),
			makeTargetField( 'description_target', 'Open text link in a new tab' ),
			makeTargetField( 'image_target', 'Open image link in a new tab' ),
			makeLayerControls( attachmentId ),
			makeExtraLayersContainer( attachmentId )
		);
		$details.append( $fields );

		return $details;
	}

	function replaceSlideImage( $item, data ) {
		var oldId = String( $item.attr( 'data-attachment-id' ) || '' );
		var newId = String( parseInt( data.id, 10 ) || '' );
		var previewUrl = data.sizes && data.sizes.large ? data.sizes.large.url : ( data.url || '' );
		var thumbUrl = thumbnailUrl( data );
		var oldPrefix = 'my_slider_pro_slide_content[' + oldId + ']';
		var newPrefix = 'my_slider_pro_slide_content[' + newId + ']';
		// The active slide's content fields live in the moved-out store, so
		// re-key across the whole slide scope, not just the thumbnail item.
		var $scope = slideScope( $item );

		if ( ! newId || ! thumbUrl ) {
			return;
		}
		// Per-slide content, coordinates, and overlay layers are keyed by the
		// image's attachment id, so re-key every field name when the image
		// changes; nothing the author set is lost.
		if ( newId !== oldId ) {
			$scope.find( '[name]' ).each( function () {
				var name = this.getAttribute( 'name' ) || '';

				if ( 0 === name.indexOf( oldPrefix ) ) {
					this.setAttribute( 'name', newPrefix + name.slice( oldPrefix.length ) );
				}
			} );
			$item.find( 'input[name="my_slider_pro_image_ids[]"]' ).val( newId );
			$item.attr( 'data-attachment-id', newId );
			$scope.find( '.psp-slide-details' ).attr( 'data-attachment-id', newId );
			$scope.find( '[data-psp-extra-layers]' ).attr( 'data-psp-extra-layers', newId );
		}
		$item.attr( 'data-preview-url', previewUrl );
		$item.find( '.psp-media-thumbnail img' ).attr( 'src', thumbUrl );
		if ( data.title ) {
			$item.find( '.psp-media-title' ).text( data.title );
		}
		activePreviewIndex = $item.index();
		refreshControls();
		announce( config.imageReplacedText || 'Slide image replaced.' );
	}

	function thumbnailUrl( attachment ) {
		if ( attachment.sizes && attachment.sizes.thumbnail ) {
			return attachment.sizes.thumbnail.url;
		}

		return attachment.url || '';
	}

	function addAttachment( attachment ) {
		var id = parseInt( attachment.id, 10 );
		var title = attachment.title || config.imageFallback || 'Image';
		var $item;
		var $summary;
		var $thumbnail;
		var $identity;
		var $actions;

		if ( ! id || ! thumbnailUrl( attachment ) ) {
			return;
		}

		$item = $( '<li>', {
			'class': 'psp-media-item',
			'data-attachment-id': id,
			'data-preview-url': attachment.url || thumbnailUrl( attachment )
		} );
		$item.append( $( '<input>', { type: 'hidden', name: 'my_slider_pro_image_ids[]', value: id } ) );
		$summary = $( '<div>', { 'class': 'psp-slide-summary' } );
		$thumbnail = $( '<div>', { 'class': 'psp-media-thumbnail' } );
		$thumbnail.append( $( '<img>', { src: thumbnailUrl( attachment ), alt: '' } ) );
		$identity = $( '<div>', { 'class': 'psp-slide-identity' } );
		// Match the PHP template: the position ("Slide N") is the bold element and
		// the file title is the span. renumber() fills the position text.
		$identity.append( $( '<strong>', { 'class': 'psp-slide-position' } ) );
		$identity.append( $( '<span>', { 'class': 'psp-media-title', title: title } ).text( title ) );
		$actions = $( '<div>', { 'class': 'psp-media-actions' } );
		$actions.append( $( '<button>', { type: 'button', 'class': 'button-link psp-move-earlier', text: '\u2190' } ) );
		$actions.append( $( '<button>', { type: 'button', 'class': 'button-link psp-move-later', text: '\u2192' } ) );
		$actions.append( $( '<button>', { type: 'button', 'class': 'button-link psp-replace-image', text: config.replaceImageText || 'Replace' } ) );
		$actions.append( $( '<button>', { type: 'button', 'class': 'button-link-delete psp-remove-image', text: config.removeText || 'Remove' } ) );
		$summary.append( $thumbnail, $identity, $actions );
		$item.append( $summary, makeSlideFields( id ) );
		$list.append( $item );
	}

	function previewSlides() {
		return $list.children( '.psp-media-item' ).map( function () {
			var $item = $( this );
			var $scope = slideScope( $item );
			var source = String( $item.attr( 'data-preview-url' ) || $item.find( '.psp-media-thumbnail img' ).attr( 'src' ) || '' );

			if ( ! source ) {
				return null;
			}

			return {
				attachmentId: parseInt( $item.attr( 'data-attachment-id' ), 10 ),
				image: source,
				title: String( $scope.find( '[name$="[title]"]' ).val() || '' ),
				description: String( $scope.find( '[name$="[description]"]' ).val() || '' ),
				buttonLabel: String( $scope.find( '[name$="[button_label]"]' ).val() || '' ),
				buttonUrl: String( $scope.find( '[name$="[button_url]"]' ).val() || '' ),
				imageLayerUrl: String( $scope.find( '[name$="[image_layer_url]"]' ).val() || '' ),
				imageLayerAlt: String( $scope.find( '[name$="[image_layer_alt]"]' ).val() || '' ),
				desktopHeading: layerPosition( $item, 'text_x', 'text_y', 5, 50 ),
				desktopDescription: layerPosition( $item, 'description_x', 'description_y', 5, 62 ),
				desktopButton: layerPosition( $item, 'button_x', 'button_y', 5, 82 ),
				desktopImage: layerPosition( $item, 'image_x', 'image_y', 50, 50 ),
				tabletHeading: posLinkedVal( $item, 'heading_pos_linked' ) ? layerPosition( $item, 'text_x', 'text_y', 5, 50 ) : layerPosition( $item, 'tablet_text_x', 'tablet_text_y', 50, 50 ),
				tabletDescription: posLinkedVal( $item, 'description_pos_linked' ) ? layerPosition( $item, 'description_x', 'description_y', 5, 62 ) : layerPosition( $item, 'tablet_description_x', 'tablet_description_y', 50, 62 ),
				tabletButton: posLinkedVal( $item, 'button_pos_linked' ) ? layerPosition( $item, 'button_x', 'button_y', 5, 82 ) : layerPosition( $item, 'tablet_button_x', 'tablet_button_y', 50, 82 ),
				tabletImage: posLinkedVal( $item, 'image_pos_linked' ) ? layerPosition( $item, 'image_x', 'image_y', 50, 50 ) : layerPosition( $item, 'tablet_image_x', 'tablet_image_y', 50, 50 ),
				mobileHeading: posLinkedVal( $item, 'heading_pos_linked' ) ? layerPosition( $item, 'text_x', 'text_y', 5, 50 ) : layerPosition( $item, 'mobile_text_x', 'mobile_text_y', 50, 50 ),
				mobileDescription: posLinkedVal( $item, 'description_pos_linked' ) ? layerPosition( $item, 'description_x', 'description_y', 5, 62 ) : layerPosition( $item, 'mobile_description_x', 'mobile_description_y', 50, 62 ),
				mobileButton: posLinkedVal( $item, 'button_pos_linked' ) ? layerPosition( $item, 'button_x', 'button_y', 5, 82 ) : layerPosition( $item, 'mobile_button_x', 'mobile_button_y', 50, 82 ),
				mobileImage: posLinkedVal( $item, 'image_pos_linked' ) ? layerPosition( $item, 'image_x', 'image_y', 50, 50 ) : layerPosition( $item, 'mobile_image_x', 'mobile_image_y', 50, 50 ),
				layerOrder: layerOrder( $item ),
				animations: {
					heading: layerAnimation( $item, 'heading' ),
					description: layerAnimation( $item, 'description' ),
					button: layerAnimation( $item, 'button' ),
					image: layerAnimation( $item, 'image' )
				},
				headingStyle: {
					color: styleValue( $item, 'text_color', '#ffffff' ),
					headingSize: deviceSizeValue( $item, 'heading_size', '64' ),
					opacity: styleValue( $item, 'heading_opacity', '100' ),
					align: styleValue( $item, 'text_align', 'left' ),
					fontFamily: styleValue( $item, 'font_family', 'montserrat' ),
					fontStyle: styleValue( $item, 'heading_font_style', 'default' )
				},
				descriptionStyle: {
					color: styleValue( $item, 'description_color', '#ffffff' ),
					descriptionSize: deviceSizeValue( $item, 'description_size', '20' ),
					opacity: styleValue( $item, 'description_opacity', '100' ),
					align: styleValue( $item, 'description_align', 'left' ),
					fontFamily: styleValue( $item, 'description_font_family', 'montserrat' ),
					fontStyle: styleValue( $item, 'description_font_style', 'default' )
				},
				buttonStyle: {
					color: styleValue( $item, 'button_text_color', '#172033' ),
					background: styleValue( $item, 'button_background', '#ffffff' ),
					fontFamily: styleValue( $item, 'button_font_family', 'montserrat' ),
					fontStyle: styleValue( $item, 'button_font_style', 'default' ),
					fontSize: deviceSizeValue( $item, 'button_font_size', '16' ),
					opacity: styleValue( $item, 'button_opacity', '100' ),
					radius: styleValue( $item, 'button_radius', '4' ),
					paddingX: styleValue( $item, 'button_padding_x', '20' ),
					paddingY: styleValue( $item, 'button_padding_y', '12' )
				},
				imageStyle: {
					width: deviceSizeValue( $item, 'image_width', '220' ),
					opacity: styleValue( $item, 'image_opacity', '100' )
				},
				background: {
					fill: styleValue( $item, 'background_fill', 'cover' ),
					position: deviceBgPosition( $item ),
					overlayType: styleValue( $item, 'overlay_type', 'none' ),
					overlayColor: styleValue( $item, 'overlay_color', '#08101f' ),
					overlayColor2: styleValue( $item, 'overlay_color2', '#000000' ),
					overlayOpacity: parseInt( styleValue( $item, 'overlay_opacity', '50' ), 10 ),
					overlayDirection: styleValue( $item, 'overlay_direction', 'to bottom' )
				},
				extraLayers: extraLayers( $item )
			};
		} ).get().filter( function ( slide ) {
			return null !== slide;
		} );
	}

	function extraLayers( $item ) {
		return slideScope( $item ).find( '[data-psp-extra-layer-row]' ).map( function () {
			var $row = $( this );
			var type = String( $row.find( '[name$="[type]"]' ).val() || 'heading' );

			return {
				type: type,
				text: String( $row.find( '[name$="[text]"]' ).val() || '' ),
				url: String( $row.find( '[name$="[url]"]' ).val() || '' ),
				linkUrl: String( $row.find( '[name$="[link_url]"]' ).val() || '' ),
				alt: String( $row.find( '[name$="[alt]"]' ).val() || '' ),
				color: String( $row.find( '[name$="[color]"]' ).val() || ( 'button' === type ? '#172033' : '#ffffff' ) ),
				background: String( $row.find( '[name$="[background]"]' ).val() || '#ffffff' ),
				fontFamily: String( $row.find( '[name$="[font_family]"]' ).val() || 'montserrat' ),
				fontStyle: String( $row.find( '[name$="[font_style]"]' ).val() || 'default' ),
				size: String( $row.find( '[name$="[' + extraEffectiveSizeKey( $row, 'size' ) + ']"]' ).val() || $row.find( '[name$="[size]"]' ).val() || ( 'heading' === type ? '64' : '20' ) ),
				width: String( $row.find( '[name$="[' + extraEffectiveSizeKey( $row, 'width' ) + ']"]' ).val() || $row.find( '[name$="[width]"]' ).val() || '220' ),
				height: String( $row.find( '[name$="[height]"]' ).val() || '200' ),
				radius: String( $row.find( '[name$="[radius]"]' ).val() || '0' ),
				overlay: {
					type: String( $row.find( '[name$="[overlay_type]"]' ).val() || 'none' ),
					color: String( $row.find( '[name$="[overlay_color]"]' ).val() || '#08101f' ),
					color2: String( $row.find( '[name$="[overlay_color2]"]' ).val() || '#000000' ),
					opacity: String( $row.find( '[name$="[overlay_opacity]"]' ).val() || '50' ),
					direction: String( $row.find( '[name$="[overlay_direction]"]' ).val() || 'to bottom' )
				},
				opacity: String( $row.find( '[name$="[opacity]"]' ).val() || '100' ),
				desktop: layerPosition( $row, 'desktop_x', 'desktop_y', 50, 50 ),
				tablet: '1' === String( $row.find( '[name$="[pos_linked]"]' ).val() || '' ) ? layerPosition( $row, 'desktop_x', 'desktop_y', 50, 50 ) : layerPosition( $row, 'tablet_x', 'tablet_y', 50, 50 ),
				mobile: '1' === String( $row.find( '[name$="[pos_linked]"]' ).val() || '' ) ? layerPosition( $row, 'desktop_x', 'desktop_y', 50, 50 ) : layerPosition( $row, 'mobile_x', 'mobile_y', 50, 50 ),
				animation: {
					type: String( $row.find( '[name$="[animation]"]' ).val() || 'fade' ),
					delay: String( $row.find( '[name$="[animation_delay]"]' ).val() || '0' ),
					duration: String( $row.find( '[name$="[animation_duration]"]' ).val() || '600' ),
					easing: String( $row.find( '[name$="[animation_easing]"]' ).val() || 'ease-out' )
				}
			};
		} ).get();
	}

	function coordinateValue( $item, key, fallback ) {
		var value = parseInt( slideScope( $item ).find( '[name$="[' + key + ']"]' ).val(), 10 );

		return Number.isFinite( value ) ? Math.max( 5, Math.min( 95, value ) ) : fallback;
	}

	function styleValue( $item, key, fallback ) {
		var value = slideScope( $item ).find( '[name$="[' + key + ']"]' ).val();

		return undefined === value || '' === String( value ) ? fallback : String( value );
	}

	function layerAnimation( $item, layer ) {
		return {
			type: styleValue( $item, layer + '_animation', 'fade' ),
			delay: styleValue( $item, layer + '_animation_delay', '0' ),
			duration: styleValue( $item, layer + '_animation_duration', '600' ),
			easing: styleValue( $item, layer + '_animation_easing', 'ease-out' )
		};
	}

	function layerOrder( $item ) {
		var allowed = [ 'button', 'heading', 'description', 'image' ];
		// Base layers and repeatable overlay layers (extra-N) share one order.
		var order = String( slideScope( $item ).find( '[name$="[layer_order]"]' ).val() || '' ).split( ',' ).filter( function ( layer, index, values ) {
			var valid = allowed.indexOf( layer ) >= 0 || /^extra-\d+$/.test( layer );
			return valid && values.indexOf( layer ) === index;
		} );

		allowed.forEach( function ( layer ) {
			if ( order.indexOf( layer ) < 0 ) {
				order.push( layer );
			}
		} );

		return order;
	}

	function layerZIndex( order, layer ) {
		var index = order.indexOf( layer );

		return index < 0 ? 1 : order.length - index;
	}

	// z for a repeatable overlay layer from the unified order; an overlay not
	// yet placed in the order sits above the base layers (a freshly added one).
	function extraLayerZIndex( order, index ) {
		var pos = order.indexOf( 'extra-' + index );

		return pos < 0 ? order.length + 1 + index : order.length - pos;
	}

	function previewFontFamily( family ) {
		var families = {
			theme: 'inherit',
			poppins: '"Poppins", sans-serif',
			montserrat: '"Montserrat", sans-serif',
			inter: '"Inter", sans-serif'
		};

		return families[ family ] || families.theme;
	}

	function previewFontWeight( value, fallback ) {
		if ( 'bold' === value || 'bold-italic' === value ) {
			return 700;
		}
		if ( 'normal' === value || 'italic' === value ) {
			return 400;
		}
		return fallback;
	}

	function previewFontStyle( value ) {
		return 'italic' === value || 'bold-italic' === value ? 'italic' : 'normal';
	}

	function previewAnimationName( type ) {
		var animations = {
			none: 'none',
			fade: 'psp-layer-fade',
			'slide-up': 'psp-layer-slide-up',
			'slide-down': 'psp-layer-slide-down',
			'slide-left': 'psp-layer-slide-left',
			'slide-right': 'psp-layer-slide-right',
			zoom: 'psp-layer-zoom'
		};

		return animations[ type ] || animations.fade;
	}

	function applyAnimationStyle( $layer, animation ) {
		var delay = parseInt( animation.delay, 10 );
		var duration = parseInt( animation.duration, 10 );

		delay = Number.isFinite( delay ) ? delay : 0;
		duration = Number.isFinite( duration ) ? duration : 600;
		$layer.css( {
			'--psp-layer-animation': previewAnimationName( animation.type ),
			'--psp-layer-animation-delay': delay + 'ms',
			'--psp-layer-animation-duration': duration + 'ms',
			'--psp-layer-animation-easing': animation.easing
		} );
	}

	function layerPosition( $item, xKey, yKey, fallbackX, fallbackY ) {
		return {
			x: coordinateValue( $item, xKey, fallbackX ),
			y: coordinateValue( $item, yKey, fallbackY )
		};
	}

	function applyLayerPosition( $layer, position ) {
		$layer
			.css( '--psp-layer-x', position.x + '%' )
			.css( '--psp-layer-y', position.y + '%' )
			.removeClass( 'is-left-anchor is-center-anchor is-right-anchor is-top-anchor is-middle-anchor is-bottom-anchor' )
			.addClass( position.x <= 33 ? 'is-left-anchor' : ( position.x >= 67 ? 'is-right-anchor' : 'is-center-anchor' ) )
			.addClass( position.y <= 33 ? 'is-top-anchor' : ( position.y >= 67 ? 'is-bottom-anchor' : 'is-middle-anchor' ) );
	}

	function previewDeviceLabel() {
		return 'desktop' === activePreviewDevice ? 'Desktop' : ( 'tablet' === activePreviewDevice ? 'Tablet' : 'Phone' );
	}

	function syncOverlayControls( $item ) {
		var type = $item && $item.length ? styleValue( $item, 'overlay_type', 'none' ) : 'none';
		$layerWorkspace.find( '[data-psp-overlay-fields]' ).toggleClass( 'is-hidden', 'none' === type );
		$layerWorkspace.find( '.psp-overlay-gradient-only' ).toggleClass( 'is-hidden', 'gradient' !== type );
		$layerWorkspace.find( '#psp-overlay-color-label' ).text( 'gradient' === type ? ( config.firstColorLabel || 'First color' ) : ( config.colorLabel || 'Color' ) );
		$layerWorkspace.find( '.psp-overlay-opacity-field .psp-range-out' ).text( ( $item && $item.length ? styleValue( $item, 'overlay_opacity', '50' ) : '50' ) + '%' );
	}

	// Show/hide the shape inspector's overlay sub-fields: everything hides when
	// the overlay is 'none', and the second color and direction show only for a
	// gradient. Mirrors syncOverlayControls but for a shape's own overlay.
	function syncShapeOverlayControls( type ) {
		type = type || 'none';
		$layerWorkspace.find( '[data-psp-shape-overlay-fields]' ).toggleClass( 'is-hidden', 'none' === type );
		$layerWorkspace.find( '.psp-shape-overlay-gradient-only' ).toggleClass( 'is-hidden', 'gradient' !== type );
		$layerWorkspace.find( '#psp-shape-overlay-color-label' ).text( 'gradient' === type ? ( config.firstColorLabel || 'First color' ) : ( config.overlayColorLabel || 'Overlay color' ) );
	}

	function syncBackgroundThumb( $item ) {
		var src = $item && $item.length ? String( $item.attr( 'data-preview-url' ) || $item.find( '.psp-media-thumbnail img' ).attr( 'src' ) || '' ) : '';
		var $thumbImg = $( '#psp-bg-thumb-img' );

		// Never leave an empty-string src: browsers render that as a broken
		// image. Drop the attribute entirely when there is no background.
		if ( src ) {
			$thumbImg.attr( 'src', src );
		} else {
			$thumbImg.removeAttr( 'src' );
		}
		$( '.psp-bg-thumb' ).toggleClass( 'is-empty', ! src );
		$( '#psp-bg-thumb-name' ).text( $item && $item.length ? itemTitle( $item ) : '' );
	}

	function hexToRgba( hex, alpha ) {
		var value = String( hex || '' ).replace( '#', '' );

		if ( 6 !== value.length ) {
			return 'rgba(8, 16, 31, ' + alpha + ')';
		}

		return 'rgba(' + parseInt( value.slice( 0, 2 ), 16 ) + ', ' + parseInt( value.slice( 2, 4 ), 16 ) + ', ' + parseInt( value.slice( 4, 6 ), 16 ) + ', ' + alpha + ')';
	}

	function overlayBackground( background ) {
		if ( ! background || 'none' === background.overlayType ) {
			return '';
		}
		var alpha = Math.max( 0, Math.min( 100, background.overlayOpacity || 0 ) ) / 100;
		if ( ! alpha ) {
			return '';
		}
		if ( 'gradient' === background.overlayType ) {
			return 'linear-gradient(' + ( background.overlayDirection || 'to bottom' ) + ', ' + hexToRgba( background.overlayColor || '#08101f', alpha ) + ', ' + hexToRgba( background.overlayColor2 || '#000000', alpha ) + ')';
		}
		return hexToRgba( background.overlayColor || '#08101f', alpha );
	}

	function applyPreviewBackground( $slider, $image, background ) {
		var fillMap = { cover: 'cover', fill: 'fill', fit: 'contain', center: 'none' };
		var positionMap = {
			top_left: 'left top', top_center: 'center top', top_right: 'right top',
			center_left: 'left center', center: 'center', center_right: 'right center',
			bottom_left: 'left bottom', bottom_center: 'center bottom', bottom_right: 'right bottom'
		};

		if ( ! background ) {
			return;
		}
		if ( 'cover' !== background.fill && fillMap[ background.fill ] ) {
			$image.css( 'object-fit', fillMap[ background.fill ] );
		}
		if ( 'center' !== background.position && positionMap[ background.position ] ) {
			$image.css( 'object-position', positionMap[ background.position ] );
		}
	}

	function syncLayerEditorUI() {
		var $item = activeSlideItem();
		var $layerStripItems = activeLayerStripItems();
		var slide = previewSlides()[ activePreviewIndex ] || { extraLayers: [] };
		var keys = layerCoordinateKeys( activeEditorLayer );
		var $coordinateScope = keys.extraIndex >= 0 ? extraLayerRow( keys.extraIndex ) : $item;
		var x = coordinateValue( $coordinateScope, keys.x, 'desktop' === activePreviewDevice ? 5 : 50 );
		var yFallback = 'button' === activeEditorLayer ? 82 : ( 'description' === activeEditorLayer ? 62 : 50 );
		var y = coordinateValue( $coordinateScope, keys.y, yFallback );
		var preset = x + ',' + y;
		var layers = [
			{ key: 'heading', label: config.headingLayerLabel || 'Heading', exists: $preview.find( '[data-psp-layer="heading"]' ).length > 0 },
			{ key: 'description', label: config.descriptionLayerLabel || 'Text', exists: $preview.find( '[data-psp-layer="description"]' ).length > 0 },
			{ key: 'button', label: config.buttonLayerLabel || 'Button', exists: $preview.find( '[data-psp-layer="button"]' ).length > 0 },
			{ key: 'image', label: config.imageLayerLabel || 'Image', exists: $preview.find( '[data-psp-layer="image"]' ).length > 0 }
		];
		var extraLabels = extraLayerLabels( slide.extraLayers );
		slide.extraLayers.forEach( function ( layer, index ) {
			layers.push( {
				key: 'extra-' + index,
				label: extraLabels[ index ],
				exists: extraLayerExists( layer ),
				extraType: layer.type
			} );
		} );
		var order = layerOrder( $item );
		// Base and overlay layers share one front-to-back order. A freshly added
		// overlay (not yet in the saved order) sorts to the top/front.
		layers.sort( function ( first, second ) {
			var firstIndex = order.indexOf( first.key );
			var secondIndex = order.indexOf( second.key );

			return ( firstIndex < 0 ? -1 : firstIndex ) - ( secondIndex < 0 ? -1 : secondIndex );
		} );
		// Persist the combined order so overlay depth survives a save even
		// without a manual drag.
		slideScope( $item ).find( '[name$="[layer_order]"]' ).val( layers.map( function ( layer ) { return layer.key; } ).join( ',' ) );
		var activeLayerConfig = layers.find( function ( layer ) { return layer.key === activeEditorLayer; } );
		var activeStyleSection = activeLayerConfig && activeLayerConfig.extraType ? activeLayerConfig.extraType : activeEditorLayer;

		$layerInspectorName.text( ( activeLayerConfig ? activeLayerConfig.label : 'Heading' ) + ' layer' );
		$layerInspectorDevice.text( previewDeviceLabel() );
		$layerInspectorX.val( x );
		$layerInspectorY.val( y );
		$layerWorkspace.find( '[data-psp-style-section]' ).each( function () {
			var $section = $( this );
			var isInactive = activeStyleSection !== String( $section.attr( 'data-psp-style-section' ) || '' );

			$section.prop( 'hidden', isInactive );
			// Hidden sections must not hold the form hostage: an out-of-range
			// value synced from another layer type would fail native validation
			// invisibly and silently block every save.
			$section.find( 'input, select' ).prop( 'disabled', isInactive );
		} );
		$layerWorkspace.find( '[data-psp-style-key]' ).each( function () {
			var $control = $( this );
			var key = String( $control.attr( 'data-psp-style-key' ) || '' );
			var extraKey;

			if ( undefined === $control.attr( 'data-psp-base-min' ) && $control.attr( 'min' ) ) {
				$control.attr( 'data-psp-base-min', String( $control.attr( 'min' ) ) );
				$control.attr( 'data-psp-base-max', String( $control.attr( 'max' ) || '' ) );
			}
			if ( keys.extraIndex >= 0 ) {
				extraKey = extraLayerStyleKey( key );
				if ( extraKey ) {
					// Repeatable layers accept wider size/width ranges than the
					// base layer a control was rendered for; align the native
					// constraints so a legal saved value cannot fail validation.
					if ( 'size' === extraKey ) {
						$control.attr( { min: '12', max: '96' } );
					} else if ( 'width' === extraKey ) {
						$control.attr( { min: '40', max: '800' } );
					}
					if ( 'size' === extraKey || 'width' === extraKey ) {
						extraKey = extraEffectiveSizeKey( extraLayerRow( keys.extraIndex ), extraKey );
					}
					$control.val( styleValue( extraLayerRow( keys.extraIndex ), extraKey, String( $control.val() || '' ) ) );
				}
				return;
			}
			if ( $control.attr( 'data-psp-base-min' ) ) {
				$control.attr( { min: String( $control.attr( 'data-psp-base-min' ) ), max: String( $control.attr( 'data-psp-base-max' ) || '' ) } );
			}
			$control.val( styleValue( $item, SIZE_DEVICE_KEYS[ key ] ? effectiveSizeKey( key ) : key, String( $control.val() || '' ) ) );
		} );
		$layerWorkspace.find( '[data-psp-animation-key]' ).each( function () {
			var $control = $( this );
			var suffix = String( $control.attr( 'data-psp-animation-key' ) || '' );
			var key = activeEditorLayer + '_' + suffix;

			if ( keys.extraIndex >= 0 ) {
				$control.val( styleValue( extraLayerRow( keys.extraIndex ), suffix, String( $control.val() || '' ) ) );
				return;
			}
			$control.val( styleValue( $item, key, String( $control.val() || '' ) ) );
		} );
		$layerWorkspace.find( '[data-psp-content-toggle]' ).each( function () {
			var $control = $( this );
			var key = String( $control.attr( 'data-psp-content-toggle' ) || '' );
			var extraKey = keys.extraIndex >= 0 ? ( /_target$/.test( key ) ? 'target' : '' ) : key;
			var $field;

			if ( ! extraKey ) {
				return;
			}
			$field = keys.extraIndex >= 0 ? extraLayerRow( keys.extraIndex ).find( '[name$="[' + extraKey + ']"]' ) : $item.add( $activeSlideFields ).find( '[name$="[' + extraKey + ']"]' );
			$control.prop( 'checked', $field.length ? $field.is( ':checked' ) : false );
		} );
		$layerWorkspace.find( '[data-psp-slide-key]' ).each( function () {
			var $control = $( this );
			var key = String( $control.attr( 'data-psp-slide-key' ) || '' );

			if ( $control.is( ':checkbox' ) ) {
				$control.prop( 'checked', '1' === styleValue( $item, key, '' ) );
				return;
			}
			// Device-varying slide keys reflect the active device's field.
			$control.val( styleValue( $item, SLIDE_DEVICE_KEYS[ key ] ? effectiveSlideKey( key ) : key, String( $control.val() || '' ) ) );
		} );
		$layerWorkspace.find( '[data-psp-color-value]' ).each( function () {
			var key = String( $( this ).attr( 'data-psp-color-value' ) || '' );
			var stored = styleValue( $item, key, '' );

			if ( stored ) {
				$( this ).val( stored );
			}
			$layerWorkspace.find( '[data-psp-color-toggle="' + key + '"]' ).prop( 'checked', '' !== stored );
		} );
		$layerWorkspace.find( '[data-psp-layer-anchor]' ).each( function () {
			$( this ).toggleClass( 'is-active', preset === String( $( this ).attr( 'data-psp-layer-anchor' ) || '' ) );
		} );
		$layerWorkspace.find( '[data-psp-link-key]' ).each( function () {
			var linkKey = String( $( this ).attr( 'data-psp-link-key' ) || '' );

			$( this ).prop( 'checked', 'size' === linkKey ? layerSizeLinked( activeEditorLayer ) : layerPositionLinked( activeEditorLayer ) );
		} );
		$layerWorkspace.find( '.psp-seg' ).each( function () {
			var $seg = $( this );
			var value = String( $seg.find( 'input[data-psp-style-key], input[data-psp-slide-key]' ).first().val() || '' );

			$seg.find( 'button' ).each( function () {
				$( this ).toggleClass( 'is-active', String( $( this ).attr( 'data-psp-seg-value' ) || '' ) === value );
			} );
		} );
		syncOverlayControls( $item );
		syncShapeOverlayControls( 'shape' === activeStyleSection && keys.extraIndex >= 0 ? styleValue( extraLayerRow( keys.extraIndex ), 'overlay_type', 'none' ) : 'none' );
		$layerWorkspace.find( '[data-psp-shape-lock]' ).prop( 'checked', 'shape' === activeStyleSection && keys.extraIndex >= 0 && '1' === String( extraLayerRow( keys.extraIndex ).find( '[name$="[ratio_locked]"]' ).val() || '' ) );
		syncBackgroundThumb( $item );
		syncAddLayerButtons();
		$layerStripItems.empty();
		layers.forEach( function ( layer ) {
			var layerKeys;
			var layerX;
			var layerY;

			if ( ! layer.exists ) {
				return;
			}
			layerKeys = layerCoordinateKeys( layer.key );
			$coordinateScope = layerKeys.extraIndex >= 0 ? extraLayerRow( layerKeys.extraIndex ) : $item;
			layerX = coordinateValue( $coordinateScope, layerKeys.x, 'desktop' === activePreviewDevice ? 5 : 50 );
			layerY = coordinateValue( $coordinateScope, layerKeys.y, 'button' === layer.key ? 82 : ( 'description' === layer.key ? 62 : 50 ) );
			$layerStripItems.append( $( '<div>', {
				'class': 'psp-layer-strip-item' + ( layer.key === activeEditorLayer ? ' is-active' : '' ),
				'data-psp-layer-order-key': layer.key
			} ).append(
				$( '<button>', {
					type: 'button',
					'class': 'psp-layer-strip-select psp-layer-picker' + ( layer.key === activeEditorLayer ? ' is-active' : '' ),
					'data-psp-pick-layer': layer.key,
					'aria-pressed': layer.key === activeEditorLayer ? 'true' : 'false'
				} ).append(
					$( '<span>', { 'class': 'psp-layer-strip-handle', text: '\u22ee\u22ee', 'aria-hidden': 'true' } ),
					$( '<span>', { 'class': 'psp-layer-strip-icon', text: 'heading' === layer.key || 'heading' === layer.extraType ? 'H' : ( 'description' === layer.key || 'description' === layer.extraType ? 'T' : ( 'image' === layer.key || 'image' === layer.extraType ? '\u25a7' : ( 'shape' === layer.extraType ? '\u25fc' : '\u25ad' ) ) ), 'aria-hidden': 'true' } ),
					$( '<strong>' ).text( layer.label + ' layer' ),
					$( '<span>', { 'class': 'psp-layer-strip-coordinates', text: 'X ' + layerX + '%  Y ' + layerY + '%' } )
				),
				$( '<button>', {
					type: 'button',
					'class': 'psp-layer-strip-delete',
					'data-psp-delete-layer': layer.key,
					'aria-label': formatText( config.deleteLayerLabel || 'Delete %s layer', layer.label ),
					title: config.deleteLayerTitle || 'Delete layer'
				} ).append( $( '<span>', { 'class': 'dashicons dashicons-trash', 'aria-hidden': 'true' } ) )
			) );
		} );
		$layerStripItems.append( $( '<div>', {
			'class': 'psp-layer-strip-item is-locked psp-layer-strip-background'
		} ).append(
			$( '<button>', {
				type: 'button',
				'class': 'psp-layer-strip-select',
				'data-psp-focus-background': '1',
				title: config.backgroundLayerHint || 'Slide background (locked, always at the back)'
			} ).append(
				$( '<span>', { 'class': 'psp-layer-strip-lock', 'aria-hidden': 'true' } ).append( $( '<span>', { 'class': 'dashicons dashicons-lock' } ) ),
				$( '<span>', { 'class': 'psp-layer-strip-icon', 'aria-hidden': 'true' } ).append( $( '<span>', { 'class': 'dashicons dashicons-format-image' } ) ),
				$( '<strong>' ).text( ( config.backgroundLayerLabel || 'Background' ) + ' layer' ),
				$( '<span>', { 'class': 'psp-layer-strip-coordinates', text: config.lockedText || 'Locked' } )
			)
		) );
		ensureLayerSortable( $layerStripItems );
	}

	function settingValue( selector, fallback ) {
		var value = String( $( selector ).val() || '' );

		return value || fallback;
	}

	function layerOpacity( value ) {
		return Math.max( 0.1, Math.min( 1, ( parseInt( value, 10 ) || 100 ) / 100 ) );
	}

	function applyExtraLayerPosition( $layer, layer ) {
		var position = 'desktop' === activePreviewDevice ? layer.desktop : ( 'tablet' === activePreviewDevice ? layer.tablet : layer.mobile );
		applyLayerPosition( $layer, position );
	}

	function renderExtraPreviewLayer( $slider, layer, index, order ) {
		var $layer;
		var $content;
		var layerZ = extraLayerZIndex( order || [], index );
		var size = parseInt( layer.size, 10 ) || ( 'heading' === layer.type ? 64 : 20 );

		if ( 'image' === layer.type ) {
			if ( ! layer.url ) {
				return;
			}
			$layer = $( '<div>', {
				'class': 'psp-preview-image-layer psp-draggable-layer',
				'data-psp-layer': 'extra-' + index,
				'data-psp-layer-label': 'Image'
			} );
			$layer.append( $( '<img>', { src: layer.url, alt: layer.alt, draggable: 'false' } ) );
			$layer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			$layer.css( {
				'--psp-layer-z': layerZ,
				'--psp-image-layer-width': ( parseInt( layer.width, 10 ) || 220 ) + 'px',
				'--psp-image-layer-opacity': layerOpacity( layer.opacity )
			} );
		} else if ( 'button' === layer.type ) {
			if ( ! layer.text ) {
				return;
			}
			$layer = $( '<div>', {
				'class': 'psp-preview-button-layer psp-draggable-layer',
				'data-psp-layer': 'extra-' + index,
				'data-psp-layer-label': 'Button'
			} );
			$layer.append( $( '<span>', editableAttributes( 'extra-' + index, 'Edit button text' ) ).addClass( 'psp-slider-preview-button' ).text( layer.text ).css( {
				'--psp-button-color': layer.color,
				'--psp-button-background': layer.background,
				'--psp-button-font-family': previewFontFamily( layer.fontFamily ),
				'--psp-button-font-weight': previewFontWeight( layer.fontStyle, 700 ),
				'--psp-button-font-style': previewFontStyle( layer.fontStyle ),
				'--psp-button-font-size': size + 'px',
				'--psp-button-opacity': layerOpacity( layer.opacity ),
				'--psp-button-radius': '4px',
				'--psp-button-padding-x': '20px',
				'--psp-button-padding-y': '12px'
			} ) );
			$layer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			$layer.css( '--psp-layer-z', layerZ );
		} else if ( 'shape' === layer.type ) {
			var $shapeBox = $( '<span>', { 'class': 'psp-preview-shape', 'aria-hidden': 'true' } );
			var shapeShade = overlayBackground( {
				overlayType: layer.overlay.type,
				overlayColor: layer.overlay.color,
				overlayColor2: layer.overlay.color2,
				overlayOpacity: parseInt( layer.overlay.opacity, 10 ),
				overlayDirection: layer.overlay.direction
			} );

			$layer = $( '<div>', {
				'class': 'psp-preview-shape-layer psp-draggable-layer',
				'data-psp-layer': 'extra-' + index,
				'data-psp-layer-label': config.shapeLayerLabel || 'Shape'
			} );
			if ( shapeShade ) {
				$shapeBox.append( $( '<span>', { 'class': 'psp-preview-shape-shade', 'aria-hidden': 'true' } ).css( 'background', shapeShade ) );
			}
			$layer.append( $shapeBox );
			$layer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			$layer.css( {
				'--psp-layer-z': layerZ,
				'--psp-shape-width': ( parseInt( layer.width, 10 ) || 320 ) + 'px',
				'--psp-shape-height': ( parseInt( layer.height, 10 ) || 200 ) + 'px',
				'--psp-shape-radius': ( parseInt( layer.radius, 10 ) || 0 ) + 'px',
				'--psp-shape-fill': layer.background,
				'--psp-shape-opacity': layerOpacity( layer.opacity )
			} );
		} else {
			if ( ! layer.text ) {
				return;
			}
			$layer = $( '<div>', {
				'class': 'psp-slider-preview-content psp-slider-preview-' + layer.type + ' psp-draggable-layer',
				'data-psp-layer': 'extra-' + index,
				'data-psp-layer-label': 'heading' === layer.type ? 'Heading' : ( config.descriptionLayerLabel || 'Text' )
			} );
			$content = 'heading' === layer.type ?
				$( '<h3>', editableAttributes( 'extra-' + index, 'Edit heading text' ) ).text( layer.text ) :
				$( '<p>', editableAttributes( 'extra-' + index, 'Edit description text' ) ).text( layer.text );
			$layer.append( $content );
			$layer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			$layer.css( {
				'--psp-layer-z': layerZ,
				'--psp-text-color': layer.color,
				'--psp-heading-size': size + 'px',
				'--psp-description-size': size + 'px',
				'--psp-heading-opacity': layerOpacity( layer.opacity ),
				'--psp-description-opacity': layerOpacity( layer.opacity ),
				'--psp-text-align': 'left',
				'--psp-font-family': previewFontFamily( layer.fontFamily ),
				'--psp-font-weight': previewFontWeight( layer.fontStyle, 'heading' === layer.type ? 700 : 400 ),
				'--psp-font-style': previewFontStyle( layer.fontStyle )
			} );
		}

		applyExtraLayerPosition( $layer, layer );
		applyAnimationStyle( $layer, layer.animation );
		$slider.append( $layer );
	}

	function refreshPreview() {
		var slides = previewSlides();
		var settings = {
			height: settingValue( '#my-slider-pro-height', 'standard' ),
			tabletHeight: settingValue( '#my-slider-pro-tablet-height', 'standard' ),
			mobileHeight: settingValue( '#my-slider-pro-mobile-height', 'standard' ),
			position: settingValue( '#my-slider-pro-content-position', 'left' ),
			tabletPosition: settingValue( '#my-slider-pro-tablet-content-position', 'left' ),
			mobilePosition: settingValue( '#my-slider-pro-mobile-content-position', 'left' ),
			tabletTextWidth: settingValue( '#my-slider-pro-tablet-text-width', 'comfortable' ),
			mobileTextWidth: settingValue( '#my-slider-pro-mobile-text-width', 'comfortable' ),
			tabletButtonSize: settingValue( '#my-slider-pro-tablet-button-size', 'large' ),
			mobileButtonSize: settingValue( '#my-slider-pro-mobile-button-size', 'large' ),
			arrows: $( 'input[name="slider_arrows"]' ).prop( 'checked' ),
			hideArrowsOnPhone: $( 'input[name="slider_hide_arrows_on_phone"]' ).prop( 'checked' ),
			dots: $( 'input[name="slider_dots"]' ).prop( 'checked' )
		};
		var slide;
		var $slider;
		var $previewImage;
		var $headingLayer;
		var $descriptionLayer;
		var $buttonLayer;
		var $imageLayer;
		var $dots;
		var $editorOverlay;
		var $editorToolbar;
		var headingPosition;
		var descriptionPosition;
		var buttonPosition;
		var imagePosition;
		var availableLayers;

		if ( ! $preview.length ) {
			return;
		}

		$preview.empty();
		$emptyPreview.toggleClass( 'is-hidden', slides.length > 0 );

		if ( ! slides.length ) {
			return;
		}

		activePreviewIndex = Math.max( 0, Math.min( activePreviewIndex, slides.length - 1 ) );
		slide = slides[ activePreviewIndex ];
		availableLayers = slide.layerOrder.filter( function ( layer ) {
			return ( 'heading' === layer && slide.title ) ||
				( 'description' === layer && slide.description ) ||
				( 'button' === layer && slide.buttonLabel && slide.buttonUrl ) ||
				( 'image' === layer && slide.imageLayerUrl );
		} );
		slide.extraLayers.forEach( function ( layer, index ) {
			if ( extraLayerExists( layer ) ) {
				availableLayers.push( 'extra-' + index );
			}
		} );
		if ( availableLayers.indexOf( activeEditorLayer ) < 0 ) {
			activeEditorLayer = availableLayers[0] || 'heading';
		}
		headingPosition = 'desktop' === activePreviewDevice ? slide.desktopHeading : ( 'tablet' === activePreviewDevice ? slide.tabletHeading : slide.mobileHeading );
		descriptionPosition = 'desktop' === activePreviewDevice ? slide.desktopDescription : ( 'tablet' === activePreviewDevice ? slide.tabletDescription : slide.mobileDescription );
		buttonPosition = 'desktop' === activePreviewDevice ? slide.desktopButton : ( 'tablet' === activePreviewDevice ? slide.tabletButton : slide.mobileButton );
		imagePosition = 'desktop' === activePreviewDevice ? slide.desktopImage : ( 'tablet' === activePreviewDevice ? slide.tabletImage : slide.mobileImage );
		$slider = $( '<div>', {
			'class': 'psp-slider-preview is-' + settings.height + '-height has-' + settings.tabletHeight + '-tablet-height has-' + settings.mobileHeight + '-mobile-height is-' + settings.position + '-content has-' + settings.tabletPosition + '-tablet-content has-' + settings.mobilePosition + '-mobile-content has-' + settings.tabletTextWidth + '-tablet-text has-' + settings.mobileTextWidth + '-mobile-text has-' + settings.tabletButtonSize + '-tablet-button has-' + settings.mobileButtonSize + '-mobile-button' + ( settings.hideArrowsOnPhone ? ' hides-phone-arrows' : '' )
		} );
		$previewImage = $( '<img>', { 'class': 'psp-slider-preview-image', src: slide.image, alt: '' } );
		applyPreviewBackground( $slider, $previewImage, slide.background );
		$slider.append( $previewImage );
		var overlayBg = overlayBackground( slide.background );
		if ( overlayBg ) {
			$slider.append( $( '<div>', { 'class': 'psp-slider-preview-shade', 'aria-hidden': 'true' } ).css( 'background', overlayBg ) );
		}
		$editorOverlay = $( '<div>', {
			'class': 'psp-layer-editor-overlay' + ( showEditorOverlay ? '' : ' is-hidden' ),
			'aria-hidden': 'true'
		} );
		$editorOverlay.append(
			$( '<span>', { 'class': 'psp-layer-safe-area' } ),
			$( '<span>', { 'class': 'psp-layer-snap-guide is-vertical' } ),
			$( '<span>', { 'class': 'psp-layer-snap-guide is-horizontal' } )
		);
		$slider.append( $editorOverlay );

		if ( slide.imageLayerUrl ) {
			$imageLayer = $( '<div>', {
				'class': 'psp-preview-image-layer psp-draggable-layer' + ( 'image' === activeEditorLayer ? ' is-selected' : '' ),
				'data-psp-layer': 'image',
				'data-psp-layer-label': config.imageLayerLabel || 'Image',
				tabindex: '0',
				'aria-label': config.imageLayerDragLabel || 'Image layer. Drag to move, or use arrow keys to nudge.',
				'aria-describedby': 'psp-layer-preview-help'
			} );
			$imageLayer.append( $( '<img>', { src: slide.imageLayerUrl, alt: slide.imageLayerAlt, draggable: 'false' } ) );
			$imageLayer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			applyLayerPosition( $imageLayer, imagePosition );
			$imageLayer.css( {
				'--psp-layer-z': layerZIndex( slide.layerOrder, 'image' ),
				'--psp-image-layer-width': ( parseInt( slide.imageStyle.width, 10 ) || 220 ) + 'px',
				'--psp-image-layer-opacity': Math.max( 0.1, Math.min( 1, ( parseInt( slide.imageStyle.opacity, 10 ) || 100 ) / 100 ) )
			} );
			applyAnimationStyle( $imageLayer, slide.animations.image );
			$slider.append( $imageLayer );
		}

		if ( slide.title ) {
			$headingLayer = $( '<div>', {
				'class': 'psp-slider-preview-content psp-slider-preview-heading psp-draggable-layer' + ( 'heading' === activeEditorLayer ? ' is-selected' : '' ),
				'data-psp-layer': 'heading',
				'data-psp-layer-label': config.headingLayerLabel || 'Heading',
				tabindex: '0',
				'aria-label': config.headingLayerDragLabel || 'Heading layer. Drag to move, or use arrow keys to nudge.',
				'aria-describedby': 'psp-layer-preview-help'
			} );
			$headingLayer.append( $( '<h3>', editableAttributes( 'heading', 'Edit heading text' ) ).text( slide.title ) );
			$headingLayer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			applyLayerPosition( $headingLayer, headingPosition );
			$headingLayer.css( {
				'--psp-layer-z': layerZIndex( slide.layerOrder, 'heading' ),
				'--psp-text-color': slide.headingStyle.color,
				'--psp-heading-size': slide.headingStyle.headingSize + 'px',
				'--psp-heading-opacity': Math.max( 0.1, Math.min( 1, ( parseInt( slide.headingStyle.opacity, 10 ) || 100 ) / 100 ) ),
				'--psp-text-align': slide.headingStyle.align,
				'--psp-font-family': previewFontFamily( slide.headingStyle.fontFamily ),
				'--psp-font-weight': previewFontWeight( slide.headingStyle.fontStyle, 700 ),
				'--psp-font-style': previewFontStyle( slide.headingStyle.fontStyle )
			} );
			applyAnimationStyle( $headingLayer, slide.animations.heading );
			$slider.append( $headingLayer );
		}

		if ( slide.description ) {
			$descriptionLayer = $( '<div>', {
				'class': 'psp-slider-preview-content psp-slider-preview-description psp-draggable-layer' + ( 'description' === activeEditorLayer ? ' is-selected' : '' ),
				'data-psp-layer': 'description',
				'data-psp-layer-label': config.descriptionLayerLabel || 'Text',
				tabindex: '0',
				'aria-label': config.descriptionLayerDragLabel || 'Description layer. Drag to move, or use arrow keys to nudge.',
				'aria-describedby': 'psp-layer-preview-help'
			} );
			$descriptionLayer.append( $( '<p>', editableAttributes( 'description', 'Edit description text' ) ).text( slide.description ) );
			$descriptionLayer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			applyLayerPosition( $descriptionLayer, descriptionPosition );
			$descriptionLayer.css( {
				'--psp-layer-z': layerZIndex( slide.layerOrder, 'description' ),
				'--psp-text-color': slide.descriptionStyle.color,
				'--psp-description-size': slide.descriptionStyle.descriptionSize + 'px',
				'--psp-description-opacity': Math.max( 0.1, Math.min( 1, ( parseInt( slide.descriptionStyle.opacity, 10 ) || 100 ) / 100 ) ),
				'--psp-text-align': slide.descriptionStyle.align,
				'--psp-font-family': previewFontFamily( slide.descriptionStyle.fontFamily ),
				'--psp-font-weight': previewFontWeight( slide.descriptionStyle.fontStyle, 400 ),
				'--psp-font-style': previewFontStyle( slide.descriptionStyle.fontStyle )
			} );
			applyAnimationStyle( $descriptionLayer, slide.animations.description );
			$slider.append( $descriptionLayer );
		}

		if ( slide.buttonLabel && slide.buttonUrl ) {
			$buttonLayer = $( '<div>', {
				'class': 'psp-preview-button-layer psp-draggable-layer' + ( 'button' === activeEditorLayer ? ' is-selected' : '' ),
				'data-psp-layer': 'button',
				'data-psp-layer-label': config.buttonLayerLabel || 'Button',
				tabindex: '0',
				'aria-label': config.buttonLayerDragLabel || 'Button layer. Drag to move, or use arrow keys to nudge.',
				'aria-describedby': 'psp-layer-preview-help'
			} );
			$buttonLayer.append( $( '<span>', editableAttributes( 'button', 'Edit button text' ) ).addClass( 'psp-slider-preview-button' ).text( slide.buttonLabel ).css( {
				'--psp-button-color': slide.buttonStyle.color,
				'--psp-button-background': slide.buttonStyle.background,
				'--psp-button-font-family': previewFontFamily( slide.buttonStyle.fontFamily ),
				'--psp-button-font-weight': previewFontWeight( slide.buttonStyle.fontStyle, 700 ),
				'--psp-button-font-style': previewFontStyle( slide.buttonStyle.fontStyle ),
				'--psp-button-font-size': slide.buttonStyle.fontSize + 'px',
				'--psp-button-opacity': Math.max( 0.1, Math.min( 1, ( parseInt( slide.buttonStyle.opacity, 10 ) || 100 ) / 100 ) ),
				'--psp-button-radius': slide.buttonStyle.radius + 'px',
				'--psp-button-padding-x': slide.buttonStyle.paddingX + 'px',
				'--psp-button-padding-y': slide.buttonStyle.paddingY + 'px'
			} ) );
			$buttonLayer.append( $( '<span>', { 'class': 'psp-layer-resize-handle', 'aria-hidden': 'true' } ) );
			applyLayerPosition( $buttonLayer, buttonPosition );
			$buttonLayer.css( '--psp-layer-z', layerZIndex( slide.layerOrder, 'button' ) );
			applyAnimationStyle( $buttonLayer, slide.animations.button );
			$slider.append( $buttonLayer );
		}

		slide.extraLayers.forEach( function ( layer, index ) {
			renderExtraPreviewLayer( $slider, layer, index, slide.layerOrder );
		} );

		$editorToolbar = $( '<div>', { 'class': 'psp-layer-editor-toolbar' } );
		$editorToolbar.append( $( '<strong>' ).text( config.layerEditorLabel || 'Layer editor' ) );
		if ( $headingLayer ) {
			$editorToolbar.append( $( '<button>', {
				type: 'button',
				'class': 'psp-layer-picker' + ( 'heading' === activeEditorLayer ? ' is-active' : '' ),
				'data-psp-pick-layer': 'heading',
				text: config.headingLayerLabel || 'Heading'
			} ) );
		}
		if ( $descriptionLayer ) {
			$editorToolbar.append( $( '<button>', {
				type: 'button',
				'class': 'psp-layer-picker' + ( 'description' === activeEditorLayer ? ' is-active' : '' ),
				'data-psp-pick-layer': 'description',
				text: config.descriptionLayerLabel || 'Text'
			} ) );
		}
		if ( $buttonLayer ) {
			$editorToolbar.append( $( '<button>', {
				type: 'button',
				'class': 'psp-layer-picker' + ( 'button' === activeEditorLayer ? ' is-active' : '' ),
				'data-psp-pick-layer': 'button',
				text: config.buttonLayerLabel || 'Button'
			} ) );
		}
		if ( $imageLayer ) {
			$editorToolbar.append( $( '<button>', {
				type: 'button',
				'class': 'psp-layer-picker' + ( 'image' === activeEditorLayer ? ' is-active' : '' ),
				'data-psp-pick-layer': 'image',
				text: config.imageLayerLabel || 'Image'
			} ) );
		}
		var toolbarLabels = extraLayerLabels( slide.extraLayers );
		slide.extraLayers.forEach( function ( layer, index ) {
			var key = 'extra-' + index;

			if ( ! extraLayerExists( layer ) ) {
				return;
			}
			$editorToolbar.append( $( '<button>', {
				type: 'button',
				'class': 'psp-layer-picker' + ( key === activeEditorLayer ? ' is-active' : '' ),
				'data-psp-pick-layer': key,
				text: toolbarLabels[ index ]
			} ) );
		} );
		$editorToolbar.append( $( '<button>', {
			type: 'button',
			'class': 'psp-layer-overlay-toggle' + ( showEditorOverlay ? ' is-active' : '' ),
			'aria-pressed': showEditorOverlay ? 'true' : 'false',
			text: showEditorOverlay ? ( config.hideOverlayLabel || 'Hide guides' ) : ( config.showOverlayLabel || 'Show guides' )
		} ) );
		$slider.append( $editorToolbar );

		if ( slides.length > 1 && settings.arrows ) {
			$slider.append( $( '<button>', {
				type: 'button',
				'class': 'psp-preview-arrow psp-preview-previous',
				'aria-label': config.previewPreviousLabel || 'Show previous slide',
				text: '\u2039'
			} ) );
			$slider.append( $( '<button>', {
				type: 'button',
				'class': 'psp-preview-arrow psp-preview-next',
				'aria-label': config.previewNextLabel || 'Show next slide',
				text: '\u203a'
			} ) );
		}

		if ( slides.length > 1 && settings.dots ) {
			$dots = $( '<div>', { 'class': 'psp-preview-dots' } );
			slides.forEach( function ( currentSlide, currentIndex ) {
				void currentSlide;
				$dots.append( $( '<button>', {
					type: 'button',
					'class': 'psp-preview-dot' + ( currentIndex === activePreviewIndex ? ' is-active' : '' ),
					'data-psp-preview-index': currentIndex,
					'aria-label': 'Show slide ' + ( currentIndex + 1 )
				} ) );
			} );
			$slider.append( $dots );
		}

		$preview.append( $slider );
		syncLayerEditorUI();
	}

	function ensureLayerSortable( $items ) {
		if ( ! $.fn.sortable || ! $items.length || $items.data( 'psp-layer-sortable' ) ) {
			return;
		}

		$items.sortable( {
			items: '.psp-layer-strip-item:not(.is-locked)',
			// The whole row is a drag handle; grabbing anywhere moves the layer.
			// The delete button is excluded so its click never starts a drag, and
			// the distance threshold lets a plain click still select the layer.
			cancel: '.psp-layer-strip-delete',
			axis: 'y',
			distance: 5,
			placeholder: 'psp-layer-strip-placeholder',
			forcePlaceholderSize: true,
			update: function () {
				var $slide = $( this ).closest( '.psp-media-item' );
				var order = $( this ).children( '.psp-layer-strip-item' ).map( function () {
					return String( $( this ).attr( 'data-psp-layer-order-key' ) || '' );
				} ).get().filter( Boolean );

				if ( ! $slide.length ) {
					$slide = activeSlideItem();
				}
				slideScope( $slide ).find( '[name$="[layer_order]"]' ).val( order.join( ',' ) );
				activePreviewIndex = $slide.index();
				refreshControls();
				announce( config.layerOrderChangedText || 'Layer order updated. The top layer is shown in front.' );
			}
		} );
		$items.data( 'psp-layer-sortable', true );
	}

	function syncActiveSlideDetails() {
		var $current = $activeSlideFields.children( '.psp-slide-details' );
		var $active = activeSlideItem();
		var currentId;
		var $owner;
		var $details;

		if ( $current.length ) {
			currentId = String( $current.attr( 'data-attachment-id' ) || '' );
			$owner = $list.children( '.psp-media-item[data-attachment-id="' + currentId + '"]' );
			if ( $owner.length && ! $owner.is( $active ) ) {
				$owner.append( $current );
			} else if ( ! $owner.length ) {
				$current.remove();
			}
		}

		$activeSlideFields.toggleClass( 'is-hidden', ! $active.length );
		if ( ! $active.length ) {
			return;
		}

		$details = $active.children( '.psp-slide-details' ).first();
		if ( $details.length ) {
			$details.prop( 'open', true );
			$activeSlideFields.append( $details );
		} else {
			$activeSlideFields.children( '.psp-slide-details' ).prop( 'open', true );
		}
	}

	// The per-slide content fields (heading/description/links/image/focus and
	// the hidden coordinate, style, and overlay-layer inputs) are no longer
	// shown in a Slide tab; they persist only as a hidden data store the canvas
	// and Layer inspector write into. Because they are never visible, native
	// constraint validation (URL format, number min/max, maxlength) could
	// silently block a save with no field to focus, so strip those constraints
	// from the store — the server still sanitizes every value on save.
	function neutralizeSlideStore() {
		$list.add( $activeSlideFields ).find( '.psp-slide-details' ).find( 'input, textarea' ).each( function () {
			var $field = $( this );

			if ( 'url' === String( $field.attr( 'type' ) || '' ).toLowerCase() ) {
				$field.attr( 'type', 'text' );
			}
			$field.removeAttr( 'maxlength' ).removeAttr( 'min' ).removeAttr( 'max' ).removeAttr( 'step' );
		} );
	}

	function refreshControls() {
		var $items = $list.children( '.psp-media-item' );
		var total = $items.length;
		var text = 1 === total ? config.countSingular : String( config.countPlural || '%d images selected' ).replace( '%d', total );

		activePreviewIndex = Math.max( 0, Math.min( activePreviewIndex, Math.max( total - 1, 0 ) ) );
		// Grey out Add New Slide once the slider is at its slide cap.
		$addButton.prop( 'disabled', total >= maxImages ).attr( 'aria-disabled', total >= maxImages ? 'true' : 'false' );
		$empty.toggleClass( 'is-hidden', total > 0 );
		$slideLayersEmpty.toggleClass( 'is-hidden', total > 0 );
		$slideLayers.find( '.psp-layer-strip' ).toggleClass( 'is-hidden', 0 === total );
		$count.text( text );
		$slideSelect.empty().prop( 'disabled', 0 === total );
		$items.each( function ( index ) {
			var $item = $( this );
			var title = itemTitle( $item );
			var position = index + 1;
			var isActive = index === activePreviewIndex;

			$slideSelect.append( $( '<option>', {
				value: index,
				text: slideOptionLabel( $item, index ),
				selected: isActive
			} ) );
			$item.toggleClass( 'is-active-slide', isActive );
			$item.find( '.psp-slide-details' ).prop( 'open', isActive );
			$item.find( '.psp-slide-position' ).text( 'Slide ' + position );
			$item.find( '.psp-move-earlier' )
				.prop( 'disabled', 0 === index )
				.attr( 'aria-label', formatText( config.moveEarlierLabel || 'Move %1$s earlier; position %2$d of %3$d', title, position, total ) );
			$item.find( '.psp-move-later' )
				.prop( 'disabled', index === total - 1 )
				.attr( 'aria-label', formatText( config.moveLaterLabel || 'Move %1$s later; position %2$d of %3$d', title, position, total ) );
			$item.find( '.psp-remove-image' )
				.attr( 'aria-label', formatText( config.removeLabel || 'Remove %1$s; position %2$d of %3$d', title, position, total ) );
		} );
		$list.toggleClass( 'has-active-slide', total > 0 );
		syncActiveSlideDetails();
		neutralizeSlideStore();

		refreshPreview();
	}

	function activeSlideItem() {
		return $list.children( '.psp-media-item' ).eq( activePreviewIndex );
	}

	// Per-device size field names and their link flag, keyed by the base
	// (desktop) size key used on the main heading/description/button/image layers.
	var SIZE_DEVICE_KEYS = {
		heading_size:     { tablet: 'tablet_heading_size',     mobile: 'mobile_heading_size',     flag: 'heading_size_linked' },
		description_size: { tablet: 'tablet_description_size', mobile: 'mobile_description_size', flag: 'description_size_linked' },
		button_font_size: { tablet: 'tablet_button_font_size', mobile: 'mobile_button_font_size', flag: 'button_size_linked' },
		image_width:      { tablet: 'tablet_image_width',      mobile: 'mobile_image_width',      flag: 'image_size_linked' }
	};

	function styleFlagOn( $item, key ) {
		return '1' === styleValue( $item, key, '' );
	}

	// Field a main-layer size control reads/writes for the current device: the
	// base (desktop) field when linked or on desktop, else the per-device field.
	function effectiveSizeKey( baseKey ) {
		var map = SIZE_DEVICE_KEYS[ baseKey ];

		if ( ! map || 'desktop' === activePreviewDevice || styleFlagOn( activeSlideItem(), map.flag ) ) {
			return baseKey;
		}

		return 'tablet' === activePreviewDevice ? map.tablet : map.mobile;
	}

	// Slide-level keys that vary per device (independent, no link flag).
	var SLIDE_DEVICE_KEYS = {
		background_position: { tablet: 'tablet_background_position', mobile: 'mobile_background_position' }
	};

	// Field a device-varying slide control reads/writes for the current device.
	function effectiveSlideKey( baseKey ) {
		var map = SLIDE_DEVICE_KEYS[ baseKey ];

		if ( ! map || 'desktop' === activePreviewDevice ) {
			return baseKey;
		}

		return 'tablet' === activePreviewDevice ? map.tablet : map.mobile;
	}

	// Background crop anchor for the active preview device, cascading to desktop.
	function deviceBgPosition( $item ) {
		if ( 'tablet' === activePreviewDevice ) {
			return styleValue( $item, 'tablet_background_position', styleValue( $item, 'background_position', 'center' ) );
		}
		if ( 'mobile' === activePreviewDevice ) {
			return styleValue( $item, 'mobile_background_position', styleValue( $item, 'background_position', 'center' ) );
		}
		return styleValue( $item, 'background_position', 'center' );
	}

	// Effective size field for a repeatable layer ('size' or 'width' base).
	function extraEffectiveSizeKey( $row, baseKey ) {
		if ( 'desktop' === activePreviewDevice || '1' === String( $row.find( '[name$="[size_linked]"]' ).val() || '' ) ) {
			return baseKey;
		}

		return ( 'tablet' === activePreviewDevice ? 'tablet_' : 'mobile_' ) + baseKey;
	}

	function posLinkedVal( $item, flagKey ) {
		return '1' === styleValue( $item, flagKey, '' );
	}

	// Read a main-layer size for the active preview device from a given slide row,
	// honoring that row's own link flag (used while building all preview slides).
	function deviceSizeValue( $item, baseKey, fallback ) {
		var map = SIZE_DEVICE_KEYS[ baseKey ];
		var key;

		if ( ! map || 'desktop' === activePreviewDevice || '1' === styleValue( $item, map.flag, '' ) ) {
			key = baseKey;
		} else {
			key = 'tablet' === activePreviewDevice ? map.tablet : map.mobile;
		}

		return styleValue( $item, key, fallback );
	}

	// Whether a layer's position is linked (one placement across all devices).
	function layerPositionLinked( layer ) {
		var extraIndex = extraLayerIndex( layer );

		if ( extraIndex >= 0 ) {
			return '1' === String( extraLayerRow( extraIndex ).find( '[name$="[pos_linked]"]' ).val() || '' );
		}

		return styleFlagOn( activeSlideItem(), layer + '_pos_linked' );
	}

	// Whether a layer's size is linked across devices.
	function layerSizeLinked( layer ) {
		var extraIndex = extraLayerIndex( layer );

		if ( extraIndex >= 0 ) {
			return '1' === String( extraLayerRow( extraIndex ).find( '[name$="[size_linked]"]' ).val() || '' );
		}

		return styleFlagOn( activeSlideItem(), layer + '_size_linked' );
	}

	function layerCoordinateKeys( layer ) {
		var coordinateLayer = 'heading' === layer ? 'text' : layer;
		var extraIndex = extraLayerIndex( layer );
		// A linked layer is placed with its desktop coordinates on every device.
		var device = ( 'desktop' === activePreviewDevice || layerPositionLinked( layer ) ) ? 'desktop' : activePreviewDevice;
		var prefix = 'desktop' === device ? '' : device + '_';

		if ( extraIndex >= 0 ) {
			return {
				x: 'desktop' === device ? 'desktop_x' : prefix + 'x',
				y: 'desktop' === device ? 'desktop_y' : prefix + 'y',
				device: device,
				extraIndex: extraIndex
			};
		}

		return {
			x: prefix + coordinateLayer + '_x',
			y: prefix + coordinateLayer + '_y',
			device: device,
			extraIndex: -1
		};
	}

	function layerSizeConfig( layer ) {
		var configs = {
			heading: { key: 'heading_size', min: 24, max: 96, step: 4, css: '--psp-heading-size', unit: 'px' },
			description: { key: 'description_size', min: 12, max: 36, step: 4, css: '--psp-description-size', unit: 'px' },
			button: { key: 'button_font_size', min: 12, max: 36, step: 4, css: '--psp-button-font-size', unit: 'px' },
			image: { key: 'image_width', min: 40, max: 800, step: 1, css: '--psp-image-layer-width', unit: 'px' }
		};
		var extraIndex = extraLayerIndex( layer );
		var extraType;

		if ( extraIndex >= 0 ) {
			extraType = extraLayerRow( extraIndex ).find( '[name$="[type]"]' ).val() || 'heading';
			if ( 'image' === extraType ) {
				return { key: 'width', min: 40, max: 800, step: 1, css: '--psp-image-layer-width', unit: 'px', extraIndex: extraIndex, extraType: extraType };
			}
			if ( 'shape' === extraType ) {
				// The resize handle drags a shape's width; height is set in the inspector.
				return { key: 'width', min: 40, max: 800, step: 1, css: '--psp-shape-width', unit: 'px', extraIndex: extraIndex, extraType: extraType };
			}
			return { key: 'size', min: 'heading' === extraType ? 24 : 12, max: 'heading' === extraType ? 96 : 36, step: 4, css: 'button' === extraType ? '--psp-button-font-size' : ( 'heading' === extraType ? '--psp-heading-size' : '--psp-description-size' ), unit: 'px', extraIndex: extraIndex, extraType: extraType };
		}
		return configs[ layer ] || null;
	}

	function extraLayerIndex( layer ) {
		var matches = /^extra-(\d+)$/.exec( String( layer || '' ) );

		return matches ? parseInt( matches[1], 10 ) : -1;
	}

	function extraLayerRow( index ) {
		return activeSlideScope().find( '[data-psp-extra-layer-row]' ).eq( index );
	}

	function layerSizeValue( $item, layer ) {
		var config = layerSizeConfig( layer );
		var fallback = 'heading' === layer ? 64 : ( 'description' === layer ? 20 : ( 'button' === layer ? 16 : 220 ) );
		var value;

		if ( ! config ) {
			return fallback;
		}
		value = config.extraIndex >= 0 ?
			parseInt( extraLayerRow( config.extraIndex ).find( '[name$="[' + extraEffectiveSizeKey( extraLayerRow( config.extraIndex ), config.key ) + ']"]' ).val(), 10 ) :
			parseInt( styleValue( $item, effectiveSizeKey( config.key ), String( fallback ) ), 10 );

		return Math.max( config.min, Math.min( config.max, Number.isFinite( value ) ? value : fallback ) );
	}

	function applyLayerSize( $layer, layer, value ) {
		var config = layerSizeConfig( layer );

		if ( ! config ) {
			return;
		}
		if ( 'button' === layer ) {
			$layer.find( '.psp-slider-preview-button' ).css( config.css, value + config.unit );
			return;
		}
		if ( config.extraIndex >= 0 && 'button' === config.extraType ) {
			$layer.find( '.psp-slider-preview-button' ).css( config.css, value + config.unit );
			return;
		}
		$layer.css( config.css, value + config.unit );
	}

	function setLayerSize( $layer, layer, value, shouldAnnounce ) {
		var $item = activeSlideItem();
		var config = layerSizeConfig( layer );

		if ( ! $item.length || ! config ) {
			return;
		}
		value = Math.max( config.min, Math.min( config.max, Math.round( value ) ) );
		if ( config.extraIndex >= 0 ) {
			extraLayerRow( config.extraIndex ).find( '[name$="[' + extraEffectiveSizeKey( extraLayerRow( config.extraIndex ), config.key ) + ']"]' ).val( value );
			$layerWorkspace.find( '[data-psp-style-key="image_width"], [data-psp-style-key="shape_width"]' ).val( value );
			$layerWorkspace.find( '[data-psp-style-key="heading_size"], [data-psp-style-key="description_size"], [data-psp-style-key="button_font_size"]' ).val( value );
		} else {
			activeSlideScope().find( '[name$="[' + effectiveSizeKey( config.key ) + ']"]' ).val( value );
			$layerWorkspace.find( '[data-psp-style-key="' + config.key + '"]' ).val( value );
		}
		applyLayerSize( $layer, layer, value );
		if ( config.extraIndex < 0 ) {
			syncLayerEditorUI();
		}

		if ( shouldAnnounce ) {
			announce( ( String( $layer.attr( 'data-psp-layer-label' ) || 'Layer' ) ) + ' resized to ' + value + config.unit + '.' );
		}
	}

	var SHAPE_MIN_W = 40;
	var SHAPE_MAX_W = 800;
	var SHAPE_MIN_H = 20;
	var SHAPE_MAX_H = 800;

	// A shape resizes in two dimensions. Width and height are set independently,
	// and the preview, the saved fields, and the inspector numbers stay in sync.
	function setShapeSize( $layer, extraIndex, width, height, shouldAnnounce ) {
		var $row = extraLayerRow( extraIndex );

		if ( ! $row.length ) {
			return;
		}
		width = Math.max( SHAPE_MIN_W, Math.min( SHAPE_MAX_W, Math.round( width ) ) );
		height = Math.max( SHAPE_MIN_H, Math.min( SHAPE_MAX_H, Math.round( height ) ) );
		$row.find( '[name$="[' + extraEffectiveSizeKey( $row, 'width' ) + ']"]' ).val( width );
		$row.find( '[name$="[height]"]' ).val( height );
		$layer.css( { '--psp-shape-width': width + 'px', '--psp-shape-height': height + 'px' } );
		$layerWorkspace.find( '[data-psp-style-key="shape_width"]' ).val( width );
		$layerWorkspace.find( '[data-psp-style-key="shape_height"]' ).val( height );

		if ( shouldAnnounce ) {
			announce( ( String( $layer.attr( 'data-psp-layer-label' ) || 'Shape' ) ) + ' resized to ' + width + ' by ' + height + ' pixels.' );
		}
	}

	// Resize a shape from a pointer drag: free by default (width follows the
	// horizontal drag, height the vertical), or locked to the starting aspect
	// ratio by projecting the drag onto the shape's diagonal.
	function resizeShapeFromDrag( state, dx, dy ) {
		var width;
		var height;

		if ( state.ratioLocked && state.startW > 0 && state.startH > 0 ) {
			var span = ( state.startW * state.startW ) + ( state.startH * state.startH );
			var scale = 1 + ( ( ( dx * state.startW ) + ( dy * state.startH ) ) / span );
			// Clamp the scale so both dimensions stay in range without skewing the ratio.
			var minScale = Math.max( SHAPE_MIN_W / state.startW, SHAPE_MIN_H / state.startH );
			var maxScale = Math.min( SHAPE_MAX_W / state.startW, SHAPE_MAX_H / state.startH );

			scale = Math.max( minScale, Math.min( maxScale, scale ) );
			width = state.startW * scale;
			height = state.startH * scale;
		} else {
			width = state.startW + dx;
			height = state.startH + dy;
		}

		setShapeSize( state.$layer, state.extraIndex, width, height, false );
	}

	function snapLayerCoordinate( value, anchors ) {
		var bounded = Math.max( 5, Math.min( 95, Math.round( value ) ) );
		var snapped = anchors.find( function ( anchor ) {
			return Math.abs( bounded - anchor ) <= 3;
		} );

		return undefined === snapped ? bounded : snapped;
	}

	function layerMovedMessage( layer, x, y ) {
		var label = 'heading' === layer ? 'Heading layer' : ( 'description' === layer ? 'Description layer' : ( 'image' === layer ? 'Image layer' : 'Button layer' ) );

		return String( config.layerMovedText || '%1$s moved to %2$d%% horizontal and %3$d%% vertical.' )
			.replace( '%1$s', label )
			.replace( '%2$d', x )
			.replace( '%3$d', y )
			.replace( '%%', '%' )
			.replace( '%%', '%' );
	}

	function setLayerPosition( $layer, layer, x, y, shouldAnnounce, shouldSnap ) {
		var $item = activeSlideItem();
		var keys = layerCoordinateKeys( layer );
		var $slider = $layer.closest( '.psp-slider-preview' );
		var $fields = keys.extraIndex >= 0 ? extraLayerRow( keys.extraIndex ) : activeSlideScope();
		var preset;
		var presetExists;

		if ( ! $item.length ) {
			return;
		}

		x = shouldSnap ? snapLayerCoordinate( x, [ 5, 50, 95 ] ) : Math.max( 5, Math.min( 95, Math.round( x ) ) );
		y = shouldSnap ? snapLayerCoordinate( y, [ 12, 50, 82 ] ) : Math.max( 5, Math.min( 95, Math.round( y ) ) );
		$slider
			.toggleClass( 'has-snap-x', shouldSnap && [ 5, 50, 95 ].indexOf( x ) >= 0 )
			.toggleClass( 'has-snap-y', shouldSnap && [ 12, 50, 82 ].indexOf( y ) >= 0 )
			.css( '--psp-snap-x', x + '%' )
			.css( '--psp-snap-y', y + '%' );
		preset = x + ',' + y;
		presetExists = positionOptions().some( function ( option ) {
			return preset === String( option[ 0 ] );
		} );
		$fields.find( '[name$="[' + keys.x + ']"]' ).val( x );
		$fields.find( '[name$="[' + keys.y + ']"]' ).val( y );
		if ( keys.extraIndex < 0 ) {
			activeSlideScope().find( '.psp-layer-position-select[data-psp-layer-device="' + keys.device + '"][data-psp-layer-type="' + layer + '"]' )
				.val( presetExists ? preset : 'custom' );
		}
		applyLayerPosition( $layer, { x: x, y: y } );
		syncLayerEditorUI();

		if ( shouldAnnounce ) {
			announce( layerMovedMessage( layer, x, y ) );
		}
	}

	function pointerLayerPosition( $layer, event ) {
		var slider = $layer.closest( '.psp-slider-preview' )[0];
		var bounds = slider.getBoundingClientRect();

		return {
			x: ( event.clientX - bounds.left ) / bounds.width * 100,
			y: ( event.clientY - bounds.top ) / bounds.height * 100
		};
	}

	$addButton.on( 'click', function () {
		if ( ! frame ) {
		frame = window.wp.media( {
				title: config.frameTitle || 'Choose slider images',
				button: { text: config.frameButton || 'Use selected images' },
				library: { type: 'image' },
				multiple: 'add'
			} );

			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );

				selection.reset();
				selectedIds().forEach( function ( id ) {
					selection.add( wp.media.attachment( id ) );
				} );
			} );

			frame.on( 'select', function () {
				var existing = {};
				var selection = frame.state().get( 'selection' );

				$list.children( '.psp-media-item' ).each( function () {
					var $item = $( this );
					existing[ parseInt( $item.attr( 'data-attachment-id' ), 10 ) ] = $item.detach();
				} );

				selection.each( function ( model, selectionIndex ) {
					var attachment = model.toJSON();
					var id = parseInt( attachment.id, 10 );

					if ( selectionIndex >= maxImages ) {
						return;
					}
					if ( existing[ id ] ) {
						$list.append( existing[ id ] );
						delete existing[ id ];
					} else {
						addAttachment( attachment );
					}
				} );

				refreshControls();
				announce( $count.text() );
			} );
		}

		frame.open();
	} );

	$list.add( $activeSlideFields ).on( 'click', '.psp-select-image-layer', function () {
		$activeImageLayerField = $( this ).siblings( 'input[name$="[image_layer_url]"]' ).first();

		if ( ! $activeImageLayerField.length ) {
			return;
		}

		if ( ! extraImageLayerFrame ) {
			extraImageLayerFrame = window.wp.media( {
				title: config.imageLayerFrameTitle || 'Choose image layer',
				button: { text: config.imageLayerFrameButton || 'Use image layer' },
				library: { type: 'image' },
				multiple: false
			} );

			extraImageLayerFrame.on( 'select', function () {
				var attachment = extraImageLayerFrame.state().get( 'selection' ).first();
				var data;
				var $item;
				var alt;

				if ( ! attachment || ! $activeImageLayerField || ! $activeImageLayerField.length ) {
					return;
				}

				data = attachment.toJSON();
				$item = $activeImageLayerField.closest( '.psp-media-item' );
				if ( ! $item.length ) {
					$item = activeSlideItem();
				}
				alt = data.alt || data.title || '';
				$activeImageLayerField.val( data.url || '' ).trigger( 'input' );
				slideScope( $item ).find( 'input[name$="[image_layer_alt]"]' ).val( alt ).trigger( 'input' );
				$activeImageLayerField.siblings( '.psp-select-image-layer' ).text( config.imageLayerChangeLabel || 'Change image layer' );
				activeEditorLayer = 'image';
				refreshPreview();
				syncLayerEditorUI();
			} );
		}

		extraImageLayerFrame.open();
	} );

	$list.add( $activeSlideFields ).add( $layerWorkspace ).on( 'click', '.psp-add-extra-layer', function () {
		var $fieldset = $( this ).closest( '[data-psp-extra-layers]' );
		var type = String( $( this ).attr( 'data-psp-extra-layer-type' ) || 'heading' );

		if ( ! $fieldset.length ) {
			$fieldset = activeSlideScope().find( '[data-psp-extra-layers]' ).first();
		}
		if ( ! $fieldset.length || ! parseInt( $fieldset.attr( 'data-psp-extra-layers' ), 10 ) ) {
			return;
		}

		// Enforce the per-type cap (base content + overlay layers count together).
		if ( ! canAddLayerType( type ) ) {
			announce( config.layerLimitText || 'You can add up to 2 layers of each type per slide.' );
			return;
		}

		// An image layer with no source never renders, so it could not be
		// selected to set one. Choose the image first, then create the layer.
		if ( 'image' === type ) {
			openAddImageLayer( $fieldset );
			return;
		}
		addExtraLayer( $fieldset, type, {} );
	} );

	$list.add( $activeSlideFields ).on( 'click', '.psp-remove-extra-layer', function () {
		$( this ).closest( '[data-psp-extra-layer-row]' ).remove();
		activeEditorLayer = 'heading';
		refreshPreview();
	} );

	$layerWorkspace.on( 'click', '.psp-delete-layer', function () {
		deleteLayer( activeEditorLayer );
	} );
	$( document ).on( 'click', '.psp-layer-strip-delete', function ( event ) {
		event.stopPropagation();
		deleteLayer( String( $( this ).attr( 'data-psp-delete-layer' ) || '' ) );
	} );

	$list.add( $activeSlideFields ).on( 'click', '.psp-select-extra-image-layer', function () {
		$activeImageLayerField = $( this ).siblings( 'input[name$="[url]"]' ).first();

		if ( ! $activeImageLayerField.length ) {
			return;
		}

		if ( ! imageLayerFrame ) {
			imageLayerFrame = window.wp.media( {
				title: config.imageLayerFrameTitle || 'Choose image layer',
				button: { text: config.imageLayerFrameButton || 'Use image layer' },
				library: { type: 'image' },
				multiple: false
			} );

			imageLayerFrame.on( 'select', function () {
				var attachment = imageLayerFrame.state().get( 'selection' ).first();
				var data;
				var $row;

				if ( ! attachment || ! $activeImageLayerField || ! $activeImageLayerField.length ) {
					return;
				}

				data = attachment.toJSON();
				$row = $activeImageLayerField.closest( '[data-psp-extra-layer-row]' );
				$activeImageLayerField.val( data.url || '' ).trigger( 'input' );
				$row.find( 'input[name$="[alt]"]' ).val( data.alt || data.title || '' ).trigger( 'input' );
				$row.find( 'select[name$="[type]"]' ).val( 'image' ).trigger( 'change' );
				refreshPreview();
			} );
		}

		imageLayerFrame.open();
	} );

	$deviceButtons.on( 'click', function () {
		var device = String( $( this ).attr( 'data-psp-device' ) || 'desktop' );

		if ( [ 'desktop', 'tablet', 'phone' ].indexOf( device ) < 0 ) {
			return;
		}

		activePreviewDevice = device;

		$deviceButtons.each( function () {
			var $button = $( this );
			var selected = device === $button.attr( 'data-psp-device' );

			$button.toggleClass( 'is-active', selected ).attr( 'aria-pressed', selected ? 'true' : 'false' );
		} );
		$previewViewport.attr( 'class', 'psp-preview-viewport is-' + device );
		refreshPreview();
	} );

	$settings.on( 'change', refreshPreview );
	$inspectorTabs.on( 'click', function () {
		activateInspectorTab( String( $( this ).attr( 'data-psp-inspector-tab' ) || 'layer' ) );
	} );
	$editorForm.on( 'click', 'button[type="submit"]', function () {
		var form = $editorForm.get( 0 );
		var invalid;
		var $panel;

		if ( ! form || form.checkValidity() ) {
			return;
		}
		invalid = form.querySelector( ':invalid:not(form):not(fieldset)' );
		if ( ! invalid ) {
			return;
		}
		// Native validation refuses to submit but cannot point at a control
		// hidden inside an inactive inspector tab or a collapsed row; reveal
		// it so the browser's bubble has somewhere visible to attach.
		$panel = $( invalid ).closest( '[data-psp-inspector-panel]' );
		if ( $panel.length && $panel.prop( 'hidden' ) ) {
			activateInspectorTab( String( $panel.attr( 'data-psp-inspector-panel' ) || 'layer' ) );
		}
		$( invalid ).parents( 'details' ).prop( 'open', true );
	} );
	$slideSelect.on( 'change', function () {
		activePreviewIndex = parseInt( $( this ).val(), 10 ) || 0;
		activeEditorLayer = 'heading';
		activateInspectorTab( 'slide' );
		refreshControls();
	} );
	$list.add( $activeSlideFields ).on( 'input change', '.psp-slide-content-input', refreshControls );
	$list.add( $activeSlideFields ).on( 'change', '[data-psp-extra-layer-row] [name$="[type]"]', function () {
		var $row = $( this ).closest( '[data-psp-extra-layer-row]' );
		var index = activeSlideScope().find( '[data-psp-extra-layer-row]' ).index( $row );
		var type = String( $( this ).val() || 'heading' );

		$row.find( '.psp-extra-layer-type-label' ).text( extraLayerLabel( type, Math.max( 0, index ) ) );
	} );
	$list.add( $activeSlideFields ).on( 'change', '.psp-layer-position-select', function () {
		var $select = $( this );
		var value = String( $select.val() || '' );
		var parts = value.split( ',' );
		var $item = $select.closest( '.psp-media-item' );
		var layer = String( $select.attr( 'data-psp-layer-type' ) || '' );
		var device = String( $select.attr( 'data-psp-layer-device' ) || '' );
		var prefix = 'mobile' === device ? 'mobile_' : ( 'tablet' === device ? 'tablet_' : '' );
		var coordinateLayer = 'heading' === layer ? 'text' : layer;

		if ( 'custom' === value || [ 'heading', 'description', 'button', 'image' ].indexOf( layer ) < 0 || 2 !== parts.length ) {
			return;
		}

		if ( ! $item.length ) {
			$item = activeSlideItem();
		}
		slideScope( $item ).find( '[name$="[' + prefix + coordinateLayer + '_x]"]' ).val( parseInt( parts[0], 10 ) );
		slideScope( $item ).find( '[name$="[' + prefix + coordinateLayer + '_y]"]' ).val( parseInt( parts[1], 10 ) );
		activePreviewIndex = $item.index();
		refreshControls();
	} );
	$preview.on( 'focusin click', '.psp-inline-editable', function ( event ) {
		var layer = String( $( this ).attr( 'data-psp-edit-layer' ) || '' );
		var $layer = $( this ).closest( '.psp-draggable-layer' );

		event.stopPropagation();
		if ( ! layer || ! $layer.length ) {
			return;
		}
		activeEditorLayer = layer;
		$preview.find( '.psp-draggable-layer' ).removeClass( 'is-selected' );
		$layer.addClass( 'is-selected' );
		$preview.find( '.psp-layer-picker' ).removeClass( 'is-active' ).filter( '[data-psp-pick-layer="' + layer + '"]' ).addClass( 'is-active' );
		activeLayerStripItems().find( '.psp-layer-picker' ).removeClass( 'is-active' ).filter( '[data-psp-pick-layer="' + layer + '"]' ).addClass( 'is-active' );
		activateInspectorTab( 'layer' );
		syncLayerEditorUI();
	} );
	$preview.on( 'input', '.psp-inline-editable', function () {
		var layer = String( $( this ).attr( 'data-psp-edit-layer' ) || '' );
		var field = editableFieldForLayer( layer );
		var value = String( $( this ).text() || '' ).replace( /\u00a0/g, ' ' );
		var $field;

		if ( ! field ) {
			return;
		}
		$field = field.extraIndex >= 0 ? extraLayerRow( field.extraIndex ).find( field.selector ) : activeSlideScope().find( field.selector );
		$field.val( value );
	} );
	$preview.on( 'blur', '.psp-inline-editable', function () {
		refreshControls();
	} );
	$preview.on( 'keydown', '.psp-inline-editable', function ( event ) {
		if ( 'Enter' === event.key && ! event.shiftKey ) {
			event.preventDefault();
			$( this ).trigger( 'blur' );
		}
	} );
	$preview.on( 'paste', '.psp-inline-editable', function ( event ) {
		var clipboard = event.originalEvent.clipboardData;
		var text = clipboard ? clipboard.getData( 'text/plain' ) : '';

		if ( ! text ) {
			return;
		}
		event.preventDefault();
		document.execCommand( 'insertText', false, text );
	} );
	$preview.on( 'pointerdown', '.psp-draggable-layer', function ( event ) {
		var pointerEvent = event.originalEvent;
		var $layer = $( this );
		var layer = String( $layer.attr( 'data-psp-layer' ) || '' );
		var position;

		if ( $( event.target ).closest( '.psp-layer-resize-handle, .psp-inline-editable' ).length ) {
			return;
		}
		if ( ( [ 'heading', 'description', 'button', 'image' ].indexOf( layer ) < 0 && extraLayerIndex( layer ) < 0 ) || ( 'mouse' === pointerEvent.pointerType && 0 !== pointerEvent.button ) ) {
			return;
		}

		event.preventDefault();
		activeEditorLayer = layer;
		activateInspectorTab( 'layer' );
		$preview.find( '.psp-draggable-layer' ).removeClass( 'is-selected' );
		$layer.addClass( 'is-selected' );
		$preview.find( '.psp-layer-picker' ).removeClass( 'is-active' ).filter( '[data-psp-pick-layer="' + layer + '"]' ).addClass( 'is-active' );
		activeLayerStripItems().find( '.psp-layer-picker' ).removeClass( 'is-active' ).filter( '[data-psp-pick-layer="' + layer + '"]' ).addClass( 'is-active' );
		this.setPointerCapture( pointerEvent.pointerId );
		position = pointerLayerPosition( $layer, pointerEvent );
		dragState = { element: this, layer: layer, pointerId: pointerEvent.pointerId, x: position.x, y: position.y };
		$layer.addClass( 'is-dragging' ).trigger( 'focus' );
		setLayerPosition( $layer, layer, position.x, position.y, false, true );
	} );
	$preview.on( 'pointerdown', '.psp-layer-resize-handle', function ( event ) {
		var pointerEvent = event.originalEvent;
		var $layer = $( this ).closest( '.psp-draggable-layer' );
		var layer = String( $layer.attr( 'data-psp-layer' ) || '' );
		var config = layerSizeConfig( layer );

		if ( ! config || ( 'mouse' === pointerEvent.pointerType && 0 !== pointerEvent.button ) ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();
		if ( extraLayerIndex( layer ) < 0 ) {
			activeEditorLayer = layer;
		}
		$preview.find( '.psp-draggable-layer' ).removeClass( 'is-selected' );
		$layer.addClass( 'is-selected is-resizing' ).trigger( 'focus' );
		this.setPointerCapture( pointerEvent.pointerId );
		var resizeExtraIndex = extraLayerIndex( layer );
		var resizeIsShape = resizeExtraIndex >= 0 && 'shape' === String( extraLayerRow( resizeExtraIndex ).find( '[name$="[type]"]' ).val() || '' );
		resizeState = {
			handle: this,
			$layer: $layer,
			layer: layer,
			startX: pointerEvent.clientX,
			startY: pointerEvent.clientY,
			startSize: layerSizeValue( activeSlideItem(), layer ),
			isShape: resizeIsShape,
			extraIndex: resizeExtraIndex,
			startW: resizeIsShape ? ( parseInt( extraLayerRow( resizeExtraIndex ).find( '[name$="[' + extraEffectiveSizeKey( extraLayerRow( resizeExtraIndex ), 'width' ) + ']"]' ).val(), 10 ) || 320 ) : 0,
			startH: resizeIsShape ? ( parseInt( extraLayerRow( resizeExtraIndex ).find( '[name$="[height]"]' ).val(), 10 ) || 200 ) : 0,
			ratioLocked: resizeIsShape && '1' === String( extraLayerRow( resizeExtraIndex ).find( '[name$="[ratio_locked]"]' ).val() || '' ),
			pointerId: pointerEvent.pointerId
		};
		if ( extraLayerIndex( layer ) < 0 ) {
			syncLayerEditorUI();
		}
	} );
	$preview.on( 'pointermove', '.psp-layer-resize-handle', function ( event ) {
		var pointerEvent = event.originalEvent;
		var $layer = $( this ).closest( '.psp-draggable-layer' );
		var config;
		var delta;

		if ( ! resizeState || resizeState.handle !== this || resizeState.pointerId !== pointerEvent.pointerId ) {
			return;
		}

		event.preventDefault();
		if ( resizeState.isShape ) {
			resizeShapeFromDrag( resizeState, pointerEvent.clientX - resizeState.startX, pointerEvent.clientY - resizeState.startY );
			return;
		}
		config = layerSizeConfig( resizeState.layer );
		delta = ( pointerEvent.clientX - resizeState.startX ) + ( pointerEvent.clientY - resizeState.startY );
		setLayerSize( $layer, resizeState.layer, resizeState.startSize + ( delta / config.step ), false );
	} );
	$preview.on( 'pointerup pointercancel', '.psp-layer-resize-handle', function ( event ) {
		var pointerEvent = event.originalEvent;
		var $layer = $( this ).closest( '.psp-draggable-layer' );

		if ( ! resizeState || resizeState.handle !== this || resizeState.pointerId !== pointerEvent.pointerId ) {
			return;
		}

		if ( this.hasPointerCapture( pointerEvent.pointerId ) ) {
			this.releasePointerCapture( pointerEvent.pointerId );
		}
		$layer.removeClass( 'is-resizing' );
		if ( resizeState.isShape ) {
			setShapeSize(
				$layer,
				resizeState.extraIndex,
				parseInt( extraLayerRow( resizeState.extraIndex ).find( '[name$="[' + extraEffectiveSizeKey( extraLayerRow( resizeState.extraIndex ), 'width' ) + ']"]' ).val(), 10 ) || resizeState.startW,
				parseInt( extraLayerRow( resizeState.extraIndex ).find( '[name$="[height]"]' ).val(), 10 ) || resizeState.startH,
				true
			);
		} else {
			setLayerSize( $layer, resizeState.layer, layerSizeValue( activeSlideItem(), resizeState.layer ), true );
		}
		resizeState = null;
	} );
	$preview.on( 'pointermove', '.psp-draggable-layer', function ( event ) {
		var pointerEvent = event.originalEvent;
		var $layer = $( this );
		var position;

		if ( ! dragState || dragState.element !== this || dragState.pointerId !== pointerEvent.pointerId ) {
			return;
		}

		event.preventDefault();
		position = pointerLayerPosition( $layer, pointerEvent );
		dragState.x = position.x;
		dragState.y = position.y;
		setLayerPosition( $layer, dragState.layer, position.x, position.y, false, true );
	} );
	$preview.on( 'pointerup pointercancel', '.psp-draggable-layer', function ( event ) {
		var pointerEvent = event.originalEvent;
		var $layer = $( this );

		if ( ! dragState || dragState.element !== this || dragState.pointerId !== pointerEvent.pointerId ) {
			return;
		}

		if ( this.hasPointerCapture( pointerEvent.pointerId ) ) {
			this.releasePointerCapture( pointerEvent.pointerId );
		}
		$layer.removeClass( 'is-dragging' );
		setLayerPosition( $layer, dragState.layer, dragState.x, dragState.y, true, true );
		$layer.closest( '.psp-slider-preview' ).removeClass( 'has-snap-x has-snap-y' );
		dragState = null;
	} );
	$preview.on( 'focusin', '.psp-draggable-layer', function () {
		var $layer = $( this );

		activeEditorLayer = String( $layer.attr( 'data-psp-layer' ) || 'heading' );
		$preview.find( '.psp-draggable-layer' ).removeClass( 'is-selected' );
		$layer.addClass( 'is-selected' );
		$preview.find( '.psp-layer-picker' ).removeClass( 'is-active' ).filter( '[data-psp-pick-layer="' + activeEditorLayer + '"]' ).addClass( 'is-active' );
		activeLayerStripItems().find( '.psp-layer-picker' ).removeClass( 'is-active' ).filter( '[data-psp-pick-layer="' + activeEditorLayer + '"]' ).addClass( 'is-active' );
		syncLayerEditorUI();
	} );
	$( document ).on( 'click', '.psp-layer-picker', function () {
		var layer = String( $( this ).attr( 'data-psp-pick-layer' ) || '' );
		var $layer = $preview.find( '.psp-draggable-layer[data-psp-layer="' + layer + '"]' );

		if ( $( this ).closest( '.psp-media-item' ).length && ! $( this ).closest( '.psp-media-item' ).is( activeSlideItem() ) ) {
			activePreviewIndex = $( this ).closest( '.psp-media-item' ).index();
			refreshControls();
		}
		if ( 1 !== $layer.length ) {
			return;
		}

		activeEditorLayer = layer;
		activateInspectorTab( 'layer' );
		$layer.trigger( 'focus' );
		syncLayerEditorUI();
	} );
	$preview.on( 'click', '.psp-layer-overlay-toggle', function () {
		showEditorOverlay = ! showEditorOverlay;
		refreshPreview();
	} );
	$layerWorkspace.on( 'change', '#psp-layer-inspector-x, #psp-layer-inspector-y', function () {
		var $layer = $preview.find( '.psp-draggable-layer[data-psp-layer="' + activeEditorLayer + '"]' );
		var x = parseInt( $layerInspectorX.val(), 10 );
		var y = parseInt( $layerInspectorY.val(), 10 );

		if ( 1 !== $layer.length || ! Number.isFinite( x ) || ! Number.isFinite( y ) ) {
			return;
		}

		setLayerPosition( $layer, activeEditorLayer, x, y, true, false );
	} );
	$layerWorkspace.on( 'click', '[data-psp-layer-anchor]', function () {
		var values = String( $( this ).attr( 'data-psp-layer-anchor' ) || '' ).split( ',' );
		var $layer = $preview.find( '.psp-draggable-layer[data-psp-layer="' + activeEditorLayer + '"]' );

		if ( 2 !== values.length || 1 !== $layer.length ) {
			return;
		}

		setLayerPosition( $layer, activeEditorLayer, parseInt( values[0], 10 ), parseInt( values[1], 10 ), true, false );
		$layer.trigger( 'focus' );
	} );
	$layerWorkspace.on( 'input change', '[data-psp-style-key]', function () {
		var key = String( $( this ).attr( 'data-psp-style-key' ) || '' );
		var extraIndex = extraLayerIndex( activeEditorLayer );
		var fieldKey = extraIndex >= 0 ? extraLayerStyleKey( key ) : key;

		// Size controls write the per-device field for the current device unless
		// the layer's size is linked across devices.
		if ( extraIndex >= 0 ) {
			if ( 'size' === fieldKey || 'width' === fieldKey ) {
				fieldKey = extraEffectiveSizeKey( extraLayerRow( extraIndex ), fieldKey );
			}
		} else if ( SIZE_DEVICE_KEYS[ key ] ) {
			fieldKey = effectiveSizeKey( key );
		}

		var $field = extraIndex >= 0 ? extraLayerRow( extraIndex ).find( '[name$="[' + fieldKey + ']"]' ) : activeSlideScope().find( '[name$="[' + fieldKey + ']"]' );

		if ( ! fieldKey || 1 !== $field.length ) {
			return;
		}

		$field.val( $( this ).val() );
		if ( 'shape_overlay_type' === key ) {
			syncShapeOverlayControls( $( this ).val() );
		}
		refreshPreview();
	} );
	$layerWorkspace.on( 'change', '[data-psp-shape-lock]', function () {
		var extraIndex = extraLayerIndex( activeEditorLayer );

		if ( extraIndex < 0 ) {
			return;
		}
		extraLayerRow( extraIndex ).find( '[name$="[ratio_locked]"]' ).val( $( this ).is( ':checked' ) ? '1' : '' );
	} );
	$layerWorkspace.on( 'change', '[data-psp-link-key]', function () {
		var linkKey = String( $( this ).attr( 'data-psp-link-key' ) || '' );
		var extraIndex = extraLayerIndex( activeEditorLayer );
		var suffix = 'size' === linkKey ? 'size_linked' : 'pos_linked';
		var fieldKey = extraIndex >= 0 ? suffix : activeEditorLayer + '_' + suffix;
		var $field = extraIndex >= 0 ? extraLayerRow( extraIndex ).find( '[name$="[' + fieldKey + ']"]' ) : activeSlideScope().find( '[name$="[' + fieldKey + ']"]' );

		if ( 1 !== $field.length ) {
			return;
		}
		$field.val( $( this ).is( ':checked' ) ? '1' : '' );
		refreshPreview();
		syncLayerEditorUI();
	} );
	$layerWorkspace.on( 'change', '[data-psp-content-toggle]', function () {
		var key = String( $( this ).attr( 'data-psp-content-toggle' ) || '' );
		var extraIndex = extraLayerIndex( activeEditorLayer );
		var fieldKey = extraIndex >= 0 ? ( /_target$/.test( key ) ? 'target' : '' ) : key;
		var $field;

		if ( ! fieldKey ) {
			return;
		}
		$field = extraIndex >= 0 ? extraLayerRow( extraIndex ).find( '[name$="[' + fieldKey + ']"]' ) : activeSlideScope().find( '[name$="[' + fieldKey + ']"]' );
		if ( 1 !== $field.length ) {
			return;
		}
		$field.prop( 'checked', $( this ).is( ':checked' ) );
		refreshPreview();
	} );
	$layerWorkspace.on( 'input change', '[data-psp-slide-key]', function () {
		var key = String( $( this ).attr( 'data-psp-slide-key' ) || '' );
		// Device-varying slide keys (background position) write the per-device field.
		var fieldKey = SLIDE_DEVICE_KEYS[ key ] ? effectiveSlideKey( key ) : key;
		var $field = activeSlideScope().find( '[name$="[' + fieldKey + ']"]' );

		if ( ! key || 1 !== $field.length ) {
			return;
		}
		$field.val( $( this ).is( ':checkbox' ) ? ( $( this ).is( ':checked' ) ? '1' : '' ) : $( this ).val() );
		if ( 'overlay_type' === key ) {
			syncOverlayControls( activeSlideItem() );
		}
		if ( 'overlay_opacity' === key ) {
			$layerWorkspace.find( '.psp-overlay-opacity-field .psp-range-out' ).text( $( this ).val() + '%' );
		}
		refreshPreview();
	} );
	$layerWorkspace.on( 'input change', '[data-psp-color-toggle], [data-psp-color-value]', function () {
		var key = String( $( this ).attr( 'data-psp-color-toggle' ) || $( this ).attr( 'data-psp-color-value' ) || '' );
		var $toggle = $layerWorkspace.find( '[data-psp-color-toggle="' + key + '"]' );
		var $value = $layerWorkspace.find( '[data-psp-color-value="' + key + '"]' );
		var $field = activeSlideScope().find( '[name$="[' + key + ']"]' );

		if ( ! key || 1 !== $field.length ) {
			return;
		}
		// Adjusting the swatch turns the color on so the choice actually applies.
		if ( $( this ).is( '[data-psp-color-value]' ) && ! $toggle.is( ':checked' ) ) {
			$toggle.prop( 'checked', true );
		}
		$field.val( $toggle.is( ':checked' ) ? String( $value.val() || '' ) : '' );
		refreshPreview();
	} );
	$layerWorkspace.on( 'click', '.psp-seg button', function () {
		var $btn = $( this );
		var $seg = $btn.closest( '.psp-seg' );
		var value = String( $btn.attr( 'data-psp-seg-value' ) || '' );
		var $hidden = $seg.find( 'input[data-psp-style-key], input[data-psp-slide-key]' ).first();

		$seg.find( 'button' ).removeClass( 'is-active' );
		$btn.addClass( 'is-active' );
		if ( $hidden.length ) {
			$hidden.val( value ).trigger( 'change' );
		}
	} );
	function setCollapsibleState( $wrap, collapsed ) {
		$wrap.toggleClass( 'is-collapsed', collapsed );
		$wrap.find( '.psp-accordion-toggle' ).attr( 'aria-expanded', collapsed ? 'false' : 'true' );
		// Disable collapsed controls so a hidden, out-of-range value can never
		// silently fail native validation and block a save.
		$wrap.find( '.psp-accordion-body' ).find( 'input, select, button, textarea' ).prop( 'disabled', collapsed );
	}
	$layerWorkspace.on( 'click', '.psp-accordion-toggle', function () {
		var $wrap = $( this ).closest( '[data-psp-collapsible]' );

		setCollapsibleState( $wrap, ! $wrap.hasClass( 'is-collapsed' ) );
	} );
	$( '[data-psp-collapsible].is-collapsed' ).each( function () {
		setCollapsibleState( $( this ), true );
	} );
	// Internal link picker: live-search published pages/posts over the REST
	// search endpoint and insert the chosen permalink into the link field.
	var linkSearchTimer = null;
	var linkSearchRequest = null;

	function closeLinkPicker() {
		if ( linkSearchTimer ) {
			window.clearTimeout( linkSearchTimer );
			linkSearchTimer = null;
		}
		if ( linkSearchRequest && linkSearchRequest.abort ) {
			linkSearchRequest.abort();
			linkSearchRequest = null;
		}
		$( '.psp-link-picker' ).remove();
		$( document ).off( 'mousedown.pspLinkPicker keydown.pspLinkPicker' );
	}

	function renderLinkResults( $list, items ) {
		$list.empty();
		if ( ! items || ! items.length ) {
			$list.append( $( '<li>', { 'class': 'psp-link-picker-empty' } ).text( config.linkPickerNoResults || 'No matches found.' ) );
			return;
		}
		items.forEach( function ( item ) {
			var subtype = String( item.subtype || '' );
			var $btn = $( '<button>', { type: 'button', 'class': 'psp-link-picker-result', 'data-url': String( item.url || '' ) } );

			$btn.append( $( '<span>', { 'class': 'psp-link-picker-result-title' } ).text( String( item.title || item.url || '' ) ) );
			$btn.append( $( '<span>', { 'class': 'psp-link-picker-result-type' } ).text( subtype ? subtype.charAt( 0 ).toUpperCase() + subtype.slice( 1 ) : '' ) );
			$list.append( $( '<li>' ).append( $btn ) );
		} );
	}

	function searchLinkContent( term, $list ) {
		if ( linkSearchRequest && linkSearchRequest.abort ) {
			linkSearchRequest.abort();
		}
		$list.empty().append( $( '<li>', { 'class': 'psp-link-picker-empty' } ).text( config.linkPickerSearching || 'Searching…' ) );
		linkSearchRequest = $.ajax( {
			url: String( config.restSearchUrl || '' ),
			data: { search: term, per_page: 20, type: 'post', subtype: 'any', _fields: 'title,url,subtype' },
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', String( config.restNonce || '' ) );
			}
		} ).done( function ( items ) {
			renderLinkResults( $list, items );
		} ).fail( function ( xhr, status ) {
			if ( 'abort' === status ) {
				return;
			}
			$list.empty().append( $( '<li>', { 'class': 'psp-link-picker-empty' } ).text( config.linkPickerError || 'Search failed. Check your connection and try again.' ) );
		} );
	}

	$layerWorkspace.on( 'click', '.psp-link-pick', function () {
		var $fieldWrap = $( this ).closest( '.psp-link-field' );
		var $urlInput = $fieldWrap.find( 'input[type="url"]' );
		var $picker;
		var $search;
		var $list;

		if ( $fieldWrap.find( '.psp-link-picker' ).length ) {
			closeLinkPicker();
			return;
		}
		closeLinkPicker();

		$search = $( '<input>', { type: 'search', 'class': 'psp-link-picker-search', placeholder: config.linkPickerPlaceholder || 'Search pages and posts…' } );
		$list = $( '<ul>', { 'class': 'psp-link-picker-results' } );
		$picker = $( '<div>', { 'class': 'psp-link-picker', role: 'dialog', 'aria-label': config.linkPickerTitle || 'Link to existing content' } );
		$picker.append( $search, $list, $( '<p>', { 'class': 'psp-link-picker-hint' } ).text( config.linkPickerHint || 'Or paste any external URL in the field.' ) );
		$fieldWrap.append( $picker );
		$search.trigger( 'focus' );

		$search.on( 'input', function () {
			var term = String( $( this ).val() || '' ).trim();

			if ( linkSearchTimer ) {
				window.clearTimeout( linkSearchTimer );
			}
			if ( term.length < 2 ) {
				$list.empty();
				return;
			}
			linkSearchTimer = window.setTimeout( function () {
				searchLinkContent( term, $list );
			}, 300 );
		} );
		$picker.on( 'click', '.psp-link-picker-result', function () {
			$urlInput.val( String( $( this ).attr( 'data-url' ) || '' ) ).trigger( 'change' );
			closeLinkPicker();
			$urlInput.trigger( 'focus' );
		} );
		$( document ).on( 'mousedown.pspLinkPicker', function ( event ) {
			if ( ! $( event.target ).closest( '.psp-link-picker, .psp-link-pick' ).length ) {
				closeLinkPicker();
			}
		} );
		$( document ).on( 'keydown.pspLinkPicker', function ( event ) {
			if ( 'Escape' === event.key ) {
				closeLinkPicker();
			}
		} );
	} );

	$layerWorkspace.on( 'click', '.psp-inspector-image-pick', function () {
		var $field = $( this ).closest( '.psp-inspector-image-field' ).find( '[data-psp-style-key="image_layer_url"]' );

		if ( ! $field.length ) {
			return;
		}
		if ( ! inspectorImageFrame ) {
			inspectorImageFrame = window.wp.media( {
				title: config.imageLayerFrameTitle || 'Choose image layer',
				button: { text: config.imageLayerFrameButton || 'Use image layer' },
				library: { type: 'image' },
				multiple: false
			} );
			inspectorImageFrame.on( 'select', function () {
				var attachment = inspectorImageFrame.state().get( 'selection' ).first();
				var data;
				var $alt;

				if ( ! attachment ) {
					return;
				}
				data = attachment.toJSON();
				$field.val( data.url || '' ).trigger( 'change' );
				$alt = $layerWorkspace.find( '[data-psp-style-key="image_layer_alt"]' );
				if ( $alt.length && ! String( $alt.val() || '' ) ) {
					$alt.val( data.alt || data.title || '' ).trigger( 'change' );
				}
			} );
		}
		inspectorImageFrame.open();
	} );
	$layerWorkspace.on( 'input change', '[data-psp-animation-key]', function () {
		var suffix = String( $( this ).attr( 'data-psp-animation-key' ) || '' );
		var extraIndex = extraLayerIndex( activeEditorLayer );
		var key = extraIndex >= 0 ? suffix : activeEditorLayer + '_' + suffix;
		var $field = extraIndex >= 0 ? extraLayerRow( extraIndex ).find( '[name$="[' + key + ']"]' ) : activeSlideScope().find( '[name$="[' + key + ']"]' );

		if ( ! suffix || 1 !== $field.length ) {
			return;
		}

		$field.val( $( this ).val() );
		refreshPreview();
	} );
	$preview.on( 'keydown', '.psp-draggable-layer', function ( event ) {
		var $layer = $( this );
		var layer = String( $layer.attr( 'data-psp-layer' ) || '' );
		var keys = layerCoordinateKeys( layer );
		var $item = activeSlideItem();
		var x = coordinateValue( $item, keys.x, 'desktop' === activePreviewDevice ? 5 : 50 );
		var y = coordinateValue( $item, keys.y, 'button' === layer ? 82 : ( 'description' === layer ? 62 : 50 ) );
		var step = event.shiftKey ? 5 : 1;

		if ( [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ].indexOf( event.key ) < 0 ) {
			return;
		}

		event.preventDefault();
		x += 'ArrowLeft' === event.key ? -step : ( 'ArrowRight' === event.key ? step : 0 );
		y += 'ArrowUp' === event.key ? -step : ( 'ArrowDown' === event.key ? step : 0 );
		setLayerPosition( $layer, layer, x, y, true, false );
	} );
	$preview.on( 'click', '.psp-preview-previous', function () {
		activePreviewIndex -= 1;
		refreshControls();
	} );
	$preview.on( 'click', '.psp-preview-next', function () {
		activePreviewIndex += 1;
		refreshControls();
	} );
	$preview.on( 'click', '[data-psp-preview-index]', function () {
		activePreviewIndex = parseInt( $( this ).attr( 'data-psp-preview-index' ), 10 ) || 0;
		refreshControls();
	} );
	$list.on( 'click', '.psp-slide-summary', function ( event ) {
		var $item;

		if ( $( event.target ).closest( 'button, a, input, select, textarea' ).length ) {
			return;
		}
		$item = $( this ).closest( '.psp-media-item' );
		if ( ! $item.length ) {
			return;
		}
		activePreviewIndex = $item.index();
		activeEditorLayer = 'heading';
		activateInspectorTab( 'slide' );
		refreshControls();
	} );

	function openReplaceImage( $item ) {
		if ( ! $item || ! $item.length || ! window.wp || ! window.wp.media ) {
			return;
		}
		replaceImageTarget = $item;
		if ( ! replaceImageFrame ) {
			replaceImageFrame = window.wp.media( {
				title: config.replaceImageTitle || 'Replace slide image',
				button: { text: config.replaceImageButton || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );
			replaceImageFrame.on( 'select', function () {
				var attachment = replaceImageFrame.state().get( 'selection' ).first();

				if ( ! attachment || ! replaceImageTarget || ! replaceImageTarget.length ) {
					return;
				}
				replaceSlideImage( replaceImageTarget, attachment.toJSON() );
			} );
		}
		replaceImageFrame.open();
	}

	$list.on( 'click', '.psp-replace-image', function () {
		openReplaceImage( $( this ).closest( '.psp-media-item' ) );
	} );

	// Background settings panel + Add Layer toolbar: set/replace the active
	// slide's single background image via the Media Library. On a brand-new
	// slider there is no slide yet, so fall back to the add-images picker —
	// the chosen image becomes the first slide (its background).
	$layerWorkspace.on( 'click', '.psp-replace-background, .psp-set-slide-background', function () {
		var $active = activeSlideItem();

		if ( ! $active.length ) {
			$addButton.trigger( 'click' );
			return;
		}
		openReplaceImage( $active );
	} );

	$list.on( 'click', '.psp-remove-image', function () {
		var $item = $( this ).closest( '.psp-media-item' );
		var title = itemTitle( $item );
		var $focusTarget = $item.next( '.psp-media-item' ).find( 'summary' ).first();

		if ( ! $focusTarget.length ) {
			$focusTarget = $item.prev( '.psp-media-item' ).find( 'summary' ).first();
		}

		$item.remove();
		refreshControls();
		if ( $focusTarget.length ) {
			$focusTarget.trigger( 'focus' );
		} else {
			$addButton.trigger( 'focus' );
		}
		announce( formatText( config.removedText || '%1$s removed. %2$d images selected.', title, selectedIds().length, 0 ) );
	} );

	$list.on( 'click', '.psp-move-earlier', function () {
		var $button = $( this );
		var $item = $button.closest( '.psp-media-item' );
		var $previous = $item.prev( '.psp-media-item' );

		if ( $previous.length ) {
			$item.insertBefore( $previous );
			activePreviewIndex = $item.index();
			refreshControls();
			$button.trigger( 'focus' );
			announce( formatText( config.movedText || '%1$s moved to position %2$d of %3$d.', itemTitle( $item ), $item.index() + 1, $list.children( '.psp-media-item' ).length ) );
		}
	} );

	$list.on( 'click', '.psp-move-later', function () {
		var $button = $( this );
		var $item = $button.closest( '.psp-media-item' );
		var $next = $item.next( '.psp-media-item' );

		if ( $next.length ) {
			$item.insertAfter( $next );
			activePreviewIndex = $item.index();
			refreshControls();
			$button.trigger( 'focus' );
			announce( formatText( config.movedText || '%1$s moved to position %2$d of %3$d.', itemTitle( $item ), $item.index() + 1, $list.children( '.psp-media-item' ).length ) );
		}
	} );

	if ( $.fn.sortable ) {
		$list.sortable( {
			items: '.psp-media-item',
			handle: '.psp-slide-summary',
			placeholder: 'psp-media-placeholder',
			forcePlaceholderSize: true,
			tolerance: 'pointer',
			update: function ( event, ui ) {
				void event;
				refreshControls();
				announce( formatText( config.movedText || '%1$s moved to position %2$d of %3$d.', itemTitle( ui.item ), ui.item.index() + 1, $list.children( '.psp-media-item' ).length ) );
			}
		} );
	}

	refreshControls();
}( window.jQuery, window.wp ) );
