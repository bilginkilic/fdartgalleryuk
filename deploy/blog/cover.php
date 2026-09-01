<?php
/**
 * chestnyznak.com.tr blog kapak gorseli uretici (1200x630, Open Graph olcusu).
 *
 * Mevcut yazilarin kapaklarini birebir taklit eder: koyu lacivert degrade zemin,
 * sol ustte sari rozet, beyaz baslik + sari alt baslik, iki satir aciklama,
 * sari cizgi, marka blogu ve sagda DataMatrix'i andiran dekoratif kare.
 *
 * Kullanim (sunucuda):
 *   php deploy/blog/cover.php \
 *       --title="Rusya'da EDO Nedir?" \
 *       --title2="Chestny Znak'in Gorunmeyen Sarti" \
 *       --desc1="Elektronik belge akisi olmadan devir bildirimi" \
 *       --desc2="tamamlanmaz - 2026 rehberi" \
 *       --seed=rusya-edo-elektronik-belge-akisi \
 *       --out=/tmp/kapak.png
 *
 * Font: temanin Montserrat dosyalari. Baska bir sitede calistirilacaksa
 * --fontdir ile yol verin.
 */

$opt = [
    'title'    => '',
    'title2'   => '',
    'desc1'    => '',
    'desc2'    => '',
    'badge'    => 'RUSYA MARKALAMA REHBERİ',
    'brand'    => 'SWORD BROS.',
    'url'      => 'chestnyznak.com.tr',
    'offices'  => 'Moskova · İstanbul · Podgorica',
    'seed'     => '',
    'out'      => '',
    'fontdir'  => '/var/www/chestnyznak.chemiartclick.uk/public/wp-content/themes/qwery/skins/default/css/font-face/Montserrat',
];

foreach (array_slice($argv, 1) as $arg) {
    if (!preg_match('/^--([a-z0-9]+)=(.*)$/s', $arg, $m)) {
        fwrite(STDERR, "Taninmayan parametre: {$arg}\n");
        exit(1);
    }
    if (!array_key_exists($m[1], $opt)) {
        fwrite(STDERR, "Bilinmeyen secenek: --{$m[1]}\n");
        exit(1);
    }
    $opt[$m[1]] = $m[2];
}

if ($opt['title'] === '' || $opt['out'] === '') {
    fwrite(STDERR, "--title ve --out zorunlu.\n");
    exit(1);
}

$FONT_BOLD    = $opt['fontdir'] . '/montserrat-bold.ttf';
$FONT_REGULAR = $opt['fontdir'] . '/montserrat-regular.ttf';
foreach ([$FONT_BOLD, $FONT_REGULAR] as $f) {
    if (!is_readable($f)) {
        fwrite(STDERR, "Font bulunamadi: {$f}\n(--fontdir ile yolu verin)\n");
        exit(1);
    }
}

const W = 1200;
const H = 630;

$im = imagecreatetruecolor(W, H);
imagealphablending($im, true);
imagesavealpha($im, true);

/* ---------- zemin: yatay degrade + sag alt kose sicak isik ---------- */
// Sol ust #16203a -> sag alt #0d1117, uzerine sagda altin bir hare.
for ($y = 0; $y < H; $y++) {
    for ($x = 0; $x < W; $x += 2) {
        $tx = $x / W;
        $ty = $y / H;
        $t  = ($tx * 0.72) + ($ty * 0.28);

        $r = (int) round(22 + (13 - 22) * $t);
        $g = (int) round(32 + (17 - 32) * $t);
        $b = (int) round(58 + (23 - 58) * $t);

        // Altin hare: (980, 380) merkezli, yumusak dususlu.
        $dx = ($x - 980) / 560.0;
        $dy = ($y - 380) / 430.0;
        $d  = sqrt(($dx * $dx) + ($dy * $dy));
        if ($d < 1.0) {
            $k = (1.0 - $d);
            $k = $k * $k * 0.55;
            $r += (int) round(52 * $k);
            $g += (int) round(42 * $k);
            $b += (int) round(10 * $k);
        }

        $col = imagecolorallocate($im, min($r, 255), min($g, 255), min($b, 255));
        imagesetpixel($im, $x, $y, $col);
        imagesetpixel($im, $x + 1, $y, $col);
    }
}

/* ---------- renkler ---------- */
$WHITE  = imagecolorallocate($im, 255, 255, 255);
$GOLD   = imagecolorallocate($im, 255, 209, 0);
$GREY   = imagecolorallocate($im, 173, 182, 196);
$DIM    = imagecolorallocate($im, 122, 131, 145);
$PILL   = imagecolorallocate($im, 44, 51, 69);
$PANEL  = imagecolorallocate($im, 46, 42, 22);
$BORDER = imagecolorallocate($im, 92, 79, 24);

/* ---------- yardimcilar ---------- */

/** Harf araligi (letter-spacing) ile metin yazar; imagettftext bunu desteklemiyor. */
function text_ls($im, $size, $x, $y, $color, $font, $text, $spacing = 0.0)
{
    if ($spacing == 0.0) {
        imagettftext($im, $size, 0, (int) $x, (int) $y, $color, $font, $text);
        return;
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $cx = (float) $x;
    foreach ($chars as $ch) {
        imagettftext($im, $size, 0, (int) round($cx), (int) $y, $color, $font, $ch);
        $bb = imagettfbbox($size, 0, $font, $ch);
        $cx += ($bb[2] - $bb[0]) + $spacing;
    }
}

/** Metnin piksel genisligi. */
function text_w($size, $font, $text, $spacing = 0.0)
{
    if ($spacing == 0.0) {
        $bb = imagettfbbox($size, 0, $font, $text);
        return $bb[2] - $bb[0];
    }
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $w = 0.0;
    foreach ($chars as $ch) {
        $bb = imagettfbbox($size, 0, $font, $ch);
        $w += ($bb[2] - $bb[0]) + $spacing;
    }
    return (int) round($w - $spacing);
}

/** Verilen genislige sigana kadar punto dusurur. */
function fit_size($startSize, $minSize, $font, $text, $maxW)
{
    for ($s = $startSize; $s >= $minSize; $s--) {
        if (text_w($s, $font, $text) <= $maxW) {
            return $s;
        }
    }
    return $minSize;
}

function rounded_rect($im, $x1, $y1, $x2, $y2, $r, $color)
{
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
    imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
}

/* ---------- sagdaki DataMatrix benzeri kare ---------- */
// Dekoratiftir, okunabilir bir kod DEGILDIR. Desen slug'dan turetilir, yani
// ayni yazi her zaman ayni gorseli uretir (yeniden uretim tekrarlanabilir olsun).
$PX = 866;  $PY = 170;  $PW = 268;  $PH = 272;
rounded_rect($im, $PX, $PY, $PX + $PW, $PY + $PH, 10, $PANEL);
imagesetthickness($im, 1);
imagerectangle($im, $PX, $PY, $PX + $PW, $PY + $PH, $BORDER);

$N   = 21;                       // modul sayisi (kenar basina)
$MOD = 10;                       // modul boyu (px)
$GX  = $PX + (int) (($PW - ($N * $MOD)) / 2);
$GY  = $PY + (int) (($PH - ($N * $MOD)) / 2);

$seed = $opt['seed'] !== '' ? $opt['seed'] : $opt['title'];
mt_srand(crc32($seed));

for ($row = 0; $row < $N; $row++) {
    for ($col = 0; $col < $N; $col++) {
        if ($col === 0) {                       // sol kenar: dolu (finder)
            $on = true;
        } elseif ($row === $N - 1) {            // alt kenar: dolu (finder)
            $on = true;
        } elseif ($row === 0) {                 // ust kenar: sasirtmali (timing)
            $on = ($col % 2 === 0);
        } elseif ($col === $N - 1) {            // sag kenar: sasirtmali (timing)
            $on = ($row % 2 === 1);
        } else {                                // ic alan: seed'e bagli
            $on = (mt_rand(0, 99) < 48);
        }
        if ($on) {
            imagefilledrectangle(
                $im,
                $GX + ($col * $MOD),
                $GY + ($row * $MOD),
                $GX + ($col * $MOD) + $MOD - 2,
                $GY + ($row * $MOD) + $MOD - 2,
                $GOLD
            );
        }
    }
}

/* ---------- sol kolon: metinler ---------- */
$X       = 68;
$MAX_TXT = 760;   // DataMatrix panelinin soluna kadar

// Rozet
$badge   = $opt['badge'];
$bSize   = 13;
$bSpace  = 1.6;
$bW      = text_w($bSize, $FONT_BOLD, $badge, $bSpace);
rounded_rect($im, $X, 74, $X + $bW + 54, 110, 18, $PILL);
text_ls($im, $bSize, $X + 27, 98, $GOLD, $FONT_BOLD, $badge, $bSpace);

// Baslik (sigmazsa punto dusurulur)
$tSize = fit_size(54, 34, $FONT_BOLD, $opt['title'], $MAX_TXT);
imagettftext($im, $tSize, 0, $X, 228, $WHITE, $FONT_BOLD, $opt['title']);

// Alt baslik
if ($opt['title2'] !== '') {
    $t2Size = fit_size(40, 26, $FONT_BOLD, $opt['title2'], $MAX_TXT);
    imagettftext($im, $t2Size, 0, $X, 292, $GOLD, $FONT_BOLD, $opt['title2']);
}

// Aciklama (iki satir)
if ($opt['desc1'] !== '') {
    $dSize = fit_size(21, 16, $FONT_REGULAR, $opt['desc1'], $MAX_TXT);
    imagettftext($im, $dSize, 0, $X, 352, $GREY, $FONT_REGULAR, $opt['desc1']);
}
if ($opt['desc2'] !== '') {
    $dSize = fit_size(21, 16, $FONT_REGULAR, $opt['desc2'], $MAX_TXT);
    imagettftext($im, $dSize, 0, $X, 388, $GREY, $FONT_REGULAR, $opt['desc2']);
}

// Sari cizgi
imagefilledrectangle($im, $X, 432, $X + 122, 437, $GOLD);

// Marka blogu
text_ls($im, 25, $X, 510, $WHITE, $FONT_BOLD, $opt['brand'], 0.6);
imagettftext($im, 18, 0, $X, 549, $GOLD, $FONT_REGULAR, $opt['url']);
imagettftext($im, 15, 0, $X, 582, $DIM, $FONT_REGULAR, $opt['offices']);

/* ---------- kaydet ---------- */
if (!imagepng($im, $opt['out'], 6)) {
    fwrite(STDERR, "Yazilamadi: {$opt['out']}\n");
    exit(1);
}
imagedestroy($im);

printf("Olusturuldu: %s (%d bayt)\n", $opt['out'], filesize($opt['out']));
