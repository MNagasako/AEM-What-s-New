/**
 * AEM What's New — pagination_mode="async" 用のクライアント側スクリプト。
 *
 * ビルド手順を持たないプラグインのため、フレームワーク無しの素のJSで書いてある。
 * .whatsnew[data-aem-whatsnew-async] 要素の中で .whatsnew-pagination 内の<a>がクリックされたら、
 * href の ?whatsnew_page=N を読み取ってAjaxで該当ページ分のHTMLを取得し、その場で差し替える。
 */
( function () {
	'use strict';

	function onDomReady( fn ) {
		if ( 'loading' !== document.readyState ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function handleClick( container, event ) {
		var link = event.target.closest ? event.target.closest( 'a' ) : null;
		if ( ! link || ! container.contains( link ) ) {
			return;
		}
		if ( ! link.closest( '.whatsnew-pagination' ) ) {
			return;
		}

		var url;
		try {
			url = new URL( link.href, window.location.href );
		} catch ( err ) {
			return;
		}

		var page = url.searchParams.get( window.AEMWhatsNew.pageParam );
		if ( ! page ) {
			return;
		}

		event.preventDefault();
		fetchPage( container, page, link );
	}

	function fetchPage( container, page, link ) {
		var atts = container.getAttribute( 'data-aem-whatsnew-atts' ) || '{}';
		var body = new URLSearchParams();
		body.set( 'action', window.AEMWhatsNew.action );
		body.set( 'atts', atts );
		body.set( 'page', page );

		if ( link ) {
			link.setAttribute( 'aria-busy', 'true' );
		}

		fetch( window.AEMWhatsNew.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( res ) {
				return res.json();
			} )
			.then( function ( json ) {
				if ( ! json || ! json.success || ! json.data || ! json.data.html ) {
					return;
				}
				var tmp = document.createElement( 'div' );
				tmp.innerHTML = json.data.html;
				var fresh = tmp.querySelector( '.whatsnew' );
				if ( ! fresh ) {
					return;
				}
				container.innerHTML = fresh.innerHTML;
				var freshAtts = fresh.getAttribute( 'data-aem-whatsnew-atts' );
				if ( freshAtts ) {
					container.setAttribute( 'data-aem-whatsnew-atts', freshAtts );
				}
			} )
			.catch( function () {
				// 失敗時は何もしない(表示はそのまま。リンクは通常のhrefへ辿れるので再試行できる)。
			} )
			.then( function () {
				if ( link ) {
					link.removeAttribute( 'aria-busy' );
				}
			} );
	}

	onDomReady( function () {
		var containers = document.querySelectorAll( '.whatsnew[data-aem-whatsnew-async]' );
		for ( var i = 0; i < containers.length; i++ ) {
			( function ( container ) {
				container.addEventListener( 'click', function ( event ) {
					handleClick( container, event );
				} );
			} )( containers[ i ] );
		}
	} );
} )();
