=== MY Slider PRO ===
Contributors: Aminudin Murad
Tags: slider, image slider, responsive, homepage, carousel
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Copyright: 2026 Aminudin Murad

Build fast, responsive, and accessible sliders in WordPress.

== Description ==

MY Slider PRO lets photographers, creative professionals, and content-rich WordPress sites build responsive sliders from the native Media Library.

Create a slider, choose and reorder images, add slide copy, calls to action, optional layer links, and optional image layers, then edit it at desktop, tablet, and phone widths before placing it on any page or post with a shortcode. Select Heading, Description, Button, or Image as independent layers directly on the visual canvas or layer list, drag-sort their front-to-back stack, use grid and safe-area guides, drag with magnetic snapping, enter precise X/Y values in the inspector, choose independent Desktop, Tablet, and Phone anchors, apply font, style, and animation controls, and adjust everything with a live preview. Images retain their WordPress responsive sources and alternative text.

MY Slider PRO is free and open source. Copyright (C) 2026 Aminudin Murad, released under the GNU General Public License v3.0. See the bundled LICENSE file for terms.

== Installation ==

1. Upload the official plugin ZIP from **Plugins > Add New > Upload Plugin**.
2. Activate MY Slider PRO.
3. Open **MY Slider PRO > Add Slider**.
4. Choose Media Library images, add slide content, check the responsive preview, and save.
5. Copy the generated `[myslider id="123"]` shortcode into a page or post.

== Frequently Asked Questions ==

= Does uninstalling remove sliders or media? =

No. User content is preserved by default.

== Support the Plugin ==

MY Slider PRO is free and open source. If it helps you build sliders, please consider supporting continued development and WordPress compatibility testing:

* GitHub Sponsors: https://github.com/sponsors/aminudinmurad
* Ko-fi: https://ko-fi.com/aminudinmurad
* PayPal: https://www.paypal.com/paypalme/aminudinmurad

Thank you for helping keep MY Slider PRO improving and freely available.

== Changelog ==

= 1.0.3 =

* Unified the visual editor with the slider library: the same branded gradient header, a single consistent button system across both screens, and the support card at the bottom of the editor.
* Fixed button icon alignment and the Slider Name and Replace background row alignment in the editor.

= 1.0.2 =

* Link fields now include an internal page/post picker that live-searches published content; external URLs still work.
* Added "Open link in a new tab" to the heading, text, and image links (previously button only).
* Animation controls are always visible in the layer inspector.
* Refreshed the slider library and editor UI: branded header, clearer buttons and cards, and a tidier Slider Settings layout.

= 1.0.1 =

* Custom MY Slider PRO admin menu icon in place of the generic dashicon.

= 1.0.0 =

* Initial release.
* Responsive slider editor with slide-by-slide copy, calls to action, image layers, focal-point controls, and a live device preview.
* Native WordPress Media Library slider creation with image selection and keyboard-accessible ordering.
* Card-grid slider overview with an assignable per-slider thumbnail (Set thumbnail / Remove, first-slide fallback), quick rename, and one-click duplicate actions, protected by capability and nonce checks.
* Independent Heading, Text, Button, and Image layers with optional link URLs, sortable front-to-back stacking, whole-row drag reordering, magnetic preview dragging, keyboard nudging, separate Desktop/Tablet/Phone positions, independent typography and alignment controls, image layer sizing, colors, CTA radius, and CTA padding.
* Per-type layer numbering (Heading 1, Heading 2, …); up to 5 slides per slider and up to 2 layers of each type per slide, enforced server-side and client-side, with Add New Slide and the per-type Add layer buttons greying out at their caps.
* Bounded 10-100% opacity controls for every layer, with Montserrat as the default slider typeface.
* Canvas-first editing: click a Heading, Text, or Button layer to edit it in place, with a focused Layer Inspector sidebar for position, styling, and animation, and the sortable Layers list and Slider Settings in full-width panels below the preview.
* Per-layer animation controls for fade, slide, zoom, delay, duration, and easing.
* Responsive desktop, tablet, and mobile heights, content alignment, text width, touch-friendly CTA sizing, optional phone-arrow hiding, dots, autoplay, looping, and pause-on-hover controls.
* Full-width or Boxed slider width with a responsive maximum width.
* Per-slide background layer: replaceable background image (panel and toolbar controls), fill mode (cover, fill, fit, actual size), nine-point position, an optional grayscale filter, and a background overlay (None, Solid, or Gradient with colour, second colour, opacity, and direction) rendered on the front end and in the editor preview, all with native colour pickers.
* Per-layer responsive linking: keep a layer's size or position linked across devices, or set them independently for Desktop, Tablet, and Phone.
* CSS scroll-snap touch swipe, keyboard navigation, a pause control, and reduced-motion support without a third-party runtime dependency.
* `[myslider]` shortcode with one-click copying; images keep their WordPress responsive sources and alternative text.
* Portable import and export: download a slider as a self-contained .zip (settings, slides, and bundled images) and import it on the same or another site; bundled images are sideloaded into the Media Library and every reference is remapped, guarded by capability, nonce, archive validation, and zip-slip and zip-bomb checks.
* Compact editor: a per-device settings matrix, grouped playback and navigation controls, segmented alignment and fill controls, and collapsible inspector sections.
* Multi-author slider isolation, attachment access checks, bounded slider lists, and directory-listing guard files.
