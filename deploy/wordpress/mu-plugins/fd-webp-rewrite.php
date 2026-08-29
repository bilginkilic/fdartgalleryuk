<?php
/**
 * Plugin Name: FD WebP Rewrite
 * Description: uploads altinda <dosya>.webp varsa gorsel adreslerini ona cevirir.
 *              Orijinaller diskte durur; .webp yoksa adres degismez.
 * Version:     1.0
 *
 * Neden bu yontem: Cloudflare Free planinda `Vary: Accept` ile format pazarligi
 * desteklenmiyor (Vary for Images yalnizca Pro+). Ayni URL'den iki farkli format
 * servis etmek edge onbellegini bozar. Bu yuzden WebP AYRI URL'de sunulur —
 * hem Cloudflare normal sekilde onbellege alir hem de orijinal dosya korunur.
 *
 * Kaldirmak icin: bu dosyayi mu-plugins dizininden silin. Veritabaninda hicbir
 * degisiklik yapilmaz, adresler aninda orijinallere doner.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FD_WebP_Rewrite {

	/** @var string uploads dizininin disk yolu */
	private $basedir = '';

	/** @var string uploads dizininin adresi */
	private $baseurl = '';

	/** @var array<string,bool> ayni istek icinde tekrar disk okumamak icin */
	private $cache = array();

	public function __construct() {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}
		$this->basedir = untrailingslashit( $uploads['basedir'] );
		$this->baseurl = untrailingslashit( $uploads['baseurl'] );

		// Panelde ve duzenleyicide orijinal dosyalarla calisilsin.
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		add_filter( 'wp_get_attachment_url', array( $this, 'filter_url' ), 20 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'filter_image_src' ), 20 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_srcset' ), 20 );
		add_filter( 'the_content', array( $this, 'filter_html' ), 999 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_html' ), 999 );
		add_filter( 'widget_text', array( $this, 'filter_html' ), 999 );
	}

	/**
	 * Adres bir uploads gorseline isaret ediyorsa ve yaninda .webp varsa
	 * .webp adresini dondurur.
	 */
	public function filter_url( $url ) {
		if ( ! is_string( $url ) || '' === $this->baseurl ) {
			return $url;
		}
		if ( ! preg_match( '/\.(jpe?g|png)$/i', $url ) ) {
			return $url;
		}
		if ( 0 !== strpos( $url, $this->baseurl ) ) {
			return $url;
		}

		if ( isset( $this->cache[ $url ] ) ) {
			return $this->cache[ $url ] ? $url . '.webp' : $url;
		}

		$relative = substr( $url, strlen( $this->baseurl ) );
		$relative = strtok( $relative, '?' );                 // sorgu dizesini at
		$path     = $this->basedir . $relative . '.webp';

		// Dizin disina cikma girisimlerine karsi
		$real = realpath( $path );
		$ok   = ( false !== $real
			&& 0 === strpos( $real, $this->basedir . DIRECTORY_SEPARATOR )
			&& is_file( $real ) );

		$this->cache[ $url ] = $ok;

		return $ok ? $url . '.webp' : $url;
	}

	/** wp_get_attachment_image_src() dizisinin ilk elemani adrestir. */
	public function filter_image_src( $image ) {
		if ( is_array( $image ) && isset( $image[0] ) ) {
			$image[0] = $this->filter_url( $image[0] );
		}
		return $image;
	}

	/** srcset icindeki her boyut ayri dosyadir, hepsi tek tek kontrol edilir. */
	public function filter_srcset( $sources ) {
		if ( ! is_array( $sources ) ) {
			return $sources;
		}
		foreach ( $sources as $key => $source ) {
			if ( isset( $source['url'] ) ) {
				$sources[ $key ]['url'] = $this->filter_url( $source['url'] );
			}
		}
		return $sources;
	}

	/** Icerik icine elle yazilmis <img> adreslerini de kapsar. */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || '' === $this->baseurl || false === strpos( $html, $this->baseurl ) ) {
			return $html;
		}
		$quoted = preg_quote( $this->baseurl, '#' );

		return preg_replace_callback(
			'#' . $quoted . '[^"\'\s\)]+?\.(?:jpe?g|png)#i',
			function ( $m ) {
				return $this->filter_url( $m[0] );
			},
			$html
		);
	}
}

new FD_WebP_Rewrite();
