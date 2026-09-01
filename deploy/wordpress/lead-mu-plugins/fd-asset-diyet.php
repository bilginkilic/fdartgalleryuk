<?php
/**
 * Plugin Name: FD Varlik Diyeti (chestnyznak)
 * Description: Sayfada KULLANILMAYAN CSS/JS dosyalarini kuyruktan cikarir.
 * Version:     1.0
 *
 * NEDEN VAR (bkz. CLAUDE.md 6j):
 * chestnyznak ana sayfasi 89 harici varlik istegi yapiyordu. Sunucu hizli
 * (TTFB 26 ms), darbogaz istek SAYISI. Eklenti sadelestirmesi (24 -> 20 aktif)
 * bunu 81'e indirdi; geri kalan israfin bir kismi AKTIF ve GEREKLI eklentilerin
 * her sayfaya kosulsuz varlik basmasindan geliyor.
 *
 * KURAL: burada yalnizca "o sayfada karsiligi OLMAYAN" dosyalar cikarilir.
 * Kosul saglanmiyorsa dosya aynen kalir — yani hata durumunda eksik degil,
 * fazla yuklenir. Yeni bir kural eklenmeden once sayfanin HTML'inde o
 * bilesenin izi ARANIR (CLAUDE.md 6e: "kullaniliyor mu?" taramasi yaniltir).
 *
 * MEVCUT KURALLAR
 *   1. mediaelement (3 JS + 2 CSS)  -> sayfada ses/video oynatici yoksa
 *   2. Site Kit CF7 olay saglayici  -> sayfada Contact Form 7 formu yoksa
 *   3. WooCommerce sourcebuster     -> magaza/sepet/hesap sayfasi degilse
 *
 * Ayrica mediaelement'in ~2 KB'lik `mejs` ceviri blogu da inline basilmaz.
 *
 * DENENIP VAZGECILEN: `akismet-frontend.js`. "Yorum formu yoksa cikar" diye
 * dusunuldu; ama Akismet ana sayfada da alan basiyor — altbilgideki
 * FluentForm bultene bal kupu ekliyor ve o alani BU JS dolduruyor. Cikarilsa
 * mesru abonelikler spam sayilirdi. Tek dosya; birakildi.
 *
 * NOT: `easy-code-manager` icindeki iki eski snippet (revslider dequeue +
 * WooCommerce tag filtresi) hala calisiyor; bu dosya onlarin YERINE gecmez,
 * onlarin dokunmadigi handle'lari toplar.
 *
 * YALNIZCA chestnyznak sitelerine kurulur (canli + dev).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gecerli sayfanin metnini (icerik + Elementor verisi) tek dize olarak dondurur.
 * Sonuc istek boyunca onbelleklenir.
 */
function fd_diyet_sayfa_metni() {
	static $metin = null;
	if ( null !== $metin ) {
		return $metin;
	}
	$metin = '';
	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( $id ) {
			$p = get_post( $id );
			if ( $p ) {
				$metin = (string) $p->post_content;
			}
			$el = get_post_meta( $id, '_elementor_data', true );
			if ( $el ) {
				$metin .= is_string( $el ) ? $el : wp_json_encode( $el );
			}
		}
	}
	return $metin;
}

/** Sayfada ses/video oynatici var mi? */
function fd_diyet_oynatici_var() {
	$m = fd_diyet_sayfa_metni();
	if ( '' === $m ) {
		// Arsiv/magaza gibi cok kayitli sayfalarda emin olamayiz -> DOKUNMA.
		return ! is_singular();
	}
	return (bool) preg_match( '/\[(audio|video|playlist)[\s\]]|<(audio|video)[\s>]|wp-(video|audio)-shortcode|mejs__/i', $m );
}

/** Sayfada Contact Form 7 formu var mi? */
function fd_diyet_cf7_var() {
	$m = fd_diyet_sayfa_metni();
	if ( '' === $m ) {
		return ! is_singular();
	}
	return (bool) preg_match( '/\[contact-form-7|wpcf7-form|contact_form_7/i', $m );
}

/** WooCommerce satin alma akisinda miyiz? */
function fd_diyet_woo_akisi() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return false;
	}
	return is_woocommerce() || is_shop() || is_product() || is_cart()
		|| is_checkout() || is_account_page();
}

/**
 * Verilen handle listesini ve src desenlerini kuyruktan cikarir.
 */
function fd_diyet_cikar( array $handles, array $desenler = [] ) {
	global $wp_scripts, $wp_styles;
	foreach ( [ $wp_scripts, $wp_styles ] as $kayit ) {
		if ( empty( $kayit ) || empty( $kayit->registered ) ) {
			continue;
		}
		foreach ( $kayit->registered as $h => $d ) {
			$eslesti = in_array( $h, $handles, true );
			if ( ! $eslesti && $desenler ) {
				$src = isset( $d->src ) ? (string) $d->src : '';
				foreach ( $desenler as $desen ) {
					if ( '' !== $src && false !== stripos( $src, $desen ) ) {
						$eslesti = true;
						break;
					}
				}
			}
			if ( $eslesti ) {
				wp_dequeue_script( $h );
				wp_dequeue_style( $h );
			}
		}
	}
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}

		// 1) mediaelement — sayfada oynatici yoksa 5 dosya bosuna iniyor.
		if ( ! fd_diyet_oynatici_var() ) {
			fd_diyet_cikar(
				[ 'wp-mediaelement', 'mediaelement', 'mediaelement-core', 'mediaelement-migrate', 'mediaelement-vimeo' ],
				[ '/js/mediaelement/' ]
			);
		}

		// 2) Site Kit'in CF7 olay saglayicisi — sayfada CF7 formu yoksa gereksiz.
		if ( ! fd_diyet_cf7_var() ) {
			fd_diyet_cikar(
				[ 'googlesitekit-events-provider-contact-form-7' ],
				[ 'googlesitekit-events-provider-contact-form-7' ]
			);
		}

		// 3) WooCommerce siparis kaynagi izleme — satin alma akisi disinda gereksiz.
		// (easy-code-manager'daki eski snippet `woocommerce`/`wc-` onekli
		//  handle'lari suzuyor; bu iki handle o desenlere UYMUYOR.)
		if ( ! fd_diyet_woo_akisi() ) {
			fd_diyet_cikar(
				[ 'sourcebuster-js', 'wc-order-attribution-inputs' ],
				[ '/sourcebuster/' ]
			);
		}
	},
	100000
);
