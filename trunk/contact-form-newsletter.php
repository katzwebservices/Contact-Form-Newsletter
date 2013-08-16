<?php
/*
Plugin Name: Fast Secure Contact Form Newsletter Add-on
Plugin URI: http://www.katzwebservices.com
Description: Integrate Constant Contact with Fast Secure Contact Form
Version: 2.0.1
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

    /**
     * Add actions to load the plugin
     */
    public function __construct() {
        self::$path = plugin_dir_path( __FILE__ );

        /**
         * If the server doesn't support PHP 5.3, sorry, but you're outta luck.
         */
        if(version_compare(phpversion(), '5.1.3') <= 0) {
            add_action('fsctf_newsletter_tab', array(&$this, 'compatibilityNotice'));
            add_action('admin_notices', array(&$this, 'compatibilityNotice'), 30);
            return;
        }

        require_once(self::$path."ctct_php_library/ConstantContact.php");

        add_action('fsctf_newsletter_tab', array(&$this, 'adminDisplayForm'));
        add_action('admin_init', array(&$this, 'adminProcessSettings'));
        add_action('fsctf_mail_sent', array(&$this, 'pushContact'));

        add_action('plugins_loaded', array(&$this, 'addActionsToV3'));
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
            $ctf_version = FSCF_VERSION;
        } else if(empty($ctf_version) && $use_option) {
            $si_contact_gb = get_option("si_contact_form_gb");
            $ctf_version = isset($si_contact_gb['ctf_version']) ? $si_contact_gb['ctf_version'] : null;
        }

        $ctf_version = floatval($ctf_version);

        return (!empty($ctf_version) && (version_compare($ctf_version, '4') < 0));
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
        $api = self::getAPI();
        $valid = self::validateAPI($api);

     if($this->isV3()) { ?>
            <script>
                jQuery(document).ready(function($) {
                    $('#sicf_ctct_settings').insertAfter($('#vCitaSectionAnchor').next('p.submit'));
                });
            </script>
    <?php } ?>
    <style>
        .block { display: block; }
        #tabs-8 > h3 {display: none; }
        #tabs-8 .fscf_settings_group {
            border: 0;
            padding:0;
            margin-top: 13px;
            background: transparent none;
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
        <div class="form-tab"><?php _e('Constant Contact Account Settings', 'si-contact-form-newsletter'); ?></div>
        <div class="clear"></div>
        <form id="ctf_form_settings" action="<?php echo add_query_arg(array('fscf_tab' => 8)); ?>#vCitaSectionAnchor" method="post">
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

    <?php if($valid) { ?>

        <div class="form-tab" style="margin-top:20px;"><?php _e(sprintf('Constant Contact Lists (for Form %s)', self::getFormNumber())); ?></div>
        <div class="clear"></div>
        <form id="ctf_form_lists" action="<?php echo add_query_arg(array('fscf_tab' => 8)); ?>" method="post">
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
        if($option) { return true; }
        try{
            $ContactsCollection = new ContactsCollection($api->CTCTRequest);
            ob_start();
            $response = $api->CTCTRequest->makeRequest($ContactsCollection->uri.'?email=asdasdsasdasdasdasdsadsadasdas@asdmgmsdfdaf.com', 'GET');
            ob_clean();
            $valid = in_array($response['info']['http_code'], array('201', '200', '204')) ? true : false;
        } catch (Exception $e) {
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
            update_option( 'sicf_ctct', array('username' => $_POST['sicf_ctct']['username'], 'password' => $_POST['sicf_ctct']['password']));
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
            echo __("Could not load Constant Contact contact lists. <br/>Error: ", "gravity-forms-constant-contact") . $api->errorMessage;
        }
        else{
            ?>
        <h2><small><?php _e("When this form is submitted, the entry will be added to the following Constant Contact lists:", "gravity-forms-constant-contact"); ?></small></h2>
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
     * Take the posted data and turn it into CTCT Contact-formatted array
     * @see  Contact::__construct()
     * @param  array  $data Form Data
     * @param  array  $post $_POST data
     * @return array       Contact-formatted data
     */
    function generateContactArray(&$data = array(), $post = array()) {

        $fields = array();

        // Process the name and figure out the names for the single input field.
        if(isset($data['from_name'])) {

            // We check what's passed by the $_POST so we can be sure what the exact names are
            $fields['firstName'] = isset($post['f_name']) ? FSCF_Util::clean_input( $post['f_name'] ) : '';
            $fields['middleName'] = isset($post['m_name']) ? FSCF_Util::clean_input( $post['m_name'] ) : (isset($post['mi_name']) ? FSCF_Util::clean_input( $post['mi_name'] ) : '');
            $fields['lastName'] = isset($post['l_name']) ? FSCF_Util::clean_input( $post['l_name'] ) : '';

            // Then we fill in the pieces for what's missing
            $name = explode(' ', $data['from_name']);
            $fields['firstName'] = !empty($fields['firstName']) ? $fields['firstName'] : $name[0];
            if(sizeof($name) === 3) {
                $fields['middleName'] = !empty($fields['middleName']) ? $fields['middleName'] : $name[1];
                $fields['lastName'] = !empty($fields['lastName']) ? $fields['lastName'] : $name[2];
            } elseif(sizeof($name) === 2) {
                $fields['lastName'] = !empty($fields['lastName']) ? $fields['lastName'] : $name[1];
            }
        }

        $fields['emailAddress'] = '';

        if(isset($data['from_email'])) {
            $fields['emailAddress'] = $data['from_email'];
        } elseif(isset($post['email'])) {
            $fields['emailAddress'] = FSCF_Util::clean_input($post['email']);
        }

        $fields['emailAddress'] = is_email( $fields['emailAddress'] ) ? $fields['emailAddress'] : false;

        return $fields;
    }

    /**
     * Once added to FSCF, add the contact to Constant Contact
     * @param  object $fsctf_posted_data Sent form data
     */
    function pushContact(&$fsctf_posted_data) {

        $fields = self::generateContactArray($fsctf_posted_data->posted_data, @$_POST);

        // We need a valid email.
        if(empty($fields['emailAddress'])) {
            return;
        }

        $form_id = isset($_POST['form_id']) ? floatval($_POST['form_id']) : (isset($_POST['si_contact_form_id']) ? floatval($_POST['si_contact_form_id']) : 1);
        $api = self::getAPI();
        $valid = self::validateAPI($api);
        $lists = self::getSetting('lists', $form_id);

        // No Lists Defined.
        if(!$valid || empty($api) || empty($lists)) { return; }

        $retval = self::addUpdateContact($fields, $lists);
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

        #    self::r($ExistingContact, true, 'Existing Contact'); // DEBUG

            // Update the contact
            $updated = $api->updateContact(apply_filters( 'sicf_ctct_existing_contact', $ExistingContact, $fields, $lists));

            // Return on completion
            return $updated;
        }

        // Add the lists as an array item for contact creation.
        $fields['lists'] = (array)$lists;

        // Create a contact object in CTCT
        $Contact = new Contact($fields);

        // Create the contact
        $AddedContact = $api->addContact(apply_filters( 'sicf_ctct_new_contact', $Contact, $fields, $lists));

        # self::r($AddedContact, true, 'Added Contact'); // DEBUG

        return $AddedContact;
    }
}

$FSCF_CTCT = new FSCF_CTCT;

/**
 * Required to trigger tab for FSCF
 * @deprecated
 */
if(!function_exists('sicf_ctct_admin_form')) { function sicf_ctct_admin_form() {} }
