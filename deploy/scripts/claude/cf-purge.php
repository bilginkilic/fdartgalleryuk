<?php
/**
 * Cloudflare zone tam temizligi. Token wp ayarindan okunur ve CIKTIYA YAZILMAZ.
 * Zone adiyla aranir — zone id gomulu degildir.
 *
 * Kullanim:
 *   ./gonder.sh cf-purge.php --wp fdartgallery.com
 *   ./gonder.sh cf-purge.php --wp fdartgallery.com -- <alan-adi>
 */
$token = get_option( 'cloudflare_api_key' );
if ( ! $token ) { echo "DURDU: cloudflare_api_key bos.\n"; return; }

$alan = $args[0] ?? preg_replace( '#^https?://#', '', home_url() );
$alan = preg_replace( '#^(www|dev)\.#', '', rtrim( $alan, '/' ) );

$basliklar = [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ];

$get = wp_remote_get( 'https://api.cloudflare.com/client/v4/zones?name=' . rawurlencode( $alan ),
	[ 'headers' => $basliklar, 'timeout' => 30 ] );
if ( is_wp_error( $get ) ) { echo "HATA: " . $get->get_error_message() . "\n"; return; }
$zid = json_decode( wp_remote_retrieve_body( $get ), true )['result'][0]['id'] ?? '';
if ( ! $zid ) { echo "HATA: '$alan' icin zone bulunamadi.\n"; return; }
echo "zone: $alan (" . substr( $zid, 0, 6 ) . "…)\n";

$pr = wp_remote_post( "https://api.cloudflare.com/client/v4/zones/$zid/purge_cache",
	[ 'headers' => $basliklar, 'body' => wp_json_encode( [ 'purge_everything' => true ] ), 'timeout' => 40 ] );
if ( is_wp_error( $pr ) ) { echo "HATA: " . $pr->get_error_message() . "\n"; return; }
$r = json_decode( wp_remote_retrieve_body( $pr ), true );
echo "purge_everything: " . ( ! empty( $r['success'] )
	? 'BASARILI' : 'BASARISIZ — ' . wp_json_encode( $r['errors'] ?? [] ) ) . "\n";
