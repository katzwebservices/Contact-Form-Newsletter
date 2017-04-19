<?php
/*
Plugin Name: Fast Secure Contact Form Newsletter Add-on
Plugin URI: http://www.katzwebservices.com
Description: Integrate Constant Contact with Fast Secure Contact Form
Version: 2.1.2
Author: Katz Web Services, Inc.
Author URI: http://www.katzwebservices.com

Copyright 2013 Katz Web Services, Inc.  (email: info@katzwebservices.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class FSCF_CTCT {

    private static $apikey = 'dc584880-333d-4c13-99c3-ac097d633de1'; // Required API Key. Do not change.
    private static $path;
    var $version;

    /**
     * Add actions to load the plugin
     */
    public function __construct() {
        $this->version = '2.1.2';
        self::$path = plugin_dir_path( __FILE__ );

        /**
         * If the server doesn't support PHP 5.3, sorry, but you're outta luck.
         */
        if(version_compare(phpversion(), '5.1.3') <= 0) {
            add_action('fsctf_newsletter_tab', array(&$this, 'compatibilityNotice'));
            add_action('admin_notices', array(&$this, 'compatibilityNotice'), 30);
            do_action( 'fscfctct_event', 'PHP Incompatible: Version '.phpversion());
            return;
        }

        require_once(self::$path."ctct_php_library/ConstantContact.php");

        add_action('fsctf_newsletter_tab', array(&$this, 'adminDisplayForm'));
        add_action('admin_init', array(&$this, 'adminProcessSettings'));
        add_action('fsctf_mail_sent', array(&$this, 'pushContact'));

        add_action('plugins_loaded', array(&$this, 'addActionsToV3'));

        add_action('fs_contact_fields_extra_modifiers', array(&$this, 'output_field_mapping'), 10, 3);
    }

    /**
     * Show error message to < PHP 5.1.3 users
     * @return [type] [description]
     */
    function compatibilityNotice() {
        global $wp_current_filter;

        // Prevent admin notices from running twice.
        if(!self::isV3(true) && in_array('admin_notices', $wp_current_filter)) { return; }

        ?>
        <div class="wrap">
            <div class="inline error" style="border: 1px solid #ccc; margin:10px 0; padding:0 10px; border-radius:5px;">
                <h2 class="cc_logo"><?php _e('Fast Secure Contact Form Newsletter Add-on', 'constant-contact-api'); ?></h2>
                <h3><?php _e('Please upgrade your website\'s PHP Version to Version 5.1.3 or Higher', 'idx-plus'); ?></h3>
                <p><?php _e('Starting with Version 2.0, <strong>the Fast Secure Contact Form Newsletter Add-on requires PHP Version 5.1.3 or higher</strong>. Please contact your hosting provider support and ask them to upgrade your server. We recommend PHP 5.3 or 5.4.', 'constant-contact-api'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Return whether this is FSCF v3 or higher
     * @return boolean True: V3; False: Higher
     */
    function isV3($use_option = false) {
        global $ctf_version;

        if(defined('FSCF_VERSION')) {
            $test_version = FSCF_VERSION;
        } else if(empty($ctf_version) && $use_option) {
            $si_contact_gb = get_option("si_contact_form_gb");
            $test_version = isset($si_contact_gb['ctf_version']) ? $si_contact_gb['ctf_version'] : null;
        }

        $test_version = floatval($ctf_version);

        return (!empty($test_version) && (version_compare($test_version, '4') < 0));
    }

    /**
     * Add the plugin form to V3.x using different hooks than V4.x
     */
    function addActionsToV3() {
        if($this->isV3()) {
            add_action('fsctf_menu_links', array(&$this, 'adminDisplayForm'));
        }
    }

    /**
     * Show the admin form and settings in FSCF tab
     */
    function adminDisplayForm() {

        wp_enqueue_script( 'jquery-ui-tooltip' );

        // Start outputting page.
        flush();

        $api = self::getAPI();
        $valid = self::validateAPI($api);

     if($this->isV3()) { ?>
            <script>
                jQuery(document).ready(function($) {
                    $('#sicf_ctct_settings').insertAfter($('#vCitaSectionAnchor').next('p.submit'));
                });
            </script>
    <?php } ?>

    <script>
        jQuery(document).ready(function($) {
            $('.cc_help').tooltip({
                content: function () {
                    return $(this).prop('title');
                }
            });
        });
    </script>

    <style>
        .cc_help {
            background: #aaa;
            color: white;
            padding: 0 5px;
            border-radius: 100px;
            display: inline-block;
            cursor: help;
            margin: 12px 5px 0 5px;
        }
        .cc_logo_label {
            margin:10px 0;
            padding: 5px 10px 10px;
            border: 1px solid #ddd;
            background: #e9e9e9;
            border-radius: 3px;
            float: left;
        }
        .cc_logo_label span.favicon {
            background: url(<?php echo plugins_url('favicon.png', __FILE__); ?>) left top no-repeat;
            padding: 0 10px 0 21px;
        }
        #optionsform #ui-id-9 {
            background: url(<?php echo plugins_url('favicon.png', __FILE__); ?>) 10px center no-repeat;
            padding-left: 31px;
        }

        .block { display: block; }
        #tabs-9 > h3 { display: none; }
        #tabs-9 .fscf_settings_group {
            border: 0;
            padding:0;
            background: transparent none;
        }
        #sicf_ctct_settings h2 {
            font-size: 20px;
            padding-top: 1em;
            margin-bottom: -.1em;
        }
        #enter-account-details { width:100%; border-top:1px solid #ccc; margin-top:1em; padding-top:.5em; }
        .padding-all { padding: 20px; }
        .padding-all label { font-style: normal;}
        .padding-all input[type=text],
        .padding-all input[type=password] {
            padding:.3em;
            font-size: 1.2em;
            max-width: 400px;
            display: block;
        }
        #optionsform #sicf_ctct_settings fieldset {
            border: 1px solid #ccc;
        }
        .ul-columns {
            columns:15em; -webkit-columns:15em; -moz-columns:15em;
        }
        .ul-columns li {
            -webkit-column-break-inside:avoid;
            -moz-column-break-inside:avoid;
            -o-column-break-inside:avoid;
            -ms-column-break-inside:avoid;
            column-break-inside:avoid;
        }
    </style>
    <div id="sicf_ctct_settings">
        <h2><?php _e('Constant Contact Account Settings', 'si-contact-form-newsletter'); ?></h2>
        <div class="clear"></div>
        <form id="ctf_form_settings" action="<?php echo add_query_arg(array('fscf_tab' => 9)); ?>#vCitaSectionAnchor" method="post">
            <fieldset class="padding-all form-wrap">
                <?php

                $password = self::getSetting('password');
                $username = self::getSetting('username');

                if(is_null($password) && is_null($username)) {
                    echo self::getRemote('http://katz.co/signup/');
                    echo '<h2 class="clear" id="enter-account-details">'.__('Enter your Constant Contact account details below:', 'si-contact-form-newsletter').'</h2>';
                } elseif($valid) {
                    echo "<div id='message' class='updated inline'><p>".__('<strong>Success:</strong> Your Constant Contact username and password are valid.', 'si-contact-form-newsletter')."</p></div>";
                } elseif(is_null($password)) {
                    echo "<div id='message' class='error inline'><p>".__('<strong>Error:</strong> Your Constant Contact password is empty.', 'si-contact-form-newsletter')."</p></div>";
                } elseif(is_null($username)) {
                    echo "<div id='message' class='error inline'><p>".__('<strong>Error:</strong> Your Constant Contact username is empty.', 'si-contact-form-newsletter')."</p></div>";
                } else {
                    echo "<div id='message' class='error inline'><p>".__('<strong>Error:</strong> Your Constant Contact username or password are not correct.', 'si-contact-form-newsletter')."</p></div>";
                }

                self::adminDisplayCTCTForm($api, $valid);
            ?>
                <p class="submit">
                  <input type="submit" class="button button-primary" name="ctf_action" value="<?php _e('Save Constant Contact Settings', 'si-contact-form-newsletter'); ?>" />
                </p>
            </fieldset>
        </form>

    <?php flush(); if($valid) { ?>

        <h2 style="margin-top:20px;"><?php _e(sprintf('Constant Contact Lists (for Form %s)', self::getFormNumber())); ?></h2>
        <div class="clear"></div>
        <form id="ctf_form_lists" action="<?php echo add_query_arg(array('fscf_tab' => 9)); ?>#vCitaSectionAnchor" method="post">
            <fieldset class="padding-all">
            <?php self::adminDisplayFormSettings($api); ?>
            <p class="submit" style="padding:0">
              <input type="submit" class="button button-primary" name="ctf_action" value="<?php _e('Save Form Lists', 'si-contact-form-newsletter'); ?>" />
            </p>
            </fieldset>
        </form>
    <?php
    }
    ?>
    </div>
    <?php
        flush();
    }

    /**
     * Remotely fetch, cache, and display HTML ad for the Fast Secure Contact Form Newsletter plugin addon.
     * for Constant Contact on Newsletter tab
     */
     static function getRemote($url = '', $cache = true) {

        // The ad is stored locally for 30 days as a transient. See if it exists.
        $cache = function_exists('get_site_transient') ? get_site_transient(sha1($url)) : get_transient(sha1($url));

        // If it exists, use that (so we save some request time), unless ?cache is set.
        if(!empty($cache) && !isset($_REQUEST['cache'])) { return $cache; }

        // Get the advertisement remotely. An encrypted site identifier, the language of the site, and the version of the FSCF plugin will be sent to katz.co
        $response = wp_remote_get($url, array('timeout' => 45,'body' => array('siteid' => sha1(site_url()), 'language' => get_bloginfo('language'), 'version' => FSCF_VERSION)));

        // If it was a successful request, process it.
        if(!is_wp_error($response)) {

            // Basically, remove <script>, <iframe> and <object> tags for security reasons
            $body = strip_tags(trim(rtrim($response['body'])), '<b><strong><em><i><span><u><ul><li><ol><div><attr><cite><a><style><blockquote><q><p><form><br><meta><option><textarea><input><select><pre><code><s><del><small><table><tbody><tr><th><td><tfoot><thead><u><dl><dd><dt><col><colgroup><fieldset><address><button><aside><article><legend><label><source><kbd><tbody><hr><noscript><link><h1><h2><h3><h4><h5><h6><img>');

            // If the result is empty, cache it for 8 hours. Otherwise, cache it for 30 days.
            $cache_time = empty($response['body']) ? floatval(60*60*8) : floatval(60*60*30);

            if(function_exists('set_site_transient')) {
                set_site_transient(sha1($url), $body, $cache_time);
            } else {
                set_transient(sha1($url), $body, $cache_time);
            }

            // return the results.
            return  $body;
        }

        return '';
    }

    /**
     * Generate a new FSCFConstantContact object
     * @return FSCFConstantContact
     */
    function getAPI() {
        return new FSCFConstantContact('basic', self::$apikey, self::getSetting('username'), self::getSetting('password'));
    }

    /**
     * We have to hack a way to check whether or not the credentials are valid.
     * @param  CC_Superclass $api
     */
    function validateAPI($api) {
        $option = get_option('sicf_ctct_valid');
        if(!empty($option)) { return true; }
        try{
            $ContactsCollection = new CFN_ContactsCollection($api->CTCTRequest);
            ob_start();
            $response = $api->CTCTRequest->makeRequest($ContactsCollection->uri.'?email=asdasdsasdasdasdasdsadsadasdas@asdmgmsdfdaf.com', 'GET');
            ob_clean();
            $valid = in_array($response['info']['http_code'], array('201', '200', '204')) ? true : false;
            if($valid) {
                do_action( 'fscfctct_event', 'Valid Configuration');
            } else {
                do_action( 'fscfctct_event', 'Invalid Configuration');
            }
        } catch (Exception $e) {
            do_action( 'fscfctct_event', 'Exception: '.$e->getMessage());
            $valid = false;
        }

        update_option('sicf_ctct_valid', $valid);

        return $valid;
    }

    /**
     * print_r with pre
     * @param  mixed  $code  Whatever you want to output
     * @param  boolean $die   Die after printing
     * @param  string  $title Give the output a title
     */
    function r($code, $die = false, $title = '') {
        if(!empty($title)) {
            echo "<h3>$title</h3>";
        }
        echo '<pre>';
        echo print_r($code, true);
        echo '</pre>';
        if($die) { die(); }
    }

    /**
     * Get the form-specific settings from the database
     * @param  int|null $form_id The ID of the current form
     * @return array          Form options array
     */
    function formSetting($form_id = null) {
        if(is_null($form_id)) {
            $form_id = self::getFormNumber();
        }
        return get_option("si_contact_form$form_id");
    }

    /**
     * When the form is submitted in admin, save settings.
     */
    function adminProcessSettings() {

        if(isset($_POST['sicf_ctct_list_form'])) {

            check_admin_referer('list_action', 'sicf_ctct_list_form');

            $form_id = self::getFormNumber();

            update_option( 'sicf_ctct_form_'.$form_id, $_POST['sicf_ctct'] );

        } else if(isset($_POST['sicf_ctct_account_form'])) {
            check_admin_referer('account_action', 'sicf_ctct_account_form');
            delete_option('sicf_ctct_valid');
            delete_option('sicf_ctct');
            add_option( 'sicf_ctct', array('username' => $_POST['sicf_ctct']['username'], 'password' => $_POST['sicf_ctct']['password']), '', 'no');
        }
    }

    /**
     * Get settings for the plugin by key. The plugin can then store all settings in one DB entry
     * @param  string $key     the key of the plugin
     * @param  int $form_id If getting a setting for a specific form, pass the ID here
     * @return mixed          The setting you asked for, or if not exists, NULL
     */
    static function getSetting($key, $form_id = null) {
        if(is_null($form_id)) {
            $settings = get_option( 'sicf_ctct');
        } else {
            $settings = get_option( 'sicf_ctct_form_'.$form_id);
        }

        $settings = maybe_unserialize($settings);
        return !empty($settings[$key]) ? maybe_unserialize($settings[$key]) : null;
    }

    /**
     * Show CTCT username & password
     */
    static function adminDisplayCTCTForm() {

        wp_nonce_field('account_action', 'sicf_ctct_account_form');
        ?>
        <p>
            <label for="sicf_ctct_username" class="form-field"><?php _e('Constant Contact Username', 'si-contact-form-newsletter'); ?>
                <input autocomplete="off" name="sicf_ctct[username]" id="sicf_ctct_username" type="text" value="<?php echo self::getSetting('username'); ?>" class="text regular-text" />
            </label>
        </p>
        <p>
            <label for="sicf_ctct_password" class="form-field"><?php _e('Constant Contact Password', 'si-contact-form-newsletter'); ?>
                <input autocomplete="off" name="sicf_ctct[password]" id="sicf_ctct_password" type="password" value="<?php echo self::getSetting('password'); ?>" class="password regular-text" />
            </label>
        </p>
    <?php
    }

    /**
     * Get newsletter lists for CTCT account
     * @param  object $api API settings
     */
    function getAllLists($api) {

        $lists = get_site_transient('sicf_ctct_cc_lists');
        $lists = maybe_unserialize($lists);
        if(!$lists || empty($lists) || isset($_REQUEST['cache']) || isset($_REQUEST['refresh'])) {
            $lists = $this->fetchLists($api);
            set_site_transient('sicf_ctct_cc_lists',maybe_serialize($lists), 60 * 60 * 24);
        }
        return $lists;
    }

    /**
     * Recursive function to get all lists, instead of just 50 returned by CTCT
     * @param  ConstantContact  $api         API object
     * @param  null|string  $page        Recursively define page link
     * @param  array   $results     Pass existing results to add to
     * @return array    Array of lists as `ContactList` objects
     */
    function fetchLists($api, $page=null, &$results = array()) {

        $fetch = $api->getLists($page);

        foreach($fetch['lists'] as $r) {
            $id = preg_replace('/(?:.*?\/lists\/)([0-9+])/', '$1', $r->id);
            $results[$id] = $r;
        }

        if(!empty($fetch['nextLink'])) {
            $this->fetchLists($api, $fetch['nextLink'], $results);
        }

        return $results;
    }

    /**
     * Output per-form settings in FSCF admin settings tab
     * @param  object $api CTCT API
     */
    function adminDisplayFormSettings($api) {
        $form_id = self::getFormNumber();
        $form = self::formSetting($form_id);

        //getting all contact lists
        $lists = self::getAllLists($api);

        // Get the saved lists for this form
        $saved_lists = self::getSetting('lists', $form_id);

        if (empty($lists)){
            echo __("Could not load Constant Contact contact lists. <br/>Error: ", 'si-contact-form-newsletter') . $api->errorMessage;
        }
        else{
            ?>
        <h2><small><?php _e("When this form is submitted, the entry will be added to the following Constant Contact lists:", 'si-contact-form-newsletter'); ?></small></h2>
            <ul class="ul-columns">
                <?php
                $output = '';

                foreach($lists as $listid => $list) {
                    $output .= '
                    <li>
                        <label for="sicf_ctct-list-'.$listid.'">
                            <input type="checkbox" name="sicf_ctct[lists][]" id="sicf_ctct-list-'.$listid.'" value="'.$list->id.'" '.@checked((in_array($list->id, (array)$saved_lists)), true, false).' />
                        '.esc_html($list->name).'
                        </label>
                    </li>';
                }
                echo $output;
                ?>
                </ul>
        <div class="clear"></div>
        <a href="<?php echo add_query_arg(array('refresh' => 1)); ?>" class="alignright button button-secondary button-small" title="<?php _e('If the lists shown are out of date, click this button to refresh the lists.', 'si-contact-form-newsletter'); ?>"><?php _e('Refresh Lists', 'si-contact-form-newsletter'); ?></a>
          <input type="hidden" value="<?php echo $form_id; ?>" name="form_id" />
        <?php
        }

        wp_nonce_field('list_action', 'sicf_ctct_list_form');
    }


    /**
     * Get the current form number in FSCF Admin
     * @return float Number of the form
     */
    function getFormNumber() {
        $form = !empty($_REQUEST['fscf_form']) ? $_REQUEST['fscf_form'] : 1;
        return floatval($form);
    }

    /**
     * Add a dropdown list for v4 field settings where users can map the field to the CTCT values
     * @param  string $field_opt_name The name attribute of the field (`fs_contact_form1[fields][0]`, for example)
     * @param  array $field          The field settings array.
     * @param  integer $key            The key of the field. As seen in `$field_opt_name`
     */
    function output_field_mapping($field_opt_name, $field, $key) {
        // Create an ID attribute
        $field_id = str_replace(array('[', ']'), '_', $field_opt_name).'_ctct';

        $output = '<label class="cc_logo_label" for="'.$field_id.'"><span class="favicon">Constant Contact:</span>';
        $output .= '<select name="'.$field_opt_name.'[ctct]" id="'.$field_id.'">';
        $output .= '<option value="">';
        $output .= __('Choose Constant Contact Field', 'si-contact-form-newsletter');
        $output .= '</option>';

        // Get the fields
        $fields = $this->get_field_list();

        // If these are the pre-defined fields, we force values.
        if( in_array($field['slug'], array('name', 'full_name', 'email')) && !empty($field['standard'])) {

        }

        // Set CTCT field value
        $field_value = @$field['ctct'];

        // Set default values if not set
        if(!isset($field['ctct'])) {
            switch($field['slug']) {
                case 'name': // backward compat with 3.x
                case 'full_name':
                    $field_value = 'fullName';
                    break;
                case 'email':
                    $field_value = 'emailAddress';
                    break;
            }
        }

        // For each CTCT field, create an <option>
        foreach ($fields as $key => $value) {
            $output .= '<option value="'.$key.'"'. selected( $field_value, $key, false ) .'>'.esc_html( $value ).'</option>';
        }
        $output .= '</select>';

        $output .= sprintf('<span class="cc_help" title="%s">?</span>', __('<p><strong class=\'block\'>Map the Field to Constant Contact</strong> Use this drop-down menu to match the current field to a Constant Contact field.</p>', 'si-contact-form-newsletter'));

        $output .= '</label>';

        echo $output;
    }

    /**
     * Get an array of CTCT fields and descriptions (keys are field names, values are descriptions)
     * @return array
     */
    function get_field_list() {
        $fields = array(
            'emailAddress' => __('Email Address (one per form)', 'si-contact-form-newsletter'),
            'fullName' => __('Full Name', 'si-contact-form-newsletter'),
            'firstName' => __('First Name', 'si-contact-form-newsletter'),
            'middleName' => __('Middle Name', 'si-contact-form-newsletter'),
            'lastName' => __('Last Name', 'si-contact-form-newsletter'),
            'jobTitle' => __('Job Title', 'si-contact-form-newsletter'),
            'companyName' => __('Company Name', 'si-contact-form-newsletter'),
            'homePhone' => __('Home Phone', 'si-contact-form-newsletter'),
            'workPhone' => __('Work Phone', 'si-contact-form-newsletter'),
            'addr1' => __('Address 1', 'si-contact-form-newsletter'),
            'addr2' => __('Address 2', 'si-contact-form-newsletter'),
            'addr3' => __('Address 3', 'si-contact-form-newsletter'),
            'city' => __('City', 'si-contact-form-newsletter'),
            'stateCode' => __('State Code (2 letters)', 'si-contact-form-newsletter'),
            'stateName' => __('State Name', 'si-contact-form-newsletter'),
            'countryCode' => __('Country Code (2 letters)', 'si-contact-form-newsletter'),
            'countryName' => __('Country Name', 'si-contact-form-newsletter'),
            'postalCode' => __('Postal Code', 'si-contact-form-newsletter'),
            'subPostalCode' => __('Sub Postal Code', 'si-contact-form-newsletter'),
            'notes' => __('Customer Notes', 'si-contact-form-newsletter'),
            'customField1' => __('Custom Field 1', 'si-contact-form-newsletter'),
            'customField2' => __('Custom Field 2', 'si-contact-form-newsletter'),
            'customField3' => __('Custom Field 3', 'si-contact-form-newsletter'),
            'customField4' => __('Custom Field 4', 'si-contact-form-newsletter'),
            'customField5' => __('Custom Field 5', 'si-contact-form-newsletter'),
            'customField6' => __('Custom Field 6', 'si-contact-form-newsletter'),
            'customField7' => __('Custom Field 7', 'si-contact-form-newsletter'),
            'customField8' => __('Custom Field 8', 'si-contact-form-newsletter'),
            'customField9' => __('Custom Field 9', 'si-contact-form-newsletter'),
            'customField10' => __('Custom Field 10', 'si-contact-form-newsletter'),
            'customField11' => __('Custom Field 11', 'si-contact-form-newsletter'),
            'customField12' => __('Custom Field 12', 'si-contact-form-newsletter'),
            'customField13' => __('Custom Field 13', 'si-contact-form-newsletter'),
            'customField14' => __('Custom Field 14', 'si-contact-form-newsletter'),
            'customField15' => __('Custom Field 15', 'si-contact-form-newsletter'),
        );

        return $fields;
    }

    /**
     * Take the posted data and turn it into CTCT Contact-formatted array
     * @see  Contact::__construct()
     * @param  array  $data Form Data
     * @param  array  $post $_POST data
     * @return array       Contact-formatted data
     */
    function generateContactArray(&$data = array(), $post = array(), $form = array()) {

        $fields = array();

        // Set the email address using posted data
        $fields['emailAddress'] = self::getIfSet($post, 'email');
        $fields['emailAddress'] = $fields['emailAddress'] ? $fields['emailAddress'] : self::getIfSet($data, 'from_email');

        require_once(self::$path."nameparse.php");

        // 1. Parse the from_name parameter for initial data.
        $fields = $this->parseName(@$data['from_name'], $fields);

        // 2. Get name data from the posted data itself
        // The name field is tough. It is not like the other fields.
        // They keys also change from v3 to v4.
        $fields['firstName'] = self::getIfSet($data, 'f_name', 'first_name');
        $fields['middleName'] = self::getIfSet($data, 'm_name', 'middle_name');
        $fields['lastName'] = self::getIfSet($data, 'l_name', 'last_name');

        // If CTCT settings are used, set that data.
        if(!self::isV3(true)) {
            // We cycle through the form fields checking to
            // see if there are ctct settings defined.
            foreach ($form['fields'] as $field) {
                // If the field has CTCT fields mapped, and the data exists
                if(!empty($field['ctct']) && isset($data[$field['slug']]) ) {
                    // We overwrite the existing data, since that should be used instead.
                    $fields[$field['ctct']] = $data[$field['slug']];
                }
            }
        }

        // 3. If the name wasn't already set and the name field exists,
        // we process the name field and figure out the names for the single input name field.
        // We overwrite the data, since the fullName parameter being set means someone wanted
        // it that way (instead of default posted data generation, like used above.)
        if(!empty($fields['fullName'])) {
            $fields = $this->parseName($fields['fullName'], $fields, true);
        }

        // If the email address isn't an email at all, then set it as false.
        // This shouldn't be necessary because of FSCF email verification,
        // but we check just in case it's using a text field or something that isn't validated.
        $fields['emailAddress'] = is_email( $fields['emailAddress'] ) ? $fields['emailAddress'] : false;

        return $fields;
    }

    /**
     * Convert a string to name pieces array
     * @uses ctf_form_parse_name()
     * @param  string  $fullName  Name to break into an array
     * @param  array  $fields    Existing field data
     * @param  boolean $overwrite Overwrite pre-existing data with name parse?
     * @return array             Modified fields
     */
    function parseName($fullName, $fields, $overwrite = false) {
        $name = ctf_form_parse_name($fullName);

        if(isset($name['suffix'])) {
            $name['lastName'] = $name['lastName'].' '.$name['suffix'];
        }
        unset($name['suffix'], $name['title']);

        foreach ($name as $key => $value) {
            if($overwrite) {
                $fields[$key] = $value;
            } else {
                $fields[$key] = !empty($fields[$key]) ? $fields[$key] : $value;
            }
        }

        return $fields;
    }

    /**
     * Check an array for a value at key `$key`. If not set, check array for value at `$backupKey`. If not set, return false. If set, return value.
     * @param  array  $array     Array to check
     * @param  string  $key       Array key to check
     * @param  string $backupKey Also check this key as a backup option
     * @param  boolean $clean     Sanitize the output
     * @return mixed|boolean     If value exists, return value. If not, return false.
     */
    function getIfSet($array, $key, $backupKey = '', $clean = true) {
        if(isset($array[$key])) {
            if($clean) {
                if(class_exists('FSCF_Util')) {
                    return FSCF_Util::clean_input($array[$key]);
                } else {
                    return esc_attr($array[$key]);
                }
            }
            return $array[$key];
        } else {
            // Do recursive check for the backup key
            if(!empty($backupKey)) {
                return self::getIfSet($array, $backupKey, false, $clean);
            }
            return false;
        }
    }

    /**
     * Once added to FSCF, add the contact to Constant Contact
     * @param  object $fsctf_posted_data Sent form data
     */
    function pushContact(&$fsctf_posted_data) {

        // V3 doesn't have this methinks
        if(class_exists('FSCF_Util')) {
            $form = FSCF_Util::get_form_options( $fsctf_posted_data->form_number, false );
        } else {
            $form = array();
        }

        $fields = self::generateContactArray($fsctf_posted_data->posted_data, @$_POST, $form);

        // We need a valid email.
        if(empty($fields['emailAddress'])) { return; }

        $form_id = isset($fsctf_posted_data->form_number) ? $fsctf_posted_data->form_number : false;
        if(!$form_id) {
            $form_id = isset($_POST['form_id']) ? floatval($_POST['form_id']) : (isset($_POST['si_contact_form_id']) ? floatval($_POST['si_contact_form_id']) : 1);
        }

        $api = self::getAPI();
        $valid = self::validateAPI($api);
        $lists = self::getSetting('lists', $form_id);

        // No Lists Defined.
        if(!$valid || empty($api) || empty($lists)) { return; }

        return self::addUpdateContact($fields, $lists);
    }

    /**
     *
     * @filter sicf_ctct_update_existing_contacts Use this filter to turn off updating for existing contacts.
     * @filter  sicf_ctct_update_contacts_method Change how the contacts are updated. Default: `ifempty` updates contact fields only if they are empty. `overwrite` updates all contact fields with the submitted form data.
     * @param array $fields Contact fields
     * @param array $lists  Form lists to be added to
     */
    function addUpdateContact($fields, $lists) {

        $api = self::getAPI();

        // First, check if contact exists
        $ExistingContact = $api->searchContactsByEmail($fields['emailAddress']);

        // searchContacts returns false if none.
        if($ExistingContact && apply_filters( 'sicf_ctct_update_existing_contacts', true )) {

            $ExistingContact = $ExistingContact[0];

            if($ExistingContact->status === 'Do Not Mail') { return false; }

            // Fill in the details
            $ExistingContact = $api->getContactDetails($ExistingContact);

            $updateMethod = apply_filters( 'sicf_ctct_update_contacts_method', 'ifempty' );

            foreach($fields as $key => $field) {

                switch ($updateMethod) {

                    // Update all fields
                    case 'overwrite':
                        $ExistingContact->{$key} = $field;
                        break;

                    // Update fields that are empty (default)
                    case 'ifempty':
                    default:
                        if(empty($ExistingContact->{$key})) { $ExistingContact->{$key} = $field; }
                        break;
                }

            }

            // Get exsting lists
            $existinglists = $ExistingContact->lists;

            // Add new lists to existing lists, but no dupes.
            $lists = array_unique(array_merge($lists, $existinglists));

            // Set the merged lists
            $ExistingContact->lists = $lists;

            // Update the contact
            $updated = $api->updateContact(apply_filters( 'sicf_ctct_existing_contact', $ExistingContact, $fields, $lists));

#            self::r($ExistingContact, true, 'Existing Contact'); // DEBUG

            // Return on completion
            return $updated;
        }

        // Add the lists as an array item for contact creation.
        $fields['lists'] = (array)$lists;

        // Create a contact object in CTCT
        $Contact = new CFN_Contact($fields);

        // Create the contact
        $AddedContact = $api->addContact(apply_filters( 'sicf_ctct_new_contact', $Contact, $fields, $lists));

#       self::r($AddedContact, true, 'Added Contact'); // DEBUG

        return $AddedContact;
    }
}

new FSCF_CTCT;

/**
 * Required to trigger tab for FSCF
 * @deprecated
 */
if(!function_exists('sicf_ctct_admin_form')) { function sicf_ctct_admin_form() {} }
