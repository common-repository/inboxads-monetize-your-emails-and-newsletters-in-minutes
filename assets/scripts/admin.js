/**
 * @file Backend scripts.
 */

// Charting library.
const Chart = require( './Chart.min.js' );

// Scrollbar library.
const SimpleBar = require( './simplebar.min.js' );

/**
 * @param {String} url
 * @param {String} arg
 */
function removeQueryArg( url, arg ) {
	var urlparts = url.split( '?' );

	if ( urlparts.length < 2 ) {
		return url;
	}

	var prefix = encodeURIComponent( arg ) + '=';
	var args = urlparts[1].split( /[&;]/g );

	for ( var i = args.length; i-- > 0; ) {
		if ( args[i].lastIndexOf( prefix, 0 ) !== -1 ) {
			args.splice( i, 1 );
		}
	}

	return urlparts[0] + ( args.length > 0 ? '?' + args.join( '&' ) : '' );
}

( function( $ ) {

	let statsChart = null;

	// Window loaded.
	$( window ).load( function() {

		// Remove type and nonce query args from URL.
		let pageURL = window.location.href;
		if ( ! pageURL.match( /type=activate/ ) ) {
			pageURL = removeQueryArg( pageURL, 'type' );
			pageURL = removeQueryArg( pageURL, '_wpnonce' );
			window.history.replaceState( {}, '', pageURL );
		}

		// Delete data functionality.
		$( '#wpr-delete-data' ).on( 'click', function() {
			$( this ).fadeOut( 100, function() {
				$( '#wpr-delete-data-warning' ).slideDown( 200 );
			} );
		} );
		$( '#wpr-delete-data-cancel' ).on( 'click', function() {
			$( '#wpr-delete-data-warning' ).slideUp( 200, function() {
				$( '#wpr-delete-data' ).fadeIn( 200 );
			} );
		} );

		// Modals.
		$( '#wpr-inboxads-content .modal-trigger' ).on( 'click', function( e ) {
			e.preventDefault();

			var $btn = $( this );
			var $modal = $( '#' + $btn.data( 'modal' ) );

			if ( $modal.length ) {
				$modal.addClass( 'visible' ).siblings( '.modal' ).removeClass( 'visible' );
				$modalWrap.addClass( 'visible' );
			}
		} );

		var $modalWrap = $( '#wpr-inboxads-modal-wrap' );

		$( '#wpr-inboxads-content .modal .close' ).on( 'click', function( e ) {
			e.preventDefault();

			$( this ).parent().add( $modalWrap ).removeClass( 'visible' );
		} );

		var $pluginsScroll = $( '#wpr-inboxads-plugins .plugins-list-scroll-inner' );
		if ( $pluginsScroll.length ) {
			new SimpleBar(
				$pluginsScroll[0],
				{
					autoHide: false,
					forceVisible: 'y',
				}
			);
		}

		// Init stats chart.
		const width = $( this ).width();

		const $chart = document.getElementById( 'wpr-inboxads-chart' );

		if ( ! $chart || ! inboxads_chart_data ) {
			return;
		}

		const ctx = $chart.getContext( '2d' );

		var chartColors = {
			orange: '#F47521',
			orange_bg: '#F4752109',
			green: '#6AAE19',
			green_bg: '#6AAE1909'
		};

		var has_data = false;

		var has_views_data = false;
		$.each( inboxads_chart_data.views, function( index, value ) {
			if ( value > 0 ) {
				has_data = true;
				has_views_data = true;
				// Break loop.
				return false;
			}
		} );

		var has_revenue_data = false;
		$.each( inboxads_chart_data.revenue, function( index, value ) {
			if ( value > 0 ) {
				has_data = true;
				has_revenue_data = true;
				// Break loop.
				return false;
			}
		} );

		var views_gradient = ctx.createLinearGradient( 0, 0, 0, 300 );
		views_gradient.addColorStop( 0, 'rgba(244, 117, 33, 0.2)' );
		views_gradient.addColorStop( 0.4, 'rgba(244, 117, 33, 0.1)' );
		views_gradient.addColorStop( 1, 'rgba(244, 117, 33, 0)' );

		var revenue_gradient = ctx.createLinearGradient( 0, 0, 0, 300 );
		revenue_gradient.addColorStop( 0, 'rgba(106, 174, 25, 0.2)' );
		revenue_gradient.addColorStop( 0.4, 'rgba(106, 174, 25, 0.1)' );
		revenue_gradient.addColorStop( 1, 'rgba(106, 174, 25, 0)' );

		statsChart = new Chart( ctx, {
			type: 'line',
			data: {
				labels: inboxads_chart_data.date,
				datasets: [
					{
						data: inboxads_chart_data.views,
						yAxisID: 'y-axis-1',
						fill: true,
						borderColor: chartColors.orange,
						backgroundColor: views_gradient,
						pointBackgroundColor: chartColors.orange,
						pointRadius: has_views_data ? 4 : 0,
						pointHoverRadius: 4,
						showLine: has_views_data,
					},
					{
						data: inboxads_chart_data.revenue,
						yAxisID: 'y-axis-2',
						fill: true,
						borderColor: chartColors.green,
						backgroundColor: revenue_gradient,
						pointBackgroundColor: chartColors.green,
						pointRadius: has_revenue_data ? 4 : 0,
						pointHoverRadius: 4,
						showLine: has_revenue_data,
					}
				]
			},
			options: {
				aspectRatio: 3,
				title: {
					display: false
				},
				legend: {
					display: false
				},
				tooltips: {
					enabled: false,
					mode: 'index',
					intersect: true
				},
				hover: {
					mode: 'index',
					intersect: true
				},
				scales: {
					xAxes: [
						{
							display: has_data,
							gridLines: {
								drawBorder: false,
								tickMarkLength: 0,
								zeroLineWidth: 0,
								drawOnChartArea: false
							},
							ticks: {
								padding: 22,
								callback: function( value, index ) {
									return index % 2 === 0 ? value : '';
								}
							}
						}
					],
					yAxes: [
						{
							type: 'linear',
							display: true,
							position: 'left',
							id: 'y-axis-1',
							gridLines: {
								drawBorder: false,
								tickMarkLength: 0,
								borderDash: [10, 15],
								zeroLineBorderDash: [10, 15],
								zeroLineColor: 'rgba(0,0,0,0.1)'
							},
							ticks: {
								min: 0,
								suggestedMax: has_revenue_data ? null : 200,
								maxTicksLimit: 5,
								padding: 12,
								callback: function( value, index, values ) {
									return '$' + value.toLocaleString();
								}
							},
						}, {
							type: 'linear',
							display: true,
							position: 'right',
							id: 'y-axis-2',
							gridLines: {
								drawBorder: false,
								tickMarkLength: 0,
								zeroLineWidth: 0,
								drawOnChartArea: false
							},
							ticks: {
								min: 0,
								suggestedMax: has_views_data ? null : 200,
								maxTicksLimit: 5,
								padding: 12,
								callback: function( value, index, values ) {
									return value.toLocaleString();
								}
							},
						}
					],
				}
			}
		} );

	} );

}( jQuery ) );
