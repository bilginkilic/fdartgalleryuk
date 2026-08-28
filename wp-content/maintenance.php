<?php

//  ATTENTION!
//
//  DO NOT MODIFY THIS FILE BECAUSE IT WAS GENERATED AUTOMATICALLY,
//  SO ALL YOUR CHANGES WILL BE LOST THE NEXT TIME THE FILE IS GENERATED.
//  IF YOU REQUIRE TO APPLY CUSTOM MODIFICATIONS, PERFORM THEM IN THE FOLLOWING FILE:
//  /var/www/vhosts/fdartgallery.com/public_html/wp-content/maintenance/template.phtml


$protocol = $_SERVER['SERVER_PROTOCOL'];
if ('HTTP/1.1' != $protocol && 'HTTP/1.0' != $protocol) {
    $protocol = 'HTTP/1.0';
}

header("{$protocol} 503 Service Unavailable", true, 503);
header('Content-Type: text/html; charset=utf-8');
header('Retry-After: 600');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <link rel="icon" href="https://fdartgallery.com/wp-content/uploads/2026/01/WhatsApp-Image-2026-01-11-at-23.24.31-100x100.jpeg">
    <link rel="stylesheet" href="https://fdartgallery.com/wp-content/maintenance/assets/styles.css?1787188517">
    <script src="https://fdartgallery.com/wp-content/maintenance/assets/timer.js?1787188517"></script>
    <title>Planlı Bakım</title>
    <style>body {background-image: url("https://fdartgallery.com/wp-content/maintenance/assets/images/bg.jpg?1787188517");}</style>
</head>

<body>

    <div class="container">

    <header class="header">
        <h1>Web sitesi, planlı bakımdan geçiriliyor.</h1>
        <h2>Verdiğimiz rahatsızlıktan dolayı özür dileriz. Biraz sonra geri gelin, yakında hazır olacağız!</h2>
    </header>

    <!--START_TIMER_BLOCK-->
        <!--END_TIMER_BLOCK-->

    <!--START_SOCIAL_LINKS_BLOCK-->
    <section class="social-links">
                    <a class="social-links__link" href="https://www.facebook.com/Plesk" target="_blank" title="Facebook">
                <span class="icon"><img src="https://fdartgallery.com/wp-content/maintenance/assets/images/facebook.svg?1787188517" alt="Facebook"></span>
            </a>
                    <a class="social-links__link" href="https://x.com/Plesk" target="_blank" title="Twitter">
                <span class="icon"><img src="https://fdartgallery.com/wp-content/maintenance/assets/images/twitter.svg?1787188517" alt="Twitter"></span>
            </a>
            </section>
    <!--END_SOCIAL_LINKS_BLOCK-->

</div>

<footer class="footer">
    <div class="footer__content">
        WP Araç Kiti tarafından desteklenmektedir    </div>
</footer>

</body>
</html>
