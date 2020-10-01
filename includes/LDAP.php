<?php

namespace RRZE\RSVP;

defined('ABSPATH') || exit;

use RRZE\RSVP\Settings;

class LDAP {
    protected $settings;
    protected $server;
    protected $link_identifier = '';
    protected $port;
    protected $distinguished_name;
    protected $bind_base_dn;
    protected $search_base_dn;
    protected $search_filter;

    public function __construct() {
        $this->settings = new Settings(plugin()->getFile());
        $this->server = $this->settings->getOption('ldap', 'server');
        $this->port = $this->settings->getOption('ldap', 'port');
        $this->distinguished_name = $this->settings->getOption('ldap', 'distinguished_name');
        $this->bind_base_dn = $this->settings->getOption('ldap', 'bind_base_dn');
        $this->search_base_dn = $this->settings->getOption('ldap', 'search_base_dn');
    }

    public function onLoaded() {
        add_shortcode('rsvp-ldap-test', [$this, 'ldapTest'], 10, 2);
    }
    
    private function logError(string $method): string{
        $msg = 'LDAP-error ' . ldap_errno($this->link_identifier) . ' ' . ldap_error($this->link_identifier) . " using $method | server = {$this->server}:{$this->port}";
        do_action('rrze.log.error', 'rrze-rsvp : ' . $msg);
        return $msg;
    }

    public function ldapTest($atts, $content = ''){
        if(isset($_POST['username']) && isset($_POST['password'])){
            $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

            $this->link_identifier = ldap_connect($this->server, $this->port);
        
            if (!$this->link_identifier){
                $content = $this->logError('ldap_connect()');
            }else{
                ldap_set_option($this->link_identifier, LDAP_OPT_PROTOCOL_VERSION, 3);
                ldap_set_option($this->link_identifier, LDAP_OPT_REFERRALS, 0);
            
                $bind = @ldap_bind($this->link_identifier, $username . '@' . $this->bind_base_dn, $password);


                if (!$bind) {
                    $content = $this->logError('ldap_bind()');
                }else{

                    $this->search_filter = '(sAMAccountName=' . $username . ')';
                    $result_identifier = @ldap_search($this->link_identifier, $this->search_base_dn, $this->search_filter);
                    
                    if ($result_identifier === false){
                        $content = $this->logError('ldap_search()');
                    }else{
                        $aEntry = @ldap_get_entries($this->link_identifier, $result_identifier);

                        if (isset($aEntry['count']) && $aEntry['count'] > 0){
                            if (isset($aEntry[0]['cn'][0]) && isset($aEntry[0]['mail'][0])){
                                $content = $aEntry[0]['mail'][0]; 
                            }else{
                                $content = $this->logError('ldap_get_entries() : Attributes have changed. Expected $aEntry[0][\'cn\'][0]');
                            }
                        }else{
                            $content = 'User not found';
                        }
                        @ldap_close($this->connection);
                    }
                }
            }
        }else{
            $content = '<form action="#" method="POST">'
                . '<label for="username">Username: </label><input id="username" type="text" name="username" />'
                . '<label for="password">Password: </label><input id="password" type="password" name="password" />'
                . '<input type="submit" name="submit" value="Submit" />'
                . '</form>';
        }
        return $content;   
    } 

    public function tryLogIn(){
        $roomId = isset($_GET['room_id']) ? absint($_GET['room_id']) : null;
        $room = $roomId ? sprintf('&room_id=%d', $roomId) : '';
        $seat = isset($_GET['seat_id']) ? sprintf('&seat_id=%d', absint($_GET['seat_id'])) : '';
        $bookingDate = isset($_GET['bookingdate']) ? sprintf('&bookingdate=%s', sanitize_text_field($_GET['bookingdate'])) : '';
        $timeslot = isset($_GET['timeslot']) ? sprintf('&timeslot=%s', sanitize_text_field($_GET['timeslot'])) : '';
        $nonce = isset($_GET['nonce']) ? sprintf('&nonce=%s', sanitize_text_field($_GET['nonce'])) : '';

        $bookingId = isset($_GET['id']) && !$roomId ? sprintf('&id=%s', absint($_GET['id'])) : '';
        $action = isset($_GET['action']) ? sprintf('&action=%s', sanitize_text_field($_GET['action'])) : '';

        if (!$this->simplesamlAuth->isAuthenticated()) {
            $authNonce = sprintf('?require-ldap-auth=%s', wp_create_nonce('require-ldap-auth'));
            $redirectUrl = sprintf('%s%s%s%s%s%s%s%s%s', trailingslashit(get_permalink()), $authNonce, $bookingId, $action, $room, $seat, $bookingDate, $timeslot, $nonce);
            header('HTTP/1.0 403 Forbidden');
            wp_redirect($redirectUrl);
            exit;
        }

        $this->setAttributes();

        return true;
    }

}