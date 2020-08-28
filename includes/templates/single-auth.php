<?php

namespace RRZE\RSVP;

defined('ABSPATH') || exit;

$idm = new IdM;
$template = new Template;

$roomId = isset($_GET['room_id']) ? absint($_GET['room_id']) : null;
$room = $roomId ? sprintf('?room_id=%d', $roomId) : '';
$seat = isset($_GET['seat_id']) ? sprintf('&seat_id=%d', absint($_GET['seat_id'])) : '';
$bookingDate = isset($_GET['bookingdate']) ? sprintf('&bookingdate=%s', sanitize_text_field($_GET['bookingdate'])) : '';
$timeslot = isset($_GET['timeslot']) ? sprintf('&timeslot=%s', sanitize_text_field($_GET['timeslot'])) : '';
$nonce = isset($_GET['nonce']) ? sprintf('&nonce=%s', sanitize_text_field($_GET['nonce'])) : '';        

if ($idm->simplesamlAuth() && $idm->simplesamlAuth->isAuthenticated()) {
    $redirectUrl = sprintf('%s/%s%s%s%s%s', get_permalink(), $room, $seat, $bookingDate, $timeslot, $nonce);
    wp_redirect($redirectUrl);
    exit;
}

$data = [];
if ($idm->simplesamlAuth()) {
    $loginUrl = $idm->simplesamlAuth->getLoginURL();
    $data['title'] = __('Authentication Required', 'rrze-rsvp');
    $data['please_login'] = sprintf(__('<a href="%s">Please login with your IdM username</a>.', 'rrze-rsvp'), $loginUrl);
} else {
    header('HTTP/1.0 403 Forbidden');
    wp_redirect(get_site_url());
    exit;
}

get_header();

/*
 * div-/Seitenstruktur für FAU- und andere Themes
 */
if (Helper::isFauTheme()) {
    get_template_part('template-parts/hero', 'small');
    $divOpen = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                    <main id="droppoint">
                        <h1 class="screen-reader-text">' . get_the_title() . '</h1>
                        <div class="inline-box">
                            <div class="content-inline">';
    $divClose = '</div>
                        </div>
                    </main>
                </div>
            </div>
        </div>
    </div>';
} else {
    $divOpen = '<div id="content">
        <div class="container">
            <div class="row">
                <div class="col-xs-12">
                <h1 class="entry-title">' . get_the_title() . '</h1>';
    $divClose = '</div>
            </div>
        </div>
    </div>';
}


/*
 * Eigentlicher Content
 */
echo $divOpen;

echo $template->getContent('auth/require-sso-auth', $data);

echo $divClose;

wp_enqueue_style('rrze-rsvp-shortcode');

get_footer();