/**
 * Mngsk Recent Content List — pagination_mode="async" / カテゴリフィルタ用のクライアント側スクリプト。
 *
 * ビルド手順を持たないプラグインのため、フレームワーク無しの素のJSで記述。
 * .mngsk-recent-content[data-mngsk-recent-content-async] 要素内で以下を処理する:
 * 1. ページネーションリンク (.mngsk-recent-content__pagination a) の非同期ページ送り
 * 2. カテゴリフィルタボタン (.mngsk-recent-content__filter) / プルダウン (.mngsk-recent-content__filter-select) の非同期カテゴリ切替
 *
 * キャッシュ & プリフェッチ:
 * - キャッシュキーは `category + ':' + page` (例: "all:1", "news:2")。
 * - ページ表示完了時、現在カテゴリの次ページをブラウザアイドル時に裏で先読み。
 * - フィルタボタンやページリンクへのマウスホバー/フォーカス時にも該当データを先読み。
 * - 同一キーへの重複リクエストは Promise を共有。
 * - 通信失敗時は通常のリンク遷移 (同期URL) へ自動フォールバック。
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

	function pageParam( container ) {
		return container.getAttribute( 'data-mngsk-recent-content-page-param' ) ||
			( window.MngskRecentContent && window.MngskRecentContent.pageParam ) ||
			'mngsk_recent_content_page';
	}

	function catParam( container ) {
		return container.getAttribute( 'data-mngsk-recent-content-cat-param' ) ||
			( window.MngskRecentContent && window.MngskRecentContent.catParam ) ||
			'mngsk_recent_content_category';
	}

	function currentPage( container ) {
		return parseInt( container.getAttribute( 'data-mngsk-recent-content-page' ), 10 ) || 1;
	}

	function maxPages( container ) {
		return parseInt( container.getAttribute( 'data-mngsk-recent-content-max-pages' ), 10 ) || 1;
	}

	function activeCategory( container ) {
		return container.getAttribute( 'data-mngsk-recent-content-active-category' ) || 'all';
	}

	function isLoadMore( container ) {
		return '1' === container.getAttribute( 'data-mngsk-recent-content-load-more' );
	}

	function pageFromLink( link, container ) {
		var url;
		try {
			url = new URL( link.href, window.location.href );
		} catch ( err ) {
			return null;
		}
		return url.searchParams.get( pageParam( container ) );
	}

	function initState( container ) {
		if ( ! container.__mngskCache ) {
			container.__mngskCache = {}; // "category:page" -> HTML
			container.__mngskInFlight = {}; // "category:page" -> Promise
		}
	}

	function makeKey( category, page ) {
		return String( category || 'all' ) + ':' + String( page || 1 );
	}

	/**
	 * 指定カテゴリ・ページを取得する。キャッシュ済みなら即時解決、通信中ならPromiseを共有。
	 * activate=true の場合のみ、取得後に画面へ反映する(falseは先読み専用)。
	 */
	function fetchContent( container, category, page, activate, navigationId, incremental ) {
		initState( container );
		category = String( category || 'all' );
		page = parseInt( page, 10 ) || 1;
		var key = makeKey( category, page );

		if ( container.__mngskCache[ key ] ) {
			if ( activate ) {
				applyIfCurrent( container, container.__mngskCache[ key ], navigationId, incremental );
			}
			return Promise.resolve( container.__mngskCache[ key ] );
		}

		if ( container.__mngskInFlight[ key ] ) {
			var pending = container.__mngskInFlight[ key ];
			return activate
				? pending.then( function ( html ) {
					applyIfCurrent( container, html, navigationId, incremental );
					return html;
				} )
				: pending;
		}

		var atts = container.getAttribute( 'data-mngsk-recent-content-atts' ) || '{}';
		var locale = container.getAttribute( 'data-mngsk-recent-content-locale' ) || '';
		var currentUrl = container.getAttribute( 'data-mngsk-recent-content-url' ) || window.location.href;
		var body = new URLSearchParams();
		body.set( 'action', window.MngskRecentContent.action );
		body.set( 'atts', atts );
		body.set( 'page', String( page ) );
		body.set( 'category', category );
		if ( locale ) {
			body.set( 'locale', locale );
		}
		if ( currentUrl ) {
			body.set( 'current_url', currentUrl );
		}
		if ( incremental && isLoadMore( container ) ) {
			body.set( 'incremental_load_more', '1' );
		}

		var request = fetch( window.MngskRecentContent.ajaxUrl, {
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
					throw new Error( 'mngsk-recent-content: invalid Ajax response' );
				}
				container.__mngskCache[ key ] = json.data.html;
				return json.data.html;
			} )
			.finally( function () {
				delete container.__mngskInFlight[ key ];
			} );

		container.__mngskInFlight[ key ] = request;

		if ( activate ) {
			return request.then( function ( html ) {
				applyIfCurrent( container, html, navigationId, incremental );
				return html;
			} );
		}

		return request.catch( function () {
			// 先読みの失敗は静かに無視する(必要になった時点で改めて取得される)。
		} );
	}

	function applyIfCurrent( container, html, navigationId, incremental ) {
		if ( navigationId === container.__mngskNavigationId ) {
			applyHtml( container, html, incremental );
		}
	}

	function applyHtml( container, html, incremental ) {
		var tmp = document.createElement( 'div' );
		tmp.innerHTML = html;
		var fresh = tmp.querySelector( '.mngsk-recent-content' );
		if ( ! fresh ) {
			return;
		}

		var incomingPage = parseInt( fresh.getAttribute( 'data-mngsk-recent-content-page' ), 10 ) || 1;
		var incomingCat = fresh.getAttribute( 'data-mngsk-recent-content-active-category' ) || 'all';
		var prevCat = activeCategory( container );

		if ( incremental && isLoadMore( container ) && incomingCat === prevCat && incomingPage > currentPage( container ) ) {
			appendLoadMoreHtml( container, fresh );
		} else {
			container.innerHTML = fresh.innerHTML;
		}

		[
			'data-mngsk-recent-content-atts',
			'data-mngsk-recent-content-page',
			'data-mngsk-recent-content-max-pages',
			'data-mngsk-recent-content-load-more',
			'data-mngsk-recent-content-locale',
			'data-mngsk-recent-content-url',
			'data-mngsk-recent-content-active-category',
			'data-mngsk-recent-content-cat-param',
			'data-mngsk-recent-content-page-param',
			'data-mngsk-recent-content-instance'
		].forEach( function ( attr ) {
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
	 */
	function appendLoadMoreHtml( container, fresh ) {
		var existingContent = container.querySelector( '.mngsk-recent-content__content' );
		var freshContent = fresh.querySelector( '.mngsk-recent-content__content' );
		if ( ! existingContent || ! freshContent ) {
			container.innerHTML = fresh.innerHTML;
			return;
		}

		while ( freshContent.firstChild ) {
			existingContent.appendChild( freshContent.firstChild );
		}

		var existingPagination = container.querySelector( '.mngsk-recent-content__pagination' );
		var freshPagination = fresh.querySelector( '.mngsk-recent-content__pagination' );
		if ( existingPagination && freshPagination ) {
			existingPagination.replaceWith( freshPagination );
		} else if ( existingPagination ) {
			existingPagination.remove();
		} else if ( freshPagination ) {
			container.appendChild( freshPagination );
		}
	}

	/**
	 * 表示が確定した直後、現在のアクティブカテゴリにおける「次のページ」を裏で取得しておく。
	 */
	function schedulePrefetchNext( container ) {
		if ( ! shouldPrefetch() ) {
			return;
		}
		var next = currentPage( container ) + 1;
		if ( next > maxPages( container ) ) {
			return;
		}
		var cat = activeCategory( container );

		var run = function () {
			fetchContent( container, cat, next, false );
		};

		if ( window.requestIdleCallback ) {
			window.requestIdleCallback( run, { timeout: 2000 } );
		} else {
			window.setTimeout( run, 300 );
		}
	}

	function paginationLinkFromEvent( container, event ) {
		var link = event.target.closest ? event.target.closest( 'a' ) : null;
		if ( ! link || ! container.contains( link ) || ! link.closest( '.mngsk-recent-content__pagination' ) ) {
			return null;
		}
		return link;
	}

	function filterLinkFromEvent( container, event ) {
		var link = event.target.closest ? event.target.closest( 'a' ) : null;
		if ( ! link || ! container.contains( link ) || ! link.closest( '.mngsk-recent-content__filters--buttons' ) ) {
			return null;
		}
		return link;
	}

	function filterSelectFromEvent( container, event ) {
		var select = event.target.closest ? event.target.closest( 'select' ) : null;
		if ( ! select || ! container.contains( select ) || ! select.classList.contains( 'mngsk-recent-content__filter-select' ) ) {
			return null;
		}
		return select;
	}

	function handleClick( container, event ) {
		// 1. ページネーションリンクのクリック
		var pageLink = paginationLinkFromEvent( container, event );
		if ( pageLink ) {
			var page = pageFromLink( pageLink, container );
			if ( ! page ) {
				return;
			}
			event.preventDefault();

			pageLink.setAttribute( 'aria-busy', 'true' );
			container.__mngskNavigationId = ( container.__mngskNavigationId || 0 ) + 1;
			var navId = container.__mngskNavigationId;
			var cat = activeCategory( container );
			var incremental = isLoadMore( container );

			fetchContent( container, cat, page, true, navId, incremental )
				.catch( function () {
					if ( navId === container.__mngskNavigationId ) {
						window.location.assign( pageLink.href );
					}
				} )
				.then( function () {
					pageLink.removeAttribute( 'aria-busy' );
				} );
			return;
		}

		// 2. カテゴリフィルタボタンのクリック
		var filterLink = filterLinkFromEvent( container, event );
		if ( filterLink ) {
			var targetCat = filterLink.getAttribute( 'data-category' ) || 'all';
			event.preventDefault();

			if ( targetCat === activeCategory( container ) && 1 === currentPage( container ) ) {
				return;
			}

			filterLink.setAttribute( 'aria-busy', 'true' );
			container.__mngskNavigationId = ( container.__mngskNavigationId || 0 ) + 1;
			var filterNavId = container.__mngskNavigationId;

			fetchContent( container, targetCat, 1, true, filterNavId, false )
				.catch( function () {
					if ( filterNavId === container.__mngskNavigationId ) {
						window.location.assign( filterLink.href );
					}
				} )
				.then( function () {
					filterLink.removeAttribute( 'aria-busy' );
				} );
		}
	}

	function handleChange( container, event ) {
		var select = filterSelectFromEvent( container, event );
		if ( ! select ) {
			return;
		}
		var targetCat = select.value || 'all';
		if ( targetCat === activeCategory( container ) && 1 === currentPage( container ) ) {
			return;
		}

		select.setAttribute( 'aria-busy', 'true' );
		container.__mngskNavigationId = ( container.__mngskNavigationId || 0 ) + 1;
		var navId = container.__mngskNavigationId;

		var selectedOpt = select.options[ select.selectedIndex ];
		var fallbackUrl = ( selectedOpt && selectedOpt.getAttribute( 'data-url' ) ) || '';

		fetchContent( container, targetCat, 1, true, navId, false )
			.catch( function () {
				if ( navId === container.__mngskNavigationId ) {
					if ( fallbackUrl ) {
						window.location.assign( fallbackUrl );
					} else if ( select.form ) {
						select.form.submit();
					}
				}
			} )
			.then( function () {
				select.removeAttribute( 'aria-busy' );
			} );
	}

	function handleHover( container, event ) {
		if ( ! shouldPrefetch() ) {
			return;
		}

		// ページネーションリンクのホバー
		var pageLink = paginationLinkFromEvent( container, event );
		if ( pageLink ) {
			var page = pageFromLink( pageLink, container );
			if ( page ) {
				fetchContent( container, activeCategory( container ), page, false );
			}
			return;
		}

		// フィルタボタンのホバー (当該カテゴリの1ページ目を先読み)
		var filterLink = filterLinkFromEvent( container, event );
		if ( filterLink ) {
			var cat = filterLink.getAttribute( 'data-category' ) || 'all';
			fetchContent( container, cat, 1, false );
		}
	}

	onDomReady( function () {
		var containers = document.querySelectorAll( '.mngsk-recent-content[data-mngsk-recent-content-async]' );
		for ( var i = 0; i < containers.length; i++ ) {
			( function ( container ) {
				initState( container );
				container.addEventListener( 'click', function ( event ) {
					handleClick( container, event );
				} );
				container.addEventListener( 'change', function ( event ) {
					handleChange( container, event );
				} );
				// mouseoverはbubbleするため委譲できる
				container.addEventListener( 'mouseover', function ( event ) {
					handleHover( container, event );
				} );
				// キーボード操作(Tabでのフォーカス)でも先読みできるよう、focusinも拾う
				container.addEventListener( 'focusin', function ( event ) {
					handleHover( container, event );
				} );
				schedulePrefetchNext( container );
			} )( containers[ i ] );
		}
	} );
} )();
