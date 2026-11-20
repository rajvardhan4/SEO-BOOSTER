/**
 * SEO Boster: reads the site's real typography and button style from the homepage
 * (desktop, tablet and phone width) so Area pages can look exactly the same.
 */
( function () {
	'use strict';
	var cfg = window.seobStyle;
	if ( ! cfg ) {
		return;
	}

	var WIDTHS = { desktop: 1280, tablet: 820, phone: 390 };
	var SKIP = 'header,footer,nav,#main-header,#top-header,#main-footer,.et-l--header,.et-l--footer,.elementor-location-header,.elementor-location-footer,#wpadminbar,.site-header,.site-footer,[role="navigation"],.menu,.et-menu,#et-top-navigation';
	var BTN = '.et_pb_button,.elementor-button,.wp-block-button__link,.wp-element-button,a.button,button.button,.btn,a[class*="btn"],a[class*="button"],.ast-button,input[type="submit"]';
	var PROPS = [ 'fontFamily', 'fontSize', 'fontWeight', 'lineHeight', 'letterSpacing', 'textTransform' ];
	var BPROPS = PROPS.concat( [ 'fontStyle', 'textDecorationLine', 'borderRadius', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderWidth', 'borderStyle', 'borderColor', 'backgroundColor', 'backgroundImage', 'color', 'boxShadow', 'transitionDuration' ] );
	// What can change on mouse over (read from the site's own CSS :hover rules).
	var HOVER = {
		'background-color': 'backgroundColor',
		'background-image': 'backgroundImage',
		'color': 'color',
		'border-top-color': 'borderColor',
		'border-top-width': 'borderWidth',
		'border-top-style': 'borderStyle',
		'box-shadow': 'boxShadow',
		'padding-top': 'paddingTop',
		'padding-right': 'paddingRight',
		'padding-bottom': 'paddingBottom',
		'padding-left': 'paddingLeft',
		'letter-spacing': 'letterSpacing',
		'text-decoration-line': 'textDecorationLine'
	};

	function visible( el, win ) {
		var r = el.getBoundingClientRect();
		if ( r.width < 2 || r.height < 2 ) {
			return false;
		}
		var cs = win.getComputedStyle( el );
		return cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat( cs.opacity ) > 0.05;
	}

	function relLine( o ) {
		if ( o && o.lineHeight && o.lineHeight !== 'normal' && o.fontSize ) {
			// Keep line height relative so it scales with the size.
			o.lineHeight = ( parseFloat( o.lineHeight ) / parseFloat( o.fontSize ) ).toFixed( 2 );
		}
		return o;
	}

	function read( el, win, props ) {
		var cs = win.getComputedStyle( el ), o = {};
		props.forEach( function ( p ) {
			o[ p ] = cs[ p ];
		} );
		if ( props.indexOf( 'borderWidth' ) > -1 ) {
			o.borderRadius = cs.borderTopLeftRadius;
			o.borderWidth = cs.borderTopWidth;
			o.borderStyle = cs.borderTopStyle;
			o.borderColor = cs.borderTopColor;
			o.transitionDuration = String( cs.transitionDuration || '' ).split( ',' )[ 0 ].trim();
		}
		return o;
	}

	function pick( els, win, props ) {
		var counts = {}, best = null, bestN = 0;
		els.forEach( function ( el ) {
			var o = read( el, win, props );
			var key = o.fontFamily + '|' + o.fontSize + '|' + o.fontWeight;
			counts[ key ] = ( counts[ key ] || 0 ) + 1;
			if ( counts[ key ] > bestN ) {
				bestN = counts[ key ];
				best = o;
			}
		} );
		return relLine( best );
	}

	/* ---------- Hover: read the site's own :hover CSS rules ---------- */

	function allRules( doc, win ) {
		var out = [];
		function walk( list ) {
			Array.prototype.forEach.call( list || [], function ( r ) {
				if ( r.type === 1 ) {
					out.push( r );
				} else if ( r.cssRules && r.type !== 7 ) {
					if ( r.media && r.media.mediaText && ! win.matchMedia( r.media.mediaText ).matches ) {
						return;
					}
					walk( r.cssRules );
				}
			} );
		}
		Array.prototype.forEach.call( doc.styleSheets, function ( sh ) {
			var list;
			try {
				list = sh.cssRules; // Files from another domain cannot be read; skipped.
			} catch ( e ) {
				return;
			}
			if ( sh.media && sh.media.mediaText && ! win.matchMedia( sh.media.mediaText ).matches ) {
				return;
			}
			walk( list );
		} );
		return out;
	}

	function splitSel( text ) {
		var parts = [], depth = 0, cur = '';
		for ( var i = 0; i < text.length; i++ ) {
			var c = text[ i ];
			if ( c === '(' ) {
				depth++;
			} else if ( c === ')' ) {
				depth--;
			}
			if ( c === ',' && depth === 0 ) {
				parts.push( cur );
				cur = '';
			} else {
				cur += c;
			}
		}
		parts.push( cur );
		return parts.map( function ( p ) {
			return p.trim();
		} );
	}

	function spec( sel ) {
		var ids = ( sel.match( /#[\w-]+/g ) || [] ).length;
		var cls = ( sel.match( /\.[\w-]+|\[[^\]]+\]|:(?!:)[\w-]+/g ) || [] ).length;
		var tags = ( sel.replace( /\[[^\]]*\]/g, '' ).replace( /[.#:][\w-]+/g, '' ).match( /[a-z][\w-]*/gi ) || [] ).length;
		return ids * 10000 + cls * 100 + tags;
	}

	function hoverOf( el, win, rules ) {
		var hits = [];
		rules.forEach( function ( r, i ) {
			var st = r.selectorText;
			if ( ! st || st.indexOf( ':hover' ) < 0 ) {
				return;
			}
			splitSel( st ).forEach( function ( part ) {
				if ( part.indexOf( ':hover' ) < 0 || /::?(after|before)/i.test( part ) ) {
					return;
				}
				try {
					if ( el.matches( part.replace( /:hover/g, '' ) ) ) {
						hits.push( { r: r, sp: spec( part ), i: i } );
					}
				} catch ( e ) {}
			} );
		} );
		if ( ! hits.length ) {
			return null;
		}
		hits.sort( function ( a, b ) {
			return a.sp - b.sp || a.i - b.i;
		} );
		// Apply the hover rules to a hidden copy of the button in CSS order (important ones last),
		// so variables, shorthands and em sizes turn into the real values.
		var decls = [], late = [];
		hits.forEach( function ( h ) {
			String( h.r.style.cssText || '' ).split( ';' ).forEach( function ( d ) {
				var i = d.indexOf( ':' );
				if ( i < 1 ) {
					return;
				}
				var prop = d.slice( 0, i ).trim().toLowerCase(), val = d.slice( i + 1 ).trim(), imp = /!\s*important$/i.test( val );
				val = val.replace( /!\s*important$/i, '' ).trim();
				if ( ! val || /^(transition|animation|display|position|content|cursor|pointer-events)/.test( prop ) ) {
					return;
				}
				( imp ? late : decls ).push( [ prop, val ] );
			} );
		} );
		decls = decls.concat( late );
		if ( ! decls.length ) {
			return null;
		}
		var probe = el.cloneNode( false ), out = {}, base = win.getComputedStyle( el );
		probe.removeAttribute( 'id' );
		probe.style.cssText = 'position:absolute!important;left:-9999px!important;top:0!important;visibility:hidden!important;transition:none!important;pointer-events:none!important;';
		decls.forEach( function ( d ) {
			probe.style.setProperty( d[ 0 ], d[ 1 ], 'important' );
		} );
		el.parentNode.insertBefore( probe, el.nextSibling );
		var cs = win.getComputedStyle( probe );
		Object.keys( HOVER ).forEach( function ( p ) {
			var v = cs.getPropertyValue( p ), b = base.getPropertyValue( p );
			if ( v && v !== b ) {
				out[ HOVER[ p ] ] = v;
			}
		} );
		probe.remove();
		return Object.keys( out ).length ? out : null;
	}

	/* ---------- Buttons: main style and second (outline) style ---------- */

	function buttons( els, win, doc ) {
		var groups = {}, order = [];
		els.forEach( function ( el ) {
			var o = read( el, win, BPROPS );
			var r = el.getBoundingClientRect();
			var key = [ o.backgroundColor, o.backgroundImage, o.color, o.borderColor, o.borderWidth, o.borderRadius, o.fontSize ].join( '|' );
			if ( ! groups[ key ] ) {
				groups[ key ] = { n: 0, area: 0, o: o, el: el, els: [], look: [ o.backgroundColor, o.backgroundImage, o.color, o.borderColor ].join( '|' ) };
				order.push( key );
			}
			groups[ key ].n++;
			groups[ key ].els.push( el );
			groups[ key ].area = Math.max( groups[ key ].area, r.width * r.height );
		} );
		var list = order.map( function ( k ) {
			return groups[ k ];
		} );
		if ( ! list.length ) {
			return {};
		}
		var filled = function ( g ) {
			var m = String( g.o.backgroundColor ).match( /[\d.]+/g );
			var a = m && m.length > 3 ? parseFloat( m[ 3 ] ) : 1;
			return ( m && a > 0.5 ) || ( g.o.backgroundImage && g.o.backgroundImage !== 'none' );
		};
		var lum = function ( c ) {
			var m = String( c ).match( /[\d.]+/g ) || [ 255, 255, 255 ];
			return ( 0.2126 * m[ 0 ] + 0.7152 * m[ 1 ] + 0.0722 * m[ 2 ] ) / 255;
		};
		// Main button = the site's call-to-action: a filled (not white) button, the first one on the
		// page counts most (hero), then how often it is used. Second = the next filled style, else an outline.
		var first = null;
		els.some( function ( el ) {
			var cs = win.getComputedStyle( el ), m = String( cs.backgroundColor ).match( /[\d.]+/g );
			if ( m && ( m.length < 4 || parseFloat( m[ 3 ] ) > 0.5 ) && lum( cs.backgroundColor ) < 0.92 ) {
				first = el;
				return true;
			}
			return false;
		} );
		var score = function ( g ) {
			var sc = g.n;
			if ( filled( g ) && lum( g.o.backgroundColor ) < 0.92 ) {
				sc += 10;
			}
			if ( first && g.els.indexOf( first ) > -1 ) {
				sc += 3;
			}
			return sc;
		};
		list.sort( function ( a, b ) {
			return score( b ) - score( a ) || b.area - a.area;
		} );
		var rules = allRules( doc, win ), out = {};
		out.btn = relLine( list[ 0 ].o );
		out.btn_hover = hoverOf( list[ 0 ].el, win, rules );
		var rest = list.slice( 1 ).filter( function ( g ) {
			return g.look !== list[ 0 ].look && lum( g.o.color ) < 0.9 || filled( g );
		} ).filter( function ( g ) {
			return g.look !== list[ 0 ].look;
		} );
		rest.sort( function ( a, b ) {
			return ( filled( b ) ? 1 : 0 ) - ( filled( a ) ? 1 : 0 ) || b.n - a.n;
		} );
		if ( rest.length ) {
			out.btn2 = relLine( rest[ 0 ].o );
			out.btn2_hover = hoverOf( rest[ 0 ].el, win, rules );
		}
		return out;
	}

	function measure( doc, win ) {
		var out = {};
		[ 'h1', 'h2', 'h3', 'h4' ].forEach( function ( tag ) {
			var els = Array.prototype.filter.call( doc.querySelectorAll( tag ), function ( el ) {
				return ! el.closest( SKIP ) && visible( el, win ) && el.textContent.trim().length > 2;
			} );
			if ( els.length ) {
				out[ tag ] = pick( els, win, PROPS );
			}
		} );
		var ps = Array.prototype.filter.call( doc.querySelectorAll( 'p' ), function ( el ) {
			return ! el.closest( SKIP ) && visible( el, win ) && el.textContent.trim().length > 40;
		} );
		if ( ps.length ) {
			out.p = pick( ps, win, PROPS );
		}
		var bs = Array.prototype.filter.call( doc.querySelectorAll( BTN ), function ( el ) {
			var t = ( el.textContent || el.value || '' ).trim();
			var inMenu = el.closest( '.menu,.et-menu,#top-menu,.sub-menu,.et_mobile_menu,.elementor-nav-menu,#wpadminbar' );
			var cs = win.getComputedStyle( el );
			// A real button has a background or a border, and short text.
			var looks = ( cs.backgroundColor !== 'rgba(0, 0, 0, 0)' && cs.backgroundColor !== 'transparent' ) || parseFloat( cs.borderTopWidth ) > 0;
			return ! inMenu && visible( el, win ) && t.length > 1 && t.length < 40 && looks;
		} );
		if ( bs.length ) {
			var b = buttons( bs, win, doc );
			Object.keys( b ).forEach( function ( k ) {
				if ( b[ k ] ) {
					out[ k ] = b[ k ];
				}
			} );
		}
		// Font files the homepage loads, so Area pages load the same ones.
		out.fonts = Array.prototype.map.call( doc.querySelectorAll( 'link[rel="stylesheet"][href*="fonts.googleapis.com"],link[rel="stylesheet"][href*="fonts.bunny.net"],link[rel="stylesheet"][href*="use.typekit.net"]' ), function ( l ) {
			return l.href;
		} ).slice( 0, 6 );
		return out;
	}

	function frame( width ) {
		return new Promise( function ( resolve ) {
			var f = document.createElement( 'iframe' ), done = false;
			f.setAttribute( 'aria-hidden', 'true' );
			f.setAttribute( 'tabindex', '-1' );
			f.style.cssText = 'position:absolute;left:-12000px;top:0;width:' + width + 'px;height:1400px;border:0;opacity:0;pointer-events:none;';
			function finish( data ) {
				if ( done ) {
					return;
				}
				done = true;
				f.remove();
				resolve( data );
			}
			setTimeout( function () {
				finish( null );
			}, 25000 );
			f.onload = function () {
				var doc, win;
				try {
					doc = f.contentDocument;
					win = f.contentWindow;
					if ( ! doc || ! doc.body || doc.location.href === 'about:blank' ) {
						finish( null );
						return;
					}
				} catch ( e ) {
					finish( null ); // Blocked by the site (X-Frame-Options / CSP).
					return;
				}
				var go = function () {
					setTimeout( function () {
						try {
							finish( measure( doc, win ) );
						} catch ( e ) {
							finish( null );
						}
					}, 1200 );
				};
				if ( doc.fonts && doc.fonts.ready ) {
					doc.fonts.ready.then( go, go );
				} else {
					go();
				}
			};
			f.src = cfg.home;
			document.body.appendChild( f );
		} );
	}

	function status( text, kind ) {
		var el = document.getElementById( 'seob-style-status' );
		if ( el ) {
			el.textContent = text;
			el.className = 'seob-help' + ( kind ? ' seob-status-' + kind : '' );
		}
	}

	function run( manual ) {
		var result = {};
		status( cfg.i18n.working );
		return frame( WIDTHS.desktop ).then( function ( d ) {
			result.desktop = d;
			return d ? frame( WIDTHS.tablet ) : null;
		} ).then( function ( t ) {
			result.tablet = t;
			return result.desktop ? frame( WIDTHS.phone ) : null;
		} ).then( function ( p ) {
			result.phone = p;
			if ( ! result.desktop ) {
				status( cfg.i18n.fail, 'bad' );
				return;
			}
			var fd = new FormData();
			fd.append( 'action', 'seob_ap_style' );
			fd.append( 'nonce', cfg.nonce );
			fd.append( 'style', JSON.stringify( result ) );
			fd.append( 'apply', manual ? '1' : '0' );
			return fetch( cfg.ajax, { method: 'POST', body: fd, credentials: 'same-origin' } ).then( function ( r ) {
				return r.json();
			} ).then( function ( r ) {
				if ( r && r.success ) {
					status( cfg.i18n.done, 'ok' );
					if ( manual ) {
						window.location.reload();
						return;
					}
					var list = document.getElementById( 'seob-style-summary' );
					if ( list && r.data.summary ) {
						list.innerHTML = '';
						r.data.summary.forEach( function ( line ) {
							var li = document.createElement( 'li' );
							li.textContent = line;
							list.appendChild( li );
						} );
					}
				} else if ( manual ) {
					status( cfg.i18n.fail, 'bad' );
				}
			} );
		} ).catch( function () {
			if ( manual ) {
				status( cfg.i18n.fail, 'bad' );
			}
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var b = e.target.closest ? e.target.closest( '#seob-style-detect' ) : null;
		if ( b ) {
			e.preventDefault();
			var q = b.getAttribute( 'data-seob-confirm-click' );
			if ( q && ! window.confirm( q ) ) {
				return;
			}
			run( true );
		}
	} );
	if ( cfg.auto ) {
		window.addEventListener( 'load', function () {
			run( false );
		} );
	}
}() );
