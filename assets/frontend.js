( function () {
	'use strict';

	var config = window.mySliderProFrontend || {};
	var reducedMotion = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : null;

	function clamp( value, minimum, maximum ) {
		return Math.min( Math.max( value, minimum ), maximum );
	}

	function slideLabel( index, total ) {
		return String( config.slideText || 'Slide %1$d of %2$d' )
			.replace( '%1$d', index + 1 )
			.replace( '%2$d', total );
	}

	function initializeSlider( slider ) {
		var viewport = slider.querySelector( '[data-psp-slider-viewport]' );
		var slides = Array.prototype.slice.call( slider.querySelectorAll( '[data-psp-slider-slide]' ) );
		var dots = Array.prototype.slice.call( slider.querySelectorAll( '[data-psp-slider-dot]' ) );
		var previousButton = slider.querySelector( '[data-psp-slider-previous]' );
		var nextButton = slider.querySelector( '[data-psp-slider-next]' );
		var toggleButton = slider.querySelector( '[data-psp-slider-toggle]' );
		var index = 0;
		var scrollFrame = 0;
		var autoplayTimer = 0;
		var manualPaused = false;
		var hoverPaused = false;
		var focusPaused = false;
		var loop = '1' === slider.getAttribute( 'data-psp-loop' );
		var autoplay = '1' === slider.getAttribute( 'data-psp-autoplay' );
		var pauseOnHover = '1' === slider.getAttribute( 'data-psp-pause-on-hover' );
		var interval = parseInt( slider.getAttribute( 'data-psp-interval' ), 10 );
		var resizeObserver;

		if ( ! viewport || slides.length < 2 ) {
			return;
		}

		if ( interval < 1000 ) {
			interval = 5000;
		}

		function isReducedMotion() {
			return Boolean( reducedMotion && reducedMotion.matches );
		}

		function canAutoplay() {
			return autoplay && ! manualPaused && ! hoverPaused && ! focusPaused && ! isReducedMotion() && ! document.hidden;
		}

		function stopAutoplay() {
			if ( autoplayTimer ) {
				window.clearInterval( autoplayTimer );
				autoplayTimer = 0;
			}
		}

		function updateToggle() {
			if ( ! toggleButton ) {
				return;
			}

			toggleButton.setAttribute( 'aria-label', manualPaused ? ( config.playLabel || 'Resume slide rotation' ) : ( config.pauseLabel || 'Pause slide rotation' ) );
			toggleButton.firstChild.textContent = manualPaused ? '>' : 'II';
		}

		function restartAutoplay() {
			stopAutoplay();
			updateToggle();

			if ( ! canAutoplay() ) {
				return;
			}

			autoplayTimer = window.setInterval( function () {
				goTo( index + 1, true );
			}, interval );
		}

		function updateActiveState( nextIndex ) {
			index = clamp( nextIndex, 0, slides.length - 1 );

			slides.forEach( function ( slide, slideIndex ) {
				slide.setAttribute( 'aria-hidden', slideIndex === index ? 'false' : 'true' );
			} );
			dots.forEach( function ( dot, dotIndex ) {
				dot.setAttribute( 'aria-current', dotIndex === index ? 'true' : 'false' );
			} );

			if ( previousButton ) {
				previousButton.disabled = ! loop && 0 === index;
			}
			if ( nextButton ) {
				nextButton.disabled = ! loop && index === slides.length - 1;
			}
		}

		function normalizedIndex( requestedIndex ) {
			if ( loop ) {
				return ( requestedIndex + slides.length ) % slides.length;
			}

			return clamp( requestedIndex, 0, slides.length - 1 );
		}

		function goTo( requestedIndex, shouldRestartAutoplay ) {
			var targetIndex = normalizedIndex( requestedIndex );
			var targetLeft = targetIndex * viewport.clientWidth;

			if ( typeof viewport.scrollTo === 'function' ) {
				viewport.scrollTo( {
					left: targetLeft,
					behavior: isReducedMotion() ? 'auto' : 'smooth'
				} );
			} else {
				viewport.scrollLeft = targetLeft;
			}

			updateActiveState( targetIndex );

			if ( shouldRestartAutoplay ) {
				restartAutoplay();
			}
		}

		viewport.addEventListener( 'scroll', function () {
			if ( scrollFrame ) {
				return;
			}

			scrollFrame = window.requestAnimationFrame( function () {
				var nextIndex = Math.round( viewport.scrollLeft / Math.max( viewport.clientWidth, 1 ) );

				scrollFrame = 0;
				updateActiveState( nextIndex );
			} );
		} );

		viewport.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				goTo( index - 1, true );
			} else if ( 'ArrowRight' === event.key ) {
				event.preventDefault();
				goTo( index + 1, true );
			} else if ( 'Home' === event.key ) {
				event.preventDefault();
				goTo( 0, true );
			} else if ( 'End' === event.key ) {
				event.preventDefault();
				goTo( slides.length - 1, true );
			}
		} );

		if ( previousButton ) {
			previousButton.addEventListener( 'click', function () {
				goTo( index - 1, true );
			} );
		}
		if ( nextButton ) {
			nextButton.addEventListener( 'click', function () {
				goTo( index + 1, true );
			} );
		}

		dots.forEach( function ( dot ) {
			dot.addEventListener( 'click', function () {
				goTo( parseInt( dot.getAttribute( 'data-psp-slider-dot' ), 10 ), true );
			} );
		} );

		if ( pauseOnHover ) {
			slider.addEventListener( 'mouseenter', function () {
				hoverPaused = true;
				restartAutoplay();
			} );
			slider.addEventListener( 'mouseleave', function () {
				hoverPaused = false;
				restartAutoplay();
			} );
		}

		slider.addEventListener( 'focusin', function () {
			focusPaused = true;
			restartAutoplay();
		} );
		slider.addEventListener( 'focusout', function ( event ) {
			if ( ! slider.contains( event.relatedTarget ) ) {
				focusPaused = false;
				restartAutoplay();
			}
		} );

		if ( toggleButton ) {
			toggleButton.addEventListener( 'click', function () {
				manualPaused = ! manualPaused;
				restartAutoplay();
			} );
		}

		if ( 'ResizeObserver' in window ) {
			resizeObserver = new window.ResizeObserver( function () {
				viewport.scrollLeft = index * viewport.clientWidth;
			} );
			resizeObserver.observe( viewport );
		} else {
			window.addEventListener( 'resize', function () {
				viewport.scrollLeft = index * viewport.clientWidth;
			} );
		}

		document.addEventListener( 'visibilitychange', restartAutoplay );
		if ( reducedMotion && typeof reducedMotion.addEventListener === 'function' ) {
			reducedMotion.addEventListener( 'change', restartAutoplay );
		}

		updateActiveState( 0 );
		restartAutoplay();
	}

	Array.prototype.forEach.call( document.querySelectorAll( '[data-psp-slider]' ), initializeSlider );
}() );
