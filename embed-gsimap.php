<?php
/*
	Plugin Name: Embed GSIMap
	Description: Embed GSIMap on the page/post.
	Version: 1.0
	Author: Midori IT Office, LLC
	Author URI: https://midoriit.com/
	License: GPLv2 or later
	Text Domain: embed-gsimap
	Domain Path: /languages/
*/

$embed_gsimap = new Embed_GSIMap();

class Embed_GSIMap {

	/** GSIMap tile server definition */
	const STANDARD_URL = 
		'["https://cyberjapandata.gsi.go.jp/xyz/std/${z}/${x}/${y}.png"]';
	const PALE_URL = 
		'["https://cyberjapandata.gsi.go.jp/xyz/pale/${z}/${x}/${y}.png"]';

	static $layers = array( '-', 'STANDARD', 'PALE' );
	public $attribution;

	/*
	 * Constructor
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( &$this, 'embed_gsimap_activate' ) );
		register_uninstall_hook( __FILE__, 'Embed_GSImap::embed_gsimap_uninstall' );
		add_shortcode( 'embed_gsimap', array( &$this, 'embed_gsimap_handler' ) );
		add_action( 'admin_menu', array( &$this, 'embed_gsimap_menu' ) );
		add_action( 'admin_init', array( &$this, 'embed_gsimap_init' ) );
		add_action( 'plugins_loaded', array( &$this, 'embed_gsimap_loaded' ) );
		add_action( 'wp_enqueue_scripts', 'embed_gsimap_scripts' );
		add_action( 'admin_enqueue_scripts', 'embed_gsimap_admin_scripts' );
	}

	/*
	 * Callback for plugins_loaded
	 */
	function embed_gsimap_loaded() {
		$ret = load_plugin_textdomain( 'embed-gsimap', false,
			basename( dirname(__FILE__) ).'/languages/' );
		$this->attribution = '<a href=\"http://www.gsi.go.jp/kikakuchousei/kikakuchousei40182.html\" target=\"_blank\">'.__( 'Geospatial Information Authority of Japan', 'embed-gsimap' ).'</a>';
	}

	/*
	 * Activation hook
	 */
	public function embed_gsimap_activate() {
		add_option( 'embed_gsimap_width', '400' );
		add_option( 'embed_gsimap_height', '300' );
		add_option( 'embed_gsimap_layer', '-' );
		add_option( 'embed_gsimap_lat', '35.32395' );
		add_option( 'embed_gsimap_lon', '139.55598' );
		add_option( 'embed_gsimap_zoom', '15' );
		add_option( 'embed_gsimap_marker', 'show' );
		add_option( 'embed_gsimap_link', 'show' );
	}

	/*
	 * Uninstall hook
	 */
	public static function embed_gsimap_uninstall() {
		delete_option( 'embed_gsimap_width' );
		delete_option( 'embed_gsimap_height' );
		delete_option( 'embed_gsimap_layer' );
		delete_option( 'embed_gsimap_lat' );
		delete_option( 'embed_gsimap_lon' );
		delete_option( 'embed_gsimap_zoom' );
		delete_option( 'embed_gsimap_marker' );
		delete_option( 'embed_gsimap_link' );
	}

	/*
	 * Callback for admin_init
	 */
	public function embed_gsimap_init() {
		add_meta_box( 'embed_gsimap', __( 'Embed GSIMap shortcode', 'embed-gsimap' ),
			array( &$this, 'embed_gsimap_box' ), 'post' );
		add_meta_box( 'embed_gsimap', __( 'Embed GSIMap shortcode', 'embed-gsimap' ),
			array( &$this, 'embed_gsimap_box' ), 'page' );
	}

	/*
	 * Display and process metabox
	 */
	public function embed_gsimap_box() {
		$width = get_option( 'embed_gsimap_width' );
		$height = get_option( 'embed_gsimap_height' );
		$layer = get_option( 'embed_gsimap_layer' );
		$lat = get_option( 'embed_gsimap_lat' );
		$lon = get_option( 'embed_gsimap_lon' );
		$zoom = get_option( 'embed_gsimap_zoom' );

		switch( $layer ) {
			case 'PALE':
				$tileurl = self::PALE_URL;
				break;
			default:
				$tileurl = self::STANDARD_URL;
		}

		echo __( 'Map Layer', 'embed-gsimap' ).
			' : <select id="embed_gsimap_layer" onChange="embed_gsimap_showmap();">';
		foreach ( self::$layers as $ly ) {
			if( strcmp( $ly, '-' ) == 0 ) {
				echo '<option value="'.$ly.'" selected>'.$ly.'</option>';
			} else {
				echo '<option value="'.$ly.'">'.$ly.'</option>';
			}
		}
		echo '</select> ';
		echo '<a class="button" onClick="embed_gsimap_get_cur_pos();">'.__( 'Get Current Position', 'embed-gsimap' ).'</a><br /><br />';
		echo '<div id="embgsimapdiv" style="width:'.$width.'px;height:'.$height.'px;">';
		echo '</div><br />';
		echo '<textarea id="embed_gsimap_shortcode" rows="2" style="max-width:100%;min-width:100%" onClick="this.select();" readonly>';
		echo '</textarea><br />';

		echo '<script type="text/javascript">
			var gsimap;
			var tileurl;
			var lat = '.$lat.';
			var lon = '.$lon.';
			var zoom = '.$zoom.';
			var lonLat;

			function embed_gsimap_showmap() {
				var lonLat;
				switch( embed_gsimap_layer.value ) {
					case "PALE":
						tileurl = '.self::PALE_URL.';
						break;
					default:
						tileurl = '.self::STANDARD_URL.';
				}
				if( gsimap ) {
					zoom = gsimap.getZoom();
					lonLat = gsimap.getCenter().transform(
						gsimap.getProjectionObject(),
						new OpenLayers.Projection( "EPSG:4326" ) );
					lon = lonLat.lon;
					lat = lonLat.lat;
					gsimap.destroy();
				}
				gsimap = new OpenLayers.Map( "embgsimapdiv" );
				gsimap.addLayer(new OpenLayers.Layer.OSM( "", tileurl, 
					{attribution:"'.$this->attribution.'"} ) );
				lonLat = new OpenLayers.LonLat( lon, lat ).transform(
					new OpenLayers.Projection( "EPSG:4326" ),
					gsimap.getProjectionObject() );
				gsimap.setCenter( lonLat, zoom );
				var center = new OpenLayers.Pixel(
					gsimap.getCurrentSize().w / 2,
					gsimap.getCurrentSize().h / 2);
				var cross = new OpenLayers.Control.Crosshairs( {
					imgUrl: "'.plugins_url().'/embed-gsimap/openlayers4jgsi/crosshairs.png",
					size: new OpenLayers.Size( 32, 32 ),
					position: center
				} );
				gsimap.addControl( cross ); 
				gsimap.events.register( "moveend", gsimap, embed_gsimap_moveend );
				embed_gsimap_genshortcode();
			};

			function embed_gsimap_genshortcode() {
				var lonLat = gsimap.getCenter().transform(
					gsimap.getProjectionObject(),
					new OpenLayers.Projection( "EPSG:4326" ) );

				var layer = embed_gsimap_layer.value == "-" ?
					"" : " layer=\"" + embed_gsimap_layer.value + "\"";

				embed_gsimap_shortcode.value = "[embed_gsimap" +
					" lat=\"" + Math.round( lonLat.lat * 100000 ) / 100000 + "\"" +
					" lon=\"" + Math.round( lonLat.lon * 100000 ) / 100000 + "\"" +
					" zoom=\"" + zoom + "\"" +
					layer + "]";
				embed_gsimap_shortcode.select();
			};

			function embed_gsimap_moveend() {
				zoom = gsimap.getZoom();
				embed_gsimap_genshortcode();
			}

			function embed_gsimap_get_cur_pos() {
				if( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition(
						function(pos) {
							lonLat = new OpenLayers.LonLat( pos.coords.longitude, pos.coords.latitude ).transform(
								new OpenLayers.Projection( "EPSG:4326" ),
								gsimap.getProjectionObject() );
							gsimap.setCenter( lonLat, zoom );
						} );
				}
			}
		embed_gsimap_showmap();
		</script>';
	}

	/*
	 * Callback for shortcode
	 */
	public function embed_gsimap_handler( $atts ) {
		$width = get_option( 'embed_gsimap_width' );
		$height = get_option( 'embed_gsimap_height' );
		$option_layer = get_option( 'embed_gsimap_layer' );
		$marker = get_option( 'embed_gsimap_marker' );
		$link = get_option( 'embed_gsimap_link' );
		$uniq = uniqid( "", 1 );

		$tileurl = '';

		extract( shortcode_atts( array(
			'lon' => '0',
			'lat' => '0',
			'zoom' => '1',
			'layer' => ''),
				$atts ) );

		switch( $layer ) {
			case 'PALE':
				$tileurl = self::PALE_URL;
				$base = 'pale';
				break;
			case 'STANDARD':
				$tileurl = self::STANDARD_URL;
				$base = 'std';
		}

		if(empty( $tileurl ) ) {
			switch( $option_layer ) {
				case 'PALE':
					$tileurl = self::PALE_URL;
					$base = 'pale';
					break;
				default:
					$tileurl = self::STANDARD_URL;
					$base = 'std';
			}
		}

		switch( $marker ) {
			case 'green':
				$icon = '-green';
				break;
			case 'blue':
				$icon = '-blue';
				break;
			case 'gold':
				$icon = '-gold';
				break;
			default:
				$icon = '';
		}

		$script = 
			'<div id="gsimapdiv'.$uniq.'" style="width:'.$width.'px; height:'.$height.'px;"></div>
			<script type="text/javascript">
				OpenLayers.IMAGE_RELOAD_ATTEMPTS = 5;
				var map = new OpenLayers.Map( "gsimapdiv'.$uniq.'" );
				map.addLayer( new OpenLayers.Layer.OSM( "", '.$tileurl.', 
					{attribution:"'.$this->attribution.'"} ) );
				var lonLat = new OpenLayers.LonLat( '.$lon.' , '.$lat.' ).transform(
					new OpenLayers.Projection( "EPSG:4326" ),
					map.getProjectionObject() );
				map.setCenter( lonLat, '.$zoom.' );';
				if( $marker !== 'hide' ) {
					$script = $script.
					'var markers = new OpenLayers.Layer.Markers( "Markers" );
					map.addLayer( markers );
					var mkIcon = new OpenLayers.Icon( "'.plugins_url().
						'/embed-gsimap/openlayers/img/marker'.$icon.
						'.png", { w: 21, h: 25 }, { x: -10.5, y: -25 } );
					var marker = new OpenLayers.Marker( lonLat, mkIcon );
					markers.addMarker( marker );';
				}
		$script = $script.'</script>';
		if( $link === 'show' ) {
			$script = $script.
				'<small>
				<a href="https://maps.gsi.go.jp/#'.$zoom.'/'.$lat.'/'.$lon.'/&base='.$base.'" target="_blank">'.
					__( 'View Larger Map', 'embed-gsimap' ).'</a>
				</small>';
		}
		return $script;
	}

	/*
	 * Callback for admin_menu
	 */
	function embed_gsimap_menu() {
		add_options_page( __( 'Embed GSIMap Settings', 'embed-gsimap' ), 'Embed GSIMap',
			'manage_options', 'embed_gsimap', array( &$this, 'embed_gsimap_options' ) );
	}

	/*
	 * Display and process settings page
	 */
	function embed_gsimap_options() {
		if ( !current_user_can( 'manage_options' ) )	{
			wp_die( __( 'insufficient permissions.' ) );
		}

		if ( isset( $_POST['update_option'] ) ) {
			check_admin_referer( 'embed_gsimap_options' );
			$width = $_POST['embed_gsimap_width'];
			if( is_numeric( $width ) ) {
				update_option( 'embed_gsimap_width', $width );
			}
			$height = $_POST['embed_gsimap_height'];
			if( is_numeric( $height ) ) {
				update_option( 'embed_gsimap_height', $height );
			}
			$layer = $_POST['embed_gsimap_layer'];
			update_option( 'embed_gsimap_layer', $layer );
			$lat = $_POST['embed_gsimap_lat'];
			update_option( 'embed_gsimap_lat', $lat );
			$lon = $_POST['embed_gsimap_lon'];
			update_option( 'embed_gsimap_lon', $lon );
			$zoom = $_POST['embed_gsimap_zoom'];
			update_option( 'embed_gsimap_zoom', $zoom );
			$marker = $_POST['embed_gsimap_marker'];
			update_option( 'embed_gsimap_marker', $marker );
			$link = $_POST['embed_gsimap_link'];
			update_option( 'embed_gsimap_link', $link );
		}

		$width = get_option( 'embed_gsimap_width' );
		$height = get_option( 'embed_gsimap_height' );
		$layer = get_option( 'embed_gsimap_layer' );
		$lat = get_option( 'embed_gsimap_lat' );
		$lon = get_option( 'embed_gsimap_lon' );
		$zoom = get_option( 'embed_gsimap_zoom' );
		$marker = get_option( 'embed_gsimap_marker' );
		$link = get_option( 'embed_gsimap_link' );

		echo '<div><h2>'.__( 'Embed GSIMap Settings', 'embed-gsimap' ).'</h2>';
		echo '<form name="form" method="post" action="">';
		wp_nonce_field( 'embed_gsimap_options' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><td>'.__( 'Map Width', 'embed-gsimap' ).'</td>';
		echo '<td><input type="text" name="embed_gsimap_width" value="'.
			$width.'" size="20"></td></tr>';
		echo '<tr><td>'.__( 'Map Height', 'embed-gsimap').'</td>';
		echo '<td><input type="text" name="embed_gsimap_height" value="'.
			$height.'" size="20"></td></tr>';
		echo '<tr><td>'.__( 'Map Layer', 'embed-gsimap').'</td>';
		echo '<td><select name="embed_gsimap_layer" id="embed_gsimap_layer" onChange="embed_gsimap_showmap2();">';
		foreach ( self::$layers as $ly ) {
			if( strcmp( $ly, $layer ) == 0) {
				echo '<option value="'.$ly.'" selected>'.$ly.'</option>';
			} else {
				echo '<option value="'.$ly.'">'.$ly.'</option>';
			}
		}
		echo '</select></td></tr>';
		echo '<tr><td>'.__( 'Home Position', 'embed-gsimap' ).'</td><td>';
		echo __( 'Latitude', 'embed-gsimap' ).
			' : <input type="text" id="embed_gsimap_lat" name="embed_gsimap_lat" value="'.
			$lat.'" size="10" readonly> ';
		echo __( 'Longitude', 'embed-gsimap' ).
			' : <input type="text" id="embed_gsimap_lon" name="embed_gsimap_lon" value="'.
			$lon.'" size="10" readonly> ';
		echo __( 'Zoom', 'embed-gsimap' ).
			' : <input type="text" id="embed_gsimap_zoom" name="embed_gsimap_zoom" value="'.
			$zoom.'" size="5" readonly><br /><br />';
		echo '<a class="button" onClick="embed_gsimap_get_cur_pos();">'.__( 'Get Current Position', 'embed-gsimap' ).'</a><br /><br />';
		echo '<div id="defgsimapdiv" style="width:'.$width.'px;height:'.$height.
			'px;"></div></td></tr>';
		echo '<tr><td>'.__( 'Marker', 'embed-gsimap' ).
			'</td><td>'.
				'<table><tbody><tr>'.
					'<td><input type="radio" name="embed_gsimap_marker" value="hide"'.
						( $marker === 'hide' ? ' checked' : '' ).'>'.
						__( 'Hide', 'embed-gsimap' ).'</td>'.
					'<td><input type="radio" name="embed_gsimap_marker" value="show"'.
						( $marker === 'show' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-gsimap/openlayers/img/marker.png" style="vertical-align:middle;"></td>'.
					'<td><input type="radio" name="embed_gsimap_marker" value="green"'.
						( $marker === 'green' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-gsimap/openlayers/img/marker-green.png" style="vertical-align:middle;"></td>'.
					'<td><input type="radio" name="embed_gsimap_marker" value="gold"'.
						( $marker === 'gold' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-gsimap/openlayers/img/marker-gold.png" style="vertical-align:middle;"></td>'.
					'<td><input type="radio" name="embed_gsimap_marker" value="blue"'.
						( $marker === 'blue' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-gsimap/openlayers/img/marker-blue.png" style="vertical-align:middle;"></td>'.
				'</tr></tbody></table>'.
			'</td></tr>';
		echo '<tr><td>'.__( 'Link to Larger Map', 'embed-gsimap' ).
			'</td><td>'.
				'<table><tbody><tr>'.
					'<td><input type="radio" name="embed_gsimap_link" value="show"'.
						( $link === 'show' ? ' checked' : '' ).'>'.
						__( 'Show', 'embed-gsimap' ).'</td>'.
					'<td><input type="radio" name="embed_gsimap_link" value="hide"'.
						( $link === 'hide' ? ' checked' : '' ).'>'.
						__( 'Hide', 'embed-gsimap' ).'</td>'.
				'</tr></tbody></table>'.
			'</td></tr>';
		echo '</tbody></table>';
		echo '<input type="submit" name="update_option" class="button button-primary" value="'.
			esc_attr__( 'Save Changes' ).'" />';
		echo '</form>';
		echo '</div>';

		echo '<script type="text/javascript">
			var map;
			var tileurl;
			var lat = '.$lat.';
			var lon = '.$lon.';
			var zoom = '.$zoom.';
			var lonLat;

			function embed_gsimap_showmap2() {
				var lonLat;
				switch( embed_gsimap_layer.value ) {
					case "PALE":
						tileurl = ',self::PALE_URL.';
						break;
					default:
						tileurl = ',self::STANDARD_URL.';
				}
				if( map ) {
					zoom = map.getZoom();
					lonLat = map.getCenter().transform(
						map.getProjectionObject(),
						new OpenLayers.Projection( "EPSG:4326" ) );
					lon = lonLat.lon;
					lat = lonLat.lat;
					map.destroy();
				}
				map = new OpenLayers.Map( "defgsimapdiv" );
				map.addLayer( new OpenLayers.Layer.OSM( "", tileurl, 
					{attribution:"'.$this->attribution.'"} ) );
				var lonLat = new OpenLayers.LonLat( lon, lat ).transform(
					new OpenLayers.Projection( "EPSG:4326" ),
					map.getProjectionObject() );
				map.setCenter( lonLat, zoom );
				var center = new OpenLayers.Pixel(
					map.getCurrentSize().w / 2,
					map.getCurrentSize().h / 2 );
				var cross = new OpenLayers.Control.Crosshairs( {
					imgUrl: "'.plugins_url().'/embed-gsimap/openlayers4jgsi/crosshairs.png",
					size: new OpenLayers.Size( 32, 32 ),
					position: center
				});
				map.addControl( cross ); 
				map.events.register( "moveend", map, embed_gsimap_moveend2 );
			}

			function embed_gsimap_getvalue() {
				var lonLat = map.getCenter().transform(
					map.getProjectionObject(),
					new OpenLayers.Projection( "EPSG:4326" ) );
				embed_gsimap_lat.value = Math.round( lonLat.lat * 100000 ) / 100000;
				embed_gsimap_lon.value = Math.round( lonLat.lon * 100000 ) / 100000;
				embed_gsimap_zoom.value = zoom;
			}

			function embed_gsimap_moveend2() {
				zoom = map.getZoom();
				embed_gsimap_getvalue();
			}

			function embed_gsimap_get_cur_pos() {
				if( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition(
						function(pos) {
							lonLat = new OpenLayers.LonLat( pos.coords.longitude, pos.coords.latitude ).transform(
								new OpenLayers.Projection( "EPSG:4326" ),
								map.getProjectionObject() );
							map.setCenter( lonLat, zoom );
						} );
				}
			}

			embed_gsimap_showmap2();
		</script>';
	}
}
	/*
	 * Enqueue scripts
	 */
	function embed_gsimap_scripts() {
		$openlayers = plugins_url().'/embed-gsimap/openlayers/OpenLayers.js';
		wp_enqueue_script( 'OpenLayers', $openlayers );
		$ol4jgsi = plugins_url().'/embed-gsimap/openlayers4jgsi/Crosshairs.js';
		wp_enqueue_script( 'OL4JGSI', $ol4jgsi );
	}
	/*
	 * Enqueue admin scripts
	 */
	function embed_gsimap_admin_scripts() {
		$openlayers = plugins_url().'/embed-gsimap/openlayers/OpenLayers.js';
		wp_enqueue_script( 'OpenLayers', $openlayers );
		$ol4jgsi = plugins_url().'/embed-gsimap/openlayers4jgsi/Crosshairs.js';
		wp_enqueue_script( 'OL4JGSI', $ol4jgsi );
	}
?>
