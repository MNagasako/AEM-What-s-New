/**
 * AEM What's New — pagination_mode="async" 用のクライアント側スクリプト。
 *
 * ビルド手順を持たないプラグインのため、フレームワーク無しの素のJSで書いてある。
 * .whatsnew[data-aem-whatsnew-async] 要素の中で .whatsnew-pagination 内の<a>がクリックされたら、
 * href の ?whatsnew_page=N を読み取ってAjaxで該当ページ分のHTMLを取得し、その場で差し替える。
 *
 * 先読み(プリフェッチ)キャッシュ:
 * - ページを表示するたびに、ブラウザがアイドルな時間を使って「次のページ」を裏で取得し
 *   コンテナ単位のキャッシュに保持しておく(表示はしない)。
 * - ページネーションのリンクにマウスオーバー/フォーカスした時点でも、そのページを先読みする。
 * - 一度取得したページ(キャッシュ済み)へ戻る/進む場合は、Ajaxを発行せず即座に表示する。
 * - 同じページへの重複リクエストは1本にまとめる(in-flightのPromiseを共有)。
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

	function shouldPrefetch() {
		var conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
		if ( conn ) {
			if ( conn.saveData ) {
				return false;
			}
			if ( conn.effectiveType && /2g/.test( conn.effectiveType ) ) {
				return false;
			}
		}
		return true;
	}

	function pageFromLink( link ) {
		var url;
		try {
			url = new URL( link.href, window.location.href );
		} catch ( err ) {
			return null;
		}
		return url.searchParams.get( window.AEMWhatsNew.pageParam );
	}

	function currentPage( container ) {
		return parseInt( container.getAttribute( 'data-aem-whatsnew-page' ), 10 ) || 1;
	}

	function maxPages( container ) {
		return parseInt( container.getAttribute( 'data-aem-whatsnew-max-pages' ), 10 ) || 1;
	}

	function isLoadMore( container ) {
		return '1' === container.getAttribute( 'data-aem-whatsnew-load-more' );
	}

	function initState( container ) {
		if ( ! container.__awnCache ) {
			container.__awnCache = {}; // page(文字列) -> 取得済みHTML
			container.__awnInFlight = {}; // page(文字列) -> 進行中のPromise
		}
	}

	/**
	 * 指定ページを取得する。キャッシュ済みなら即solve、進行中なら同じPromiseを共有する。
	 * activate=trueの場合のみ、取得後に画面へ反映する(falseはプリフェッチ専用)。
	 */
	function fetchPage( container, page, activate, navigationId ) {
		initState( container );
		page = String( page );

		if ( container.__awnCache[ page ] ) {
			if ( activate ) {
				applyIfCurrent( container, container.__awnCache[ page ], navigationId );
			}
			return Promise.resolve( container.__awnCache[ page ] );
		}

		if ( container.__awnInFlight[ page ] ) {
			var pending = container.__awnInFlight[ page ];
			return activate
				? pending.then( function ( html ) {
					applyIfCurrent( container, html, navigationId );
					return html;
				} )
				: pending;
		}

		var atts = container.getAttribute( 'data-aem-whatsnew-atts' ) || '{}';
		var body = new URLSearchParams();
		body.set( 'action', window.AEMWhatsNew.action );
		body.set( 'atts', atts );
		body.set( 'page', page );
		if ( isLoadMore( container ) ) {
			body.set( 'incremental_load_more', '1' );
		}

		var request = fetch( window.AEMWhatsNew.ajaxUrl, {
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
					throw new Error( 'aem-whatsnew: invalid Ajax response' );
				}
				container.__awnCache[ page ] = json.data.html;
				return json.data.html;
			} )
			.finally( function () {
				delete container.__awnInFlight[ page ];
			} );

		container.__awnInFlight[ page ] = request;

		if ( activate ) {
			return request.then( function ( html ) {
				applyIfCurrent( container, html, navigationId );
				return html;
			} );
		}

		return request.catch( function () {
			// プリフェッチの失敗は無視する(必要になった時点で改めて取得される)。
		} );
	}

	function applyIfCurrent( container, html, navigationId ) {
		if ( navigationId === container.__awnNavigationId ) {
			applyHtml( container, html );
		}
	}

	function applyHtml( container, html ) {
		var tmp = document.createElement( 'div' );
		tmp.innerHTML = html;
		var fresh = tmp.querySelector( '.whatsnew' );
		if ( ! fresh ) {
			return;
		}
		var incomingPage = parseInt( fresh.getAttribute( 'data-aem-whatsnew-page' ), 10 ) || 1;
		if ( isLoadMore( container ) && incomingPage > currentPage( container ) ) {
			appendLoadMoreHtml( container, fresh );
		} else {
			container.innerHTML = fresh.innerHTML;
		}
		[ 'data-aem-whatsnew-atts', 'data-aem-whatsnew-page', 'data-aem-whatsnew-max-pages', 'data-aem-whatsnew-load-more' ].forEach( function ( attr ) {
			var value = fresh.getAttribute( attr );
			if ( value ) {
				container.setAttribute( attr, value );
			} else {
				container.removeAttribute( attr );
			}
		} );
		schedulePrefetchNext( container );
	}

	/**
	 * Ajaxの「もっと見る」は、サーバーから受け取った次ページ分だけを既存の一覧へ追加する。
	 * これにより、ページ数に比例して先頭から再取得する必要がなくなる。
	 */
	function appendLoadMoreHtml( container, fresh ) {
		var existingContent = container.querySelector( '.whatsnew-content' );
		var freshContent = fresh.querySelector( '.whatsnew-content' );
		if ( ! existingContent || ! freshContent ) {
			container.innerHTML = fresh.innerHTML;
			return;
		}

		while ( freshContent.firstChild ) {
			existingContent.appendChild( freshContent.firstChild );
		}

		var existingPagination = container.querySelector( '.whatsnew-pagination' );
		var freshPagination = fresh.querySelector( '.whatsnew-pagination' );
		if ( existingPagination && freshPagination ) {
			existingPagination.replaceWith( freshPagination );
		} else if ( existingPagination ) {
			existingPagination.remove();
		} else if ( freshPagination ) {
			container.appendChild( freshPagination );
		}
	}

	/**
	 * 表示が確定した直後、ブラウザがアイドルなタイミングで「次のページ」を裏で取得しておく。
	 */
	function schedulePrefetchNext( container ) {
		if ( ! shouldPrefetch() ) {
			return;
		}
		var next = currentPage( container ) + 1;
		if ( next > maxPages( container ) ) {
			return;
		}

		var run = function () {
			fetchPage( container, next, false );
		};

		if ( window.requestIdleCallback ) {
			window.requestIdleCallback( run, { timeout: 2000 } );
		} else {
			window.setTimeout( run, 300 );
		}
	}

	function paginationLinkFromEvent( container, event ) {
		var link = event.target.closest ? event.target.closest( 'a' ) : null;
		if ( ! link || ! container.contains( link ) || ! link.closest( '.whatsnew-pagination' ) ) {
			return null;
		}
		return link;
	}

	function handleClick( container, event ) {
		var link = paginationLinkFromEvent( container, event );
		if ( ! link ) {
			return;
		}
		var page = pageFromLink( link );
		if ( ! page ) {
			return;
		}
		event.preventDefault();

		link.setAttribute( 'aria-busy', 'true' );
		container.__awnNavigationId = ( container.__awnNavigationId || 0 ) + 1;
		var navigationId = container.__awnNavigationId;
		fetchPage( container, page, true, navigationId )
			.catch( function () {
				// Ajaxが恒久的に失敗する環境でも、同期ページネーションとして使い続けられる。
				if ( navigationId === container.__awnNavigationId ) {
					window.location.assign( link.href );
				}
			} )
			.then( function () {
				link.removeAttribute( 'aria-busy' );
			} );
	}

	function handleHover( container, event ) {
		if ( ! shouldPrefetch() ) {
			return;
		}
		var link = paginationLinkFromEvent( container, event );
		if ( ! link ) {
			return;
		}
		var page = pageFromLink( link );
		if ( ! page ) {
			return;
		}
		fetchPage( container, page, false );
	}

	onDomReady( function () {
		var containers = document.querySelectorAll( '.whatsnew[data-aem-whatsnew-async]' );
		for ( var i = 0; i < containers.length; i++ ) {
			( function ( container ) {
				initState( container );
				container.addEventListener( 'click', function ( event ) {
					handleClick( container, event );
				} );
				// mouseoverはbubbleするため委譲できる(mouseenterは非対応)。
				container.addEventListener( 'mouseover', function ( event ) {
					handleHover( container, event );
				} );
				// キーボード操作(Tabでのフォーカス)でも先読みできるよう、focusinも拾う。
				container.addEventListener( 'focusin', function ( event ) {
					handleHover( container, event );
				} );
				schedulePrefetchNext( container );
			} )( containers[ i ] );
		}
	} );
} )();
