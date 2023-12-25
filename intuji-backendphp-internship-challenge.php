<?php
/**
 * Plugin Name: intuji-backendphp-internship-challenge
 * Description: A WordPress plugin to integrate Google Calendar for listing, creating, and deleting events.
 * Version: 1.0
 * Author: John Thapa
 */

 if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/vendor/autoload.php';

class IntujiPractical_JohnTest_GoogleCalendarIntegration {

    protected $client;

    public function __construct() {
        $this->intujiP_JT_initClient();
        $this->intujiP_JT_setupActions();
    }

    private function intujiP_JT_initClient() {

        $clientId       = get_option('intujiP_JT_google_client_id');
        $clientSecret   = get_option('intujiP_JT_google_client_secret');
        $redirectUri    = get_option('intujiP_JT_google_redirect_uri');

        // Provide a default Redirect URI if it's empty
        if (empty($redirectUri)) {
            $redirectUri = home_url('/');
        }

        $this->client = new Google_Client();
        $this->client->setClientId($clientId);
        $this->client->setClientSecret($clientSecret);
        $this->client->setRedirectUri($redirectUri);
        $this->client->addScope(Google_Service_Calendar::CALENDAR);
        $this->client->setAccessType("offline");
    }

    //----------------------------------------------
    // Register plugin wordpress hooks
    //----------------------------------------------
    private function intujiP_JT_setupActions() {
        add_action('init', array($this, 'intujiP_JT_handleOAuthCallback'));
        add_shortcode('intuji_BPIC_google_calendar', array($this, 'intujiP_JT_calendar_shortcode'));
        // list events ajax
        add_action('wp_ajax_list_event', array($this, 'intujiP_JT_listEventAjax'));
        add_action('wp_ajax_nopriv_list_event', array($this, 'intujiP_JT_listEventAjax'));
        // create events ajax
        add_action('wp_ajax_create_event', array($this, 'intujiP_JT_createEventAjax'));
        add_action('wp_ajax_nopriv_create_event', array($this, 'intujiP_JT_createEventAjax'));
        // delete events ajax
        add_action('wp_ajax_delete_event', array($this, 'intujiP_JT_deleteEventAjax'));
        add_action('wp_ajax_nopriv_delete_event', array($this, 'intujiP_JT_deleteEventAjax'));
        // disconnect from google calendar
        add_action('wp_ajax_disconnect_google_calendar', array($this, 'intujiP_JT_disconnectFromGC'));
        add_action('wp_ajax_nopriv_disconnect_google_calendar', array($this, 'intujiP_JT_disconnectFromGC'));
    }

    //------------------------------------------------------
    // Save api token to cookie
    //------------------------------------------------------
    public function intujiP_JT_handleOAuthCallback() {
        if (isset($_GET['code']) && !isset($_COOKIE['google_calendar_access_token'])) {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($_GET['code']);
            // Check if the access token was successfully obtained
            if (!empty($accessToken['access_token'])) {
                // Save the access token to a cookie
                setcookie('google_calendar_access_token', json_encode($accessToken), time() + 3600, '/');
                wp_redirect(get_option('intujiP_JT_google_redirect_uri'));
                exit();
            } else {
                // Handle the case where there was an error
                echo 'Error obtaining the access token: ' . $accessToken['error_description'];
            }                    
        }
    }

    //------------------------------------------------------
    // Get access api token from cookie
    //------------------------------------------------------
    private function intujiP_JT_getAccessTokenFromCookie() {
        if (isset($_COOKIE['google_calendar_access_token'])) {
            return json_decode(stripslashes($_COOKIE['google_calendar_access_token']), true);
        }
        return null;
    }

    //------------------------------------------------------
    // Validate and refresh api token
    //------------------------------------------------------
    private function intujiP_JT_validateAndRefreshAccessToken() {
        $accessToken = $this->intujiP_JT_getAccessTokenFromCookie();

        if (!$accessToken) {
            return 'Access token not available.';
        }

        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {
            // If the access token is expired, refresh it
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            $newAccessToken = $this->client->getAccessToken();
            setcookie('google_calendar_access_token', json_encode($newAccessToken), time() + 3600, '/');
        }
    }
    
    //------------------------------------------------------
    // Google calendar list, create, delete html shortcode 
    //------------------------------------------------------
    public function intujiP_JT_calendar_shortcode() {
            ob_start(); 
            // Check if the user has connected their Google account
            $accessToken = $this->intujiP_JT_getAccessTokenFromCookie();

            if (!$accessToken) {
                // Provide a link to connect to Google Calendar
                $authUrl = $this->client->createAuthUrl();
                echo '<div class="text-center">
                <a class="btn btn-outline-success" href="' . esc_url($authUrl) . '">Connect with Google Calendar</a>
                </div>';
            } else {
               echo '<div>
                       <button id="create-event" class="btn btn-outline-success mr-2">Create Event</button>
                       <button id="intujiJTGC-disconnect-ac" class="btn btn-outline-danger">Disconnect Account</button>
                       <div id="google-calendar-events" class="list-event mt-3">
                           <div class="loading-overlay"></div>
                           <div id="google-calendar-events-container">
                               ' . $this->intujiP_JT_listEventAjax() . '
                           </div>
                       </div>
                     </div>';
            }

            return ob_get_clean();
    }

    //----------------------------------------
    // List google calendar event
    //----------------------------------------
    public function intujiP_JT_listEventAjax() {
        $this->intujiP_JT_validateAndRefreshAccessToken();

        $service    = new Google_Service_Calendar($this->client);
        $calendarId = 'primary';

        try {
            $optParams = array(
                'maxResults'    => 10,
                'orderBy'       => 'startTime',
                'singleEvents'  => true,
                'timeMin'       => date('c'),
            );
            $results = $service->events->listEvents($calendarId, $optParams);
            $events  = $results->getItems();

            if (empty($events)) {
                $response = 'No upcoming events found.';
            } else {
                $output = '<div class="list-group">';
                foreach ($events as $event) {
                    $start = $event->start->dateTime ?? $event->start->date;
                    $end = $event->end->dateTime ?? $event->end->date;

                    $output .= '<li id="elem-'.$event->id.'" class="list-group-item list-group-item-action">';

                    $output .= '<strong>Summary:</strong> ' . $event->summary . '<br>';
                    
                    if (isset($event->location)) {
                        $output .= '<strong>Location:</strong> ' . $event->location . '<br>';
                    }

                    if (isset($event->description)) {
                        $output .= '<strong>Description:</strong> ' . $event->description . '<br>';
                    }

                    $output .= '<strong>Start Date:</strong> ' . $start . '<br>';
                    $output .= '<strong>End Date:</strong> ' . $end . '<br>';
                    
                    $output .= '<button type="button" class="delete-event btn btn-sm btn-outline-danger float-right" data-eventid='.$event->id.'>Delete</button>';
                    $output .= '</li>';
                }
                $output .= '</ul>';
                $response = $output;
            }

            if (wp_doing_ajax()) {
                wp_send_json_success($response);
            } else {
                return $response;
            }
        } catch (Google\Service\Exception $e) {
            $errorMessage = 'Google Calendar API Error: ' . $e->getMessage();
            if (wp_doing_ajax()) {
                wp_send_json_error($errorMessage);
            } else {
                return $errorMessage;
            }
        }
    }

    //----------------------------------------
    // Create google calendar event
    //----------------------------------------
    public function intujiP_JT_createEventAjax() {
        if (isset($_POST['formData'])) {
            $eventData = $_POST['formData'];

            try {
                $this->intujiP_JT_validateAndRefreshAccessToken();

                $service = new Google_Service_Calendar($this->client);
                $event   = new Google_Service_Calendar_Event($eventData);

                $calendarId = 'primary';
                $event = $service->events->insert($calendarId, $event);

                wp_send_json_success('Event created successfully.', $event);
            } catch (Google\Service\Exception $e) {
                $errorMessage = 'Google Calendar API Error: ' . $e->getMessage();
                wp_send_json_error($errorMessage);
            }
        } else {
            wp_send_json_error('Invalid AJAX request.');
        }
    }

    //----------------------------------------
    // Delete google calendar event
    //----------------------------------------
    public function intujiP_JT_deleteEventAjax() {
        if (isset($_POST['event_id'])) {
            $eventId = sanitize_text_field($_POST['event_id']);
            
            try {
                $this->intujiP_JT_validateAndRefreshAccessToken();

                $service    = new Google_Service_Calendar($this->client);
                $calendarId = 'primary';
                $service->events->delete($calendarId, $eventId);

                wp_send_json_success('Event deleted successfully.');
            } catch (Google\Service\Exception $e) {
                $errorMessage = 'Google Calendar API Error: ' . $e->getMessage();
                wp_send_json_error($errorMessage);
            }
        } else {
            wp_send_json_error('Invalid AJAX request.');
        }
    }

    //------------------------------------------------------
    // Disconnect from Google Calendar
    //------------------------------------------------------
    public function intujiP_JT_disconnectFromGC() {
        // Check if there's an access token stored in the cookie
        if (isset($_COOKIE['google_calendar_access_token'])) {
            $accessToken = json_decode($_COOKIE['google_calendar_access_token'], true);            
            $this->client->revokeToken($accessToken);
            // Delete the access token cookie
            setcookie('google_calendar_access_token', '', time() - 3600, '/');
            wp_send_json_success('Successfully disconnected from Google Calendar.');            
        } else {
            // Handle the case where there's no access token cookie            
            wp_send_json_error('No Google Calendar access token found.');

        }
    }
 

}

$googleCalendarIntegration = new IntujiPractical_JohnTest_GoogleCalendarIntegration();

//----------------------------------------------------
// Enqueue Bootstrap CSS and JS
//----------------------------------------------------
function intujiP_JT_enqueue_styles() {
    wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css', array(), '4.3.1');
    wp_enqueue_style('jquery-confirm-css', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css');
    wp_enqueue_style('my-custom-css', plugins_url('assets/css/custom.css', __FILE__), array(), '1.0');

    wp_enqueue_script('jquery');
    wp_enqueue_script('popper', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js', array('jquery'), '', true);
    wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js', array('jquery', 'popper'), '4.3.1', true);
    wp_enqueue_script('jquery-confirm-js', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js', array('jquery'), '3.3.2', true);
    wp_enqueue_script('my-custom-script', plugins_url('assets/js/custom-script.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('my-custom-script', 'my_ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));
}

add_action('wp_enqueue_scripts', 'intujiP_JT_enqueue_styles');


//----------------------------------------------------
// Save and retrieve options on your settings page
//---------------------------------------------------
function intujiP_JT_settings_page() {
    // Save settings when form is submitted
    if (isset($_POST['submit'])) {
        update_option('intujiP_JT_google_client_id', sanitize_text_field($_POST['intujiP_JT_google_client_id']));
        update_option('intujiP_JT_google_client_secret', sanitize_text_field($_POST['intujiP_JT_google_client_secret']));
        update_option('intujiP_JT_google_redirect_uri', sanitize_text_field($_POST['intujiP_JT_google_redirect_uri']));
    }

    // Display the settings form
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">intuji backendphp internship challenge settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="intujiP_JT_google_client_id">Google Client ID:</label></th>
                    <td><input type="text" class="regular-text" name="intujiP_JT_google_client_id" value="<?php echo get_option('intujiP_JT_google_client_id'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="intujiP_JT_google_client_secret">Google Client Secret:</label></th>
                    <td><input type="text" class="regular-text" name="intujiP_JT_google_client_secret" value="<?php echo get_option('intujiP_JT_google_client_secret'); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="intujiP_JT_google_redirect_uri">Redirect URI:</label></th>
                    <td><input type="text" class="regular-text" name="intujiP_JT_google_redirect_uri" value="<?php echo get_option('intujiP_JT_google_redirect_uri'); ?>" /></td>
                </tr>
            </table>

            <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

// Hook the settings page into the WordPress admin menu
add_action('admin_menu', function () {
    add_menu_page('Your Plugin Settings', 'IntujiJT GC Setting', 'manage_options', 'intuji-jt-gc-settings', 'intujiP_JT_settings_page');
});



