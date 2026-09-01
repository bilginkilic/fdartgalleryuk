<?php
/**
 * Plugin Name: FD Lead Capture (chestnyznak)
 * Description: Ana sayfadaki hero formunu WordPress'e kaydeder, e-posta bildirimi
 *              gonderir ve Telegram aktarimini SUNUCU tarafinda yapar.
 * Version:     1.0
 *
 * NEDEN VAR (bkz. CLAUDE.md 5n):
 * Onceki tasarimda form dogrudan tarayicidan bir Google Apps Script'e POST
 * ediyordu ve baska hicbir yere yazilmiyordu. Uc sorun vardi:
 *   1. Relay basarisiz olsa bile ziyaretciye "basarili" ekrani gosteriliyordu
 *      -> sessiz lead kaybi.
 *   2. Basvuru hicbir yerde saklanmiyordu; Telegram tek kayitti.
 *   3. `chat_id` sayfa kaynaginda ACIKTI; isteyen o gruba mesaj bastirabilirdi.
 *      Ayrica dev ile canli ayni gruba yaziyordu.
 *
 * Bu eklenti ucunu de kapatir: once VERITABANINA yazar (kalici kayit), sonra
 * e-posta dener, en son Telegram'a aktarir. Ziyaretciye "basarili" demek icin
 * olcut yalnizca veritabani kaydidir.
 *
 * YALNIZCA chestnyznak sitelerine kurulur (canli + dev).
 *
 * wp-config.php'de tanimlanacaklar (repoya YAZILMAZ):
 *   define( 'FD_LEAD_RELAY_URL', 'https://script.google.com/macros/s/.../exec' );
 *   define( 'FD_LEAD_CHAT_ID',   '-100...' );   // BOS BIRAKILIRSA aktarim yapilmaz
 *   define( 'FD_LEAD_NOTIFY_EMAIL', 'info@ornek.com' ); // yoksa admin_email
 *   define( 'FD_LEAD_NOTIFY_CC',    'ikinci@ornek.com' ); // istege bagli kopya
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FD_LEAD_CPT      = 'fd_lead';
const FD_LEAD_MAX_HOUR = 8;      // ayni IP'den saatte en fazla kayit

/* -------------------------------------------------------------------------
 * 1) Basvurulari tutacak icerik tipi — panelde gorunur, aranabilir.
 * ---------------------------------------------------------------------- */
add_action(
	'init',
	function () {
		register_post_type(
			FD_LEAD_CPT,
			[
				'labels'          => [
					'name'          => 'Başvurular',
					'singular_name' => 'Başvuru',
					'menu_name'     => 'Başvurular',
				],
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email-alt',
				'menu_position'   => 26,
				'supports'        => [ 'title', 'editor', 'custom-fields' ],
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'has_archive'     => false,
				'rewrite'         => false,
				'exclude_from_search' => true,
			]
		);
	}
);

/* -------------------------------------------------------------------------
 * 2) Genel uc: POST /wp-json/fd-lead/v1/submit
 * ---------------------------------------------------------------------- */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'fd-lead/v1',
			'/submit',
			[
				'methods'             => 'POST',
				'callback'            => 'fd_lead_handle_submit',
				'permission_callback' => '__return_true', // genel form; koruma asagida
			]
		);
	}
);

function fd_lead_client_ip() {
	// real_ip modulu REMOTE_ADDR'i zaten ziyaretcinin IP'siyle degistiriyor.
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function fd_lead_handle_submit( WP_REST_Request $req ) {
	/* --- bal kupu: gercek ziyaretci bu alani doldurmaz --- */
	if ( trim( (string) $req->get_param( 'website' ) ) !== '' ) {
		// Bota basarili gibi don; israr etmesin.
		return new WP_REST_Response( [ 'stored' => true, 'id' => 0 ], 200 );
	}

	/* --- basit hiz siniri --- */
	$ip  = fd_lead_client_ip();
	$key = 'fd_lead_rl_' . md5( $ip );
	$hit = (int) get_transient( $key );
	if ( $hit >= FD_LEAD_MAX_HOUR ) {
		return new WP_REST_Response(
			[ 'stored' => false, 'error' => 'rate_limited' ],
			429
		);
	}

	/* --- alanlar --- */
	$ad    = sanitize_text_field( (string) $req->get_param( 'adsoyad' ) );
	$posta = sanitize_email( (string) $req->get_param( 'eposta' ) );
	$tel   = sanitize_text_field( (string) $req->get_param( 'tel' ) );
	$cc    = $req->get_param( 'cc' ) === 'ru' ? 'ru' : 'tr';
	$kaynak = esc_url_raw( (string) $req->get_param( 'kaynak' ) );

	$ad    = mb_substr( $ad, 0, 120 );
	$tel   = mb_substr( $tel, 0, 40 );

	if ( $ad === '' || ! is_email( $posta ) || $tel === '' ) {
		return new WP_REST_Response(
			[ 'stored' => false, 'error' => 'invalid_fields' ],
			400
		);
	}

	/* --- 1. ADIM: KALICI KAYIT. Her seyden once bu. --- */
	$onek   = $cc === 'ru' ? '+7' : '+90';
	$post_id = wp_insert_post(
		[
			'post_type'   => FD_LEAD_CPT,
			'post_status' => 'publish',
			'post_title'  => sprintf( '%s — %s %s', $ad, $onek, $tel ),
			'post_content' => sprintf(
				"Ad Soyad / Firma: %s\nE-posta: %s\nTelefon: %s %s\nKaynak: %s",
				$ad,
				$posta,
				$onek,
				$tel,
				$kaynak ? $kaynak : '-'
			),
			'meta_input'  => [
				'_fd_ad'     => $ad,
				'_fd_eposta' => $posta,
				'_fd_tel'    => $onek . ' ' . $tel,
				'_fd_kaynak' => $kaynak,
				'_fd_ip'     => $ip,
				'_fd_ua'     => mb_substr( (string) $req->get_header( 'user_agent' ), 0, 255 ),
			],
		],
		true
	);

	if ( is_wp_error( $post_id ) || ! $post_id ) {
		// Kayit tutmadiysa ziyaretciye BASARILI DEME.
		return new WP_REST_Response(
			[ 'stored' => false, 'error' => 'store_failed' ],
			500
		);
	}

	set_transient( $key, $hit + 1, HOUR_IN_SECONDS );

	$staging = defined( 'FD_STAGING' ) || function_exists( 'fd_staging_guard_active' )
		|| file_exists( WPMU_PLUGIN_DIR . '/fd-staging-guard.php' );

	$metin = sprintf(
		"%s🟡 YENİ HERO BAŞVURUSU (Ana sayfa)\n👤 Ad: %s\n✉️ E-posta: %s\n📞 Telefon: %s %s\n🎁 Kupon: SWORD5000 (5.000 ₺)\n📋 Kontrol listesi talebi: Evet\n🔗 Kayıt: %s",
		$staging ? "🧪 DENEME ORTAMI — gerçek başvuru değildir\n" : '',
		$ad,
		$posta,
		$onek,
		$tel,
		admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	);

	/* --- 2. ADIM: e-posta bildirimi ---
	 * Alici ve kopya wp-config'den gelir. `FD_LEAD_NOTIFY_EMAIL` tanimsizsa
	 * `admin_email`'e duser — ama admin adresi ekip kutusu DEGIL (kullanici
	 * karari: yonetici adresi kisisel kalir), bu yuzden ikisi de tanimlanmali.
	 */
	$alici = defined( 'FD_LEAD_NOTIFY_EMAIL' ) && FD_LEAD_NOTIFY_EMAIL
		? FD_LEAD_NOTIFY_EMAIL
		: get_option( 'admin_email' );

	$basliklar = [ 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $posta ];
	if ( defined( 'FD_LEAD_NOTIFY_CC' ) && FD_LEAD_NOTIFY_CC ) {
		$basliklar[] = 'Cc: ' . FD_LEAD_NOTIFY_CC;
	}

	$mail_ok = (bool) wp_mail( $alici, 'Yeni başvuru: ' . $ad, $metin, $basliklar );
	update_post_meta( $post_id, '_fd_mail', $mail_ok ? 'gonderildi' : 'BASARISIZ' );

	/* --- 3. ADIM: Telegram aktarimi (sunucu tarafinda) --- */
	// DIKKAT: Google Apps Script basarili POST'a 302 doner ve yaniti
	// script.googleusercontent.com'a yonlendirir. O adres yalnizca GET kabul
	// ettigi icin yonlendirme TAKIP EDILIRSE 405/400 gorunur ve mesaj
	// gitmis olmasina ragmen "basarisiz" saniliriz. Bu yuzden
	// redirection = 0 ve 302 BASARI sayilir.
	$relay = 'atlandi';
	if ( defined( 'FD_LEAD_RELAY_URL' ) && FD_LEAD_RELAY_URL
		&& defined( 'FD_LEAD_CHAT_ID' ) && FD_LEAD_CHAT_ID ) {
		$yanit = wp_remote_post(
			FD_LEAD_RELAY_URL,
			[
				'timeout'     => 10,
				'redirection' => 0,
				'body'        => [ 'chat_id' => FD_LEAD_CHAT_ID, 'text' => $metin ],
			]
		);
		if ( is_wp_error( $yanit ) ) {
			$relay = 'basarisiz: ' . $yanit->get_error_message();
		} else {
			$kod   = (int) wp_remote_retrieve_response_code( $yanit );
			$relay = in_array( $kod, [ 200, 201, 302 ], true )
				? 'gonderildi (' . $kod . ')'
				: 'BASARISIZ (kod ' . $kod . ')';
		}
	}
	update_post_meta( $post_id, '_fd_relay', $relay );

	return new WP_REST_Response(
		[ 'stored' => true, 'id' => $post_id, 'mail' => $mail_ok, 'relay' => $relay ],
		200
	);
}

/* -------------------------------------------------------------------------
 * 3) Panelde islevsel sutunlar
 * ---------------------------------------------------------------------- */
add_filter(
	'manage_' . FD_LEAD_CPT . '_posts_columns',
	function ( $c ) {
		return [
			'cb'        => $c['cb'],
			'title'     => 'Başvuran',
			'fd_eposta' => 'E-posta',
			'fd_tel'    => 'Telefon',
			'fd_mail'   => 'E-posta bildirimi',
			'fd_relay'  => 'Telegram',
			'date'      => 'Tarih',
		];
	}
);
add_action(
	'manage_' . FD_LEAD_CPT . '_posts_custom_column',
	function ( $col, $post_id ) {
		$harita = [
			'fd_eposta' => '_fd_eposta',
			'fd_tel'    => '_fd_tel',
			'fd_mail'   => '_fd_mail',
			'fd_relay'  => '_fd_relay',
		];
		if ( isset( $harita[ $col ] ) ) {
			echo esc_html( (string) get_post_meta( $post_id, $harita[ $col ], true ) );
		}
	},
	10,
	2
);
