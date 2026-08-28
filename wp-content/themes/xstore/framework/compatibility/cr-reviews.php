<?php
add_filter('etheme_comment_reviews_images', function ($comment_text, $comment_id, $comment) {
    ob_start();
        do_action( 'cr_review_after_comment_text', $comment );
    $comment_images = ob_get_clean();
    return $comment_text . $comment_images;
}, 10, 3);