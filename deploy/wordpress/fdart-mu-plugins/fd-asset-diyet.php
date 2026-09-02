<?php
/**
 * Plugin Name: FD Varlik Diyeti (fdartgallery)
 * Description: Sayfada KULLANILMAYAN CSS/JS dosyalarini kuyruktan cikarir.
 * Version:     1.0
 *
 * NEDEN VAR (olculdu 02.09.2026, dev ana sayfasi):
 * 47 script + 32 stylesheet = 79 harici istek, HTML 249 KB. Sunucu darbogaz
 * degil (onbellek isabetinde TTFB 20 ms); darbogaz istek SAYISI.
 *
 * EN BUYUK TEK KALEM — Turnstile'in WooCommerce entegrasyonu:
 * `cfturnstile-woo-js` bagimlilik olarak `wp-data`'yi istiyor. WordPress bu
 * zinciri acinca ana sayfaya react + react-dom + react-jsx-runtime + 12 adet
 * `wp-includes/js/dist/*` dosyasi giriyor — TEK BIR bagimlilik yuzunden 15 istek.
 *
 * ONCE "SCRIPTI CIKARALIM" DENENDI, YANLISTI: XStore temasi her sayfada gizli
 * bir WooCommerce giris/kayit penceresi basiyor (olculdu: ana sayfada
 * `woocommerce-form-login` ve 5 adet `cf-turnstile` alani var). Script
 * cikarilsaydi o formdaki Turnstile dogrulanmaz, giris kirilirdi.
 *
 * DOGRU MUDAHALE: script KALIR, `wp-data` BAGIMLILIGI DUSER. Dosyanin kendisi
 * `wp.data`'yi yalnizca BLOK odeme formunda kullaniyor ve zaten
 * `!document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout')
 *  || typeof wp === 'undefined' || !wp.data` ile erken cikiyor. Sepet/odeme
 * disindaki sayfalarda handle KENDI bagimlilik listemizle ON KAYIT edilir;
 * `WP_Dependencies::add()` mevcut bir handle'i EZMEDIGI icin eklentinin sonraki
 * `wp_enqueue_script()` cagrisi bizim kaydimizi kullanir.
 *
 * KURAL: burada yalnizca "o sayfada karsiligi OLMAYAN" dosyalar cikarilir.
 * Kosul saglanmiyorsa dosya AYNEN kalir — yani hata durumunda eksik degil,
 * fazla yuklenir. Yeni bir kural eklenmeden once sayfanin HTML'inde o
 * bilesenin izi ARANIR (CLAUDE.md 6e: "kullaniliyor mu?" taramasi yaniltir).
 *
 * MEVCUT KURALLAR
 *   0. cfturnstile-woo  -> sepet/odeme disinda `wp-data` bagimliligi dusurulur
 *   1. contact-form-7   -> sayfada CF7 formu yoksa (2 JS + 1 CSS)
 *   2. fluentform widget-> sayfada FluentForm yoksa (1 JS + 1 CSS)
 *   3. comment-reply    -> sayfada yorum formu yoksa
 *   4. iyzico overlay   -> satin alma akisi disinda
 *   5. mediaelement     -> sayfada ses/video oynatici yoksa
 *
 * BILEREK DOKUNULMADI
 *   - `sourcebuster-js` + `wc-order-attribution`: siparis kaynagi izleme, ilk
 *     temasi GIRIS SAYFASINDA kaydeder. Yalnizca sepette yuklemek veriyi
 *     eksiltmez, YANLIS yapar (herkes "direct" gorunur). Kaldirilacaksa
 *     WooCommerce ayarindan ozellik komple kapatilir — bu bir is karari.
 *   - `wc-add-to-cart`, `woocommerce`, `wc-jquery-blockui`, `wc-js-cookie`:
 *     ana sayfada urun izgarasi ve karuseli var, sepete ekleme calisiyor.
 *   - `akismet-frontend.js`: chestnyznak'ta denendi ve VAZGECILDI — Akismet
 *     form bal kupu alanlarini bu JS dolduruyor (bkz. lead-mu-plugins surumu).
 *
 * Hem canli hem dev fdartgallery'ye kurulur.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gecerli sayfanin metnini (icerik + Elementor verisi) tek dize olarak dondurur.
 * Sonuc istek boyunca onbelleklenir.
 *
 * Bos dize "bilmiyorum" demektir — arsiv, magaza, arama gibi cok kayitli
 * sayfalarda icerigi tek metne indirgeyemeyiz. Cagiran taraf bos dizede
 * DOKUNMAMAYI secer.
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

/**
 * Sayfa metninde bir desen var mi?
 *
 * @param string $desen Regex.
 * @param bool   $bilinmiyorsa Metin cozulemiyorsa donecek deger (guvenli taraf).
 */
function fd_diyet_metinde( $desen, $bilinmiyorsa = true ) {
	$m = fd_diyet_sayfa_metni();
	if ( '' === $m ) {
		return $bilinmiyorsa;
	}
	return (bool) preg_match( $desen, $m );
}

/**
 * Elementor sablonlari (baslik/altbilgi/popup) sayfa icerigine DAHIL DEGILDIR.
 * Formlar cogu zaman bir Elementor sablonunda durur; bu yuzden aktif
 * sablonlarin verisi de bir kez taranir ve sonuc onbelleklenir.
 */
function fd_diyet_sablonlarda( $desen ) {
	static $sablon_metni = null;
	if ( null === $sablon_metni ) {
		$sablon_metni = '';
		$ids = get_posts(
			[
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 60,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		foreach ( $ids as $id ) {
			$d = get_post_meta( $id, '_elementor_data', true );
			if ( $d ) {
				$sablon_metni .= is_string( $d ) ? $d : wp_json_encode( $d );
			}
		}
	}
	return (bool) preg_match( $desen, $sablon_metni );
}

/** WooCommerce satin alma / hesap akisinda miyiz? */
function fd_diyet_woo_akisi() {
	if ( ! function_exists( 'is_cart' ) ) {
		return false;
	}
	return is_cart() || is_checkout() || is_account_page();
}

/**
 * Verilen handle listesini ve src desenlerini kuyruktan cikarir.
 * Bagimliliklar ELLE cikarilmaz: WordPress yalnizca kuyruktakilerin
 * bagimliliklarini basar, dolayisiyla zincir kendiliginden duser.
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

/* 0) Turnstile WooCommerce entegrasyonu: script kalir, `wp-data` zinciri duser.
      ON KAYIT oldugu icin eklentinin enqueue'sundan ONCE calismali — bu yuzden
      ayri ve erken (oncelik 5) bir kanca. */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}
		// Blok odeme/sepet yalnizca bu sayfalarda olabilir; orada zincir kalsin.
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			return;
		}
		$eklenti = WP_PLUGIN_DIR . '/simple-cloudflare-turnstile/js/integrations/woocommerce.js';
		if ( ! file_exists( $eklenti ) ) {
			return;
		}
		wp_register_script(
			'cfturnstile-woo-js',
			plugins_url( 'simple-cloudflare-turnstile/js/integrations/woocommerce.js' ),
			[ 'jquery', 'cfturnstile' ],
			'1.9',
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);
	},
	5
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( is_admin() || is_customize_preview() ) {
			return;
		}

		/* 1) Contact Form 7 — sitede yalnizca TEK yayinlanmis sayfa kullaniyor
		      (olculdu: digerlerinin hepsi revizyon). */
		if ( ! fd_diyet_metinde( '/\[contact-form-7|wpcf7-form|"shortcode":"\[contact-form-7/i' )
			&& ! fd_diyet_sablonlarda( '/\[contact-form-7|wpcf7/i' ) ) {
			fd_diyet_cikar( [ 'contact-form-7', 'swv' ], [ '/plugins/contact-form-7/' ] );
		}

		/* 2) FluentForm'un Elementor widget varliklari — form yoksa gereksiz. */
		if ( ! fd_diyet_metinde( '/\[fluentform|fluentform_wrap|"widgetType":"fluent[^"]*"/i' )
			&& ! fd_diyet_sablonlarda( '/\[fluentform|"widgetType":"fluent/i' ) ) {
			fd_diyet_cikar(
				[ 'fluentform-elementor', 'fluentform-elementor-widget' ],
				[ 'fluent-forms-elementor-widget' ]
			);
		}

		/* 3) Yorum yaniti — sayfada yorum formu yoksa. */
		if ( ! is_singular() || ! comments_open() || ! get_option( 'thread_comments' ) ) {
			wp_dequeue_script( 'comment-reply' );
		}

		/* 4) iyzico odeme katmani — satin alma akisi disinda gereksiz. */
		if ( ! fd_diyet_woo_akisi() ) {
			fd_diyet_cikar( [ 'iyzico-overlay-script' ], [ 'iyzico-overlay' ] );
		}

		/* 5) mediaelement — sayfada oynatici yoksa 5 dosya bosuna iniyor. */
		if ( ! fd_diyet_metinde( '/\[(audio|video|playlist)[\s\]]|<(audio|video)[\s>]|wp-(video|audio)-shortcode|mejs__/i' ) ) {
			fd_diyet_cikar(
				[ 'wp-mediaelement', 'mediaelement', 'mediaelement-core', 'mediaelement-migrate', 'mediaelement-vimeo' ],
				[ '/js/mediaelement/' ]
			);
		}
	},
	100000
);
