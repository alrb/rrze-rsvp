<?php

namespace RRZE\RSVP\Shortcodes;

use RRZE\RSVP\Functions;
use RRZE\RSVP\Helper;
use function RRZE\RSVP\Config\getShortcodeSettings;
use function RRZE\RSVP\Config\getShortcodeDefaults;



defined('ABSPATH') || exit;

/**
 * Define Shortcode Bookings
 */
class Availability extends Shortcodes {
    protected $pluginFile;
    private $settings = '';
    private $shortcodesettings = '';

    public function __construct($pluginFile, $settings) {
        $this->pluginFile = $pluginFile;
        $this->settings = $settings;
        $this->shortcodesettings = getShortcodeSettings();
        $this->options = (object) $settings->getOptions();
    }


    public function onLoaded() {

        add_shortcode('rsvp-availability', [$this, 'shortcodeAvailability'], 10, 2);

    }

    public function shortcodeAvailability($atts, $content = '', $tag) {
        $shortcode_atts = parent::shortcodeAtts($atts, $tag, $this->shortcodesettings);
        $output = '';
        $today = date('Y-m-d');
        $booking_link = (isset($shortcode_atts['booking_link']) && $shortcode_atts['booking_link'] == 'true');
        $days = sanitize_text_field($shortcode_atts['days']); // kann today, tomorrow oder eine Zahl sein (kommende X Tage)

        if (isset($shortcode_atts['room']) && $shortcode_atts['room'] != '') {
            $room = (int)$shortcode_atts['room'];
            $availability = Functions::getRoomAvailability($room, $today, date('Y-m-d', strtotime($today. ' +'.$days.' days')));

            $output .= '<table>';
            $output .= '<tr>'
                . '<th scope="col" width="200">' . __('Date/Time', 'rrze-rsvp') . '</th>'
                . '<th scope="col">' . __('Seats available', 'rrze-rsvp') . '</th>';
            foreach ($availability as $date => $timeslot) {
                foreach ($timeslot as $time => $seat_ids) {
                    $seat_names = [];
                    $date_formatted = date_i18n('d.m.Y', strtotime($date));
                    $seat_names_raw = [];
                    foreach ($seat_ids as $seat_id) {
                        $seat_names_raw[$seat_id] = get_the_title($seat_id);
                    }
                    asort($seat_names_raw);
                    foreach ($seat_names_raw as $seat_id => $seat_name) {
                        $booking_link_open = '';
                        $booking_link_close = '';
                        if ($booking_link && $this->options->general_booking_page != '') {
                            $permalink = get_permalink($this->options->general_booking_page);
                            $booking_link_open = "<a href=\"$permalink?room_id=$room&seat_id=$seat_id&bookingdate=$date&timeslot=$time\" title='" . __('Book this seat/timeslot now','rrze-rsvp') . "'>";
                            $booking_link_close = '</a>';
                        }
                        $seat_names[] = $booking_link_open . $seat_name . $booking_link_close;
                    }

                    $output .= '<tr>'
                        . '<td>' . $date_formatted . ' &nbsp;&nbsp; ' . $time . '</td>';
                    $output .= '<td>' . implode(', ', $seat_names) . '</td>';
                    $output .= '</tr>';
                }
            }
            $output .= '</table>';
        } elseif (isset($shortcode_atts['seat']) && $shortcode_atts['seat'] != '') {
            $seat = sanitize_title($shortcode_atts['seat']);
            // Seat-ID über Slug
            $seat_post = get_posts([
                'name'        => $seat,
                'post_type'   => 'seat',
                'post_status' => 'publish',
                'posts_per_page ' => '1',
            ]);
            if (!empty($seat_post)) {
                $seat_id = $seat_post[0]->ID;
            } else {
                // Fallback: Seat = ID eingegeben?
                $seat_post = get_post($seat);
                if (!empty($seat_post)) {
                    $seat_id = $seat;
                } else {
                    return __( 'Please enter a valid seat slug or ID', 'rrze-rsvp' );;
                }
            }
            $room_id = get_post_meta($seat_id, 'rrze-rsvp-seat-room', true);

            $availability = Functions::getSeatAvailability($seat_id, $today, date('Y-m-d', strtotime($today. ' +'.$days.' days')));

            if (empty($availability)) {
                return __( 'No timeslots available for this seat.', 'rrze-rsvp' );
            } else {
                $output .= '<div class="rrze-rsvp">'
                    . '<table class="seat-availability">'
                    . '<th scope="col" width="200">' . __('Date', 'rrze-rsvp') . '</th>'
                    . '<th scope="col">' . __('Available Time Slots', 'rrze-rsvp') . '</th>';
                foreach ($availability as $date => $timeslots) {
                    $time_output = [];
                    foreach ($timeslots as $time) {
                        $booking_link_open = '';
                        $booking_link_close = '';
                        if ( $booking_link && $this->options->general_booking_page != '' ) {
                            $permalink = get_permalink( $this->options->general_booking_page );
                            $booking_link_open = "<a href=\"$permalink?room_id=$room_id&seat_id=$seat_id&bookingdate=$date&timeslot=$time\" title='" . __( 'Book this seat/timeslot now', 'rrze-rsvp' ) . "'>";
                            $booking_link_close = '</a>';
                        }
                        $time_output[] = $booking_link_open . $time . $booking_link_close;
                    }
                    $output .= '<tr>'
                        .'<td>' . $date_formatted = date_i18n('d.m.Y', strtotime($date)) . '</td>'
                        . '<td>' . implode(', ', $time_output) . '</td>'
                        . '</tr>';
                }
                $output .= '</table>'
                    . '</div>';
            }
        } else {
            return __( 'Please specify a room ID in your Shortcode.', 'rrze-rsvp' );
        }


        wp_enqueue_style('rrze-rsvp-shortcode');
        //wp_enqueue_script('rrze-rsvp-shortcode');

        return $output;
    }


}
