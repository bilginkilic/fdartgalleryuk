<?php
/* B adimi: cz-lead sol kolonunu yerel Elementor widget'larina cevirir,
   form kartini HTML widget olarak birakir. kses KAPALI calisir. */

$POST_ID = 5002;
$dry = (($args[0] ?? '') === 'dry');

kses_remove_filters();   // wp-cli'de kullanici yok -> aksi halde <style>/<script> silinir

$w = get_option('widget_custom_html');
$content = $w[2]['content'] ?? '';
$s = strpos($content, '<!--CZLEAD-START-->');
$e = strpos($content, '<!--CZLEAD-END-->');
if ($s === false || $e === false) { echo "HATA: CZLEAD isaretleri yok\n"; return; }
$e += strlen('<!--CZLEAD-END-->');
$region = substr($content, $s, $e - $s);
$rest   = substr($content, 0, $s) . substr($content, $e);

/* --- lead template'ini bolgeden ayir --- */
$ts = strpos($region, '<template id="cz-lead-tpl">');
$te = strpos($region, '</template>', $ts);
if ($ts === false || $te === false) { echo "HATA: cz-lead-tpl yok\n"; return; }
$tpl    = substr($region, $ts + strlen('<template id="cz-lead-tpl">'), $te - $ts - strlen('<template id="cz-lead-tpl">'));
$assets = substr($region, 0, $ts) . substr($region, $te + strlen('</template>'));

/* --- sol kolon parcalari --- */
$grab = function ($re) use ($tpl) {
    return preg_match($re, $tpl, $m) ? $m[0] : '';
};
$parts = [
    'eyebrow' => $grab('~<div class="cz-lead-eyebrow">.*?</div>~s'),
    'h1'      => $grab('~<h1 class="cz-lead-h">.*?</h1>~s'),
    'sub'     => $grab('~<p class="cz-lead-sub">.*?</p>~s'),
    'perks'   => $grab('~<div class="cz-lead-perks">.*?</span></div>~s'),
    'trust'   => $grab('~<div class="cz-lead-trust">.*?</div>~s'),
    'fn'      => $grab('~<div class="cz-lead-fn">.*?</div>~s'),
];
$fs = strpos($tpl, '<div class="cz-lead-formwrap">');
if ($fs === false) { echo "HATA: formwrap yok\n"; return; }
$form = substr($tpl, $fs);
$form = preg_replace('~</div>\s*</section>\s*$~s', '', $form);   // .cz-lead-in ve section kapanislari

foreach ($parts as $k => $v) { printf("  %-8s %5d bayt %s\n", $k, strlen($v), $v ? '' : '<<< BOS!'); }
printf("  %-8s %5d bayt\n", 'form', strlen($form));
printf("  %-8s %5d bayt (style + 4 template + scriptler)\n", 'assets', strlen($assets));

$balance = function ($h) {
    $d = substr_count($h, '<div') - substr_count($h, '</div>');
    $p = substr_count($h, '<p>') + substr_count($h, '<p ') - substr_count($h, '</p>');
    return $d * 10 + $p;
};
$bad = false;
foreach (array_merge($parts, ['form' => $form]) as $k => $v) {
    if ($v === '') { echo "DURDURULDU: '$k' bulunamadi\n"; $bad = true; continue; }
    $b = $balance($v);
    if ($b !== 0) { echo "DURDURULDU: '$k' etiket dengesi bozuk ($b)\n"; $bad = true; }
}
// parcalar birbirini yutmasin
foreach (['trust','fn'] as $k) {
    if (strpos($parts['perks'], $parts[$k]) !== false) { echo "DURDURULDU: perks '$k' parcasini yutmus\n"; $bad = true; }
}
if ($bad) { return; }
echo "  denge kontrolu: TAMAM\n";

/* --- Elementor koprusu --- */
$bridge = "\n<style>/*cz-elementor-bridge*/\n"
    . "#cz-lead > .elementor-container{max-width:1180px;margin:0 auto;padding:0 22px;align-items:center}\n"
    . "#cz-lead .elementor-widget:not(:last-child){margin-bottom:0}\n"
    . "#cz-lead .elementor-widget-container{margin:0}\n"
    . "#cz-lead .elementor-widget-text-editor{color:inherit}\n"
    . ".cz-lead-assets{display:none!important}\n"
    . "@media(max-width:980px){#cz-lead > .elementor-container{gap:30px}}\n"
    . "</style>\n";

/* --- yeni section --- */
$uid = function () { return substr(md5(uniqid('', true)), 0, 7); };
$text = function ($html, $cls = '') use ($uid) {
    return ['id' => $uid(), 'elType' => 'widget', 'widgetType' => 'text-editor',
            'settings' => array_filter(['editor' => $html, '_css_classes' => $cls]), 'elements' => []];
};
$htmlw = function ($html, $cls = '') use ($uid) {
    return ['id' => $uid(), 'elType' => 'widget', 'widgetType' => 'html',
            'settings' => array_filter(['html' => $html, '_css_classes' => $cls]), 'elements' => []];
};

$hero = [
    'id' => $uid(), 'elType' => 'section',
    'settings' => ['_element_id' => 'cz-lead', 'layout' => 'full_width', 'gap' => 'no',
                   'content_position' => 'middle'],
    'elements' => [
        ['id' => $uid(), 'elType' => 'column',
         'settings' => ['_column_size' => 52, '_inline_size' => 52, '_css_classes' => 'cz-lead-copy'],
         'elements' => [
             $htmlw($assets . $bridge, 'cz-lead-assets'),
             $text($parts['eyebrow']),
             $text($parts['h1']),
             $text($parts['sub']),
             $text($parts['perks']),
             $text($parts['trust']),
             $text($parts['fn']),
         ]],
        ['id' => $uid(), 'elType' => 'column',
         'settings' => ['_column_size' => 48, '_inline_size' => 48],
         'elements' => [ $htmlw($form) ]],
    ],
];

$raw  = get_post_meta($POST_ID, '_elementor_data', true);
$data = json_decode($raw, true);
if (!is_array($data)) { echo "HATA: _elementor_data cozulemedi\n"; return; }

/* section[0] yalnizca olu trx_widget_slider'i tasiyor -> yerine hero */
$first = $data[0]['elements'][0]['elements'][0]['widgetType'] ?? '';
echo "  section[0] icindeki widget: '$first'\n";
if ($first !== 'trx_widget_slider') { echo "DURDURULDU: section[0] beklenen slider degil\n"; return; }
$data[0] = $hero;

if ($dry) { echo "DRY-RUN — yazilmadi.\n"; return; }

update_post_meta($POST_ID, '_elementor_data', wp_slash(wp_json_encode($data)));

/* --- KAYIT SONRASI DOGRULAMA: <style>/<script>/<template> hayatta mi? --- */
$back = get_post_meta($POST_ID, '_elementor_data', true);
$ok = true;
foreach (['<style>' => 1, '<script>' => 1, '<template id=' => 1] as $needle => $_) {
    $n = substr_count($back, str_replace('/', '\\/', $needle)) + substr_count($back, $needle);
    if ($n < 1) { echo "DOGRULAMA BASARISIZ: '$needle' kayitta YOK (kses sildi)\n"; $ok = false; }
    else { echo "  kayitta '$needle': $n\n"; }
}
if (!$ok) {
    update_post_meta($POST_ID, '_elementor_data', wp_slash($raw));   // geri al
    echo "GERI ALINDI — hicbir sey degismedi.\n";
    return;
}

$w[2]['content'] = $rest;
update_option('widget_custom_html', $w);
if (class_exists('\Elementor\Plugin')) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
echo "TAMAM\n";
