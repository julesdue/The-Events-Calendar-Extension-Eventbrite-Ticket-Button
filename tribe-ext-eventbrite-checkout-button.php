<?php
/**
 * Plugin Name:       The Events Calendar Extension: Eventbrite Ticket Button
 * Plugin URI:        none
 * Description:       Adds a custom field for an Eventbrite embed code and a button to the event details page in The Events Calendar.
 * Version:           1.3.0
 * Extension Class:   Tribe__Extension__Eventbrite_Ticket_Button
 * Author:            Julian Duenser
 * Author URI:        none
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-eventbrite-checkout-button
 */

// Ensure the Tribe__Extension class exists before trying to extend it.
if (
    class_exists( 'Tribe__Extension' )
    && ! class_exists( 'Tribe__Extension__Eventbrite_Ticket_Button' )
) {
    /**
     * Extension main class, responsible for adding custom fields and a button to events.
     */
    class Tribe__Extension__Eventbrite_Ticket_Button extends Tribe__Extension {

        /**
         * Constructor for the extension.
         * Sets up required plugins and hooks.
         */
        public function construct() {
            // Ensure The Events Calendar is active.
            $this->add_required_plugin( 'Tribe__Events__Main', '4.6' );
        }

        /**
         * Initializes the extension and sets up all necessary hooks.
         */
        public function init() {
            // Load plugin textdomain for internationalization.
            load_plugin_textdomain( 'tribe-ext-eventbrite-checkout-button', false, basename( dirname( __FILE__ ) ) . '/languages/' );

            // Add the meta box to the event editing screen in the WordPress admin.
            add_action( 'add_meta_boxes', array( $this, 'add_custom_link_meta_box' ) );

            // Save the data from our custom meta box when the event is saved.
            add_action( 'save_post_tribe_events', array( $this, 'save_custom_link_data' ), 10, 2 );

            // Output the custom description, buttons, and custom HTML on the single event page in the frontend.
            // CHANGED HOOK: From _end to _start to make it appear at the top
            add_action( 'tribe_events_single_event_meta_primary_section_start', array( $this, 'output_custom_content' ), 5 ); // Using a lower priority to ensure it's among the first
            // Note: The previous change to 'set_event_meta_box_order' is for backend and is no longer needed if this is the only frontend change.

            // Enqueue admin scripts for repeatable fields
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        }

        /**
         * Enqueues admin-specific JavaScript and localizes data.
         *
         * @param string $hook The current admin page hook.
         */
        public function enqueue_admin_scripts( $hook ) {
            // Only load on New Post and Edit Post screens
            if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
                return;
            }

            global $post;
            // Only load for 'tribe_events' post type
            if ( ! $post || 'tribe_events' !== $post->post_type ) {
                return;
            }

            // Enqueue jQuery UI Sortable
            wp_enqueue_script( 'jquery-ui-sortable' );

            // Enqueue a basic jQuery UI stylesheet for sortable feedback (placeholder, etc.)
            wp_enqueue_style(
                'jquery-ui-theme',
                '//code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css', // Using a stable CDN URL
                array(),
                '1.13.2'
            );

            // Enqueue your custom admin scripts
            wp_enqueue_script(
                'tribe-ext-eventbrite-admin-scripts',
                plugins_url( 'js/admin-scripts.js', __FILE__ ),
                array( 'jquery', 'jquery-ui-sortable' ), // jQuery and Sortable are dependencies
                filemtime( plugin_dir_path( __FILE__ ) . 'js/admin-scripts.js' ), // Version based on file modification time
                true // Enqueue in the footer
            );

            // Localize script to pass PHP variables (like translatable strings) to JavaScript
            wp_localize_script(
                'tribe-ext-eventbrite-admin-scripts',
                'eventbrite_ticket_button_vars', // This will be the global JS variable name
                array(
                    'embed_code_label'      => esc_html__( 'Eventbrite embed code', 'tribe-ext-eventbrite-checkout-button' ),
                    'embed_code_desc'       => esc_html__( 'Custom code provided by Eventbrite for embedding a checkout popup on the client\'s site.', 'tribe-ext-eventbrite-checkout-button' ),
                    'embed_code_info'       => esc_html__( 'If no embed code is inserted, only the link at the bottom of the frontend is displayed. If an embed code is inserted, again no link is displayed.', 'tribe-ext-eventbrite-checkout-button' ),
                    'url_label'             => esc_html__( 'Eventbrite URL', 'tribe-ext-eventbrite-checkout-button' ),
                    'url_desc'              => esc_html__( 'URL to Eventbrite site directly. Is used as fallback if no embed code is provided.', 'tribe-ext-eventbrite-checkout-button' ),
                    'remove_button_text'    => esc_html__( 'Remove Entry', 'tribe-ext-eventbrite-checkout-button' ),
                    'confirm_remove_text' => esc_html__( 'Are you sure you want to remove this entry?', 'tribe-ext-eventbrite-checkout-button' ),
                    'entry_label_prefix'    => esc_html__( 'Ticket Option', 'tribe-ext-eventbrite-checkout-button' ),
                )
            );
        }

        /**
         * Defines the custom field labels and their corresponding keys.
         * Now uses a single meta key for repeatable entries.
         *
         * @return array An associative array where keys are user-friendly labels and values are meta keys.
         */
        protected function get_custom_field_definitions() {
            return array(
                'Eventbrite Entries' => '_tribe_ext_eventbrite_entries', // This will store an array
                'Show Ticket Overview Link' => '_tribe_ext_eventbrite_show_overview', // Checkbox to show/hide ticket overview link
            );
        }

        /**
         * Adds a custom meta box to the 'tribe_events' post type editing screen.
         */
        public function add_custom_link_meta_box() {
            add_meta_box(
                'tribe_events_custom_link_details', // Unique ID for the meta box.
                esc_html__( 'Eventbrite Checkout Section', 'tribe-ext-eventbrite-checkout-button' ), // Title of the meta box.
                array( $this, 'render_custom_link_meta_box' ), // Callback function to render the content.
                'tribe_events', // The post type where the meta box will appear (The Events Calendar's event post type).
                'normal', // Context (where on the screen the meta box will be displayed).
                'high' // Priority within the context.
            );
        }

        /**
         * Renders the HTML content for the custom link meta box.
         * This now includes input fields for the link URL and the custom HTML,
         * with conditional display based on the embed code.
         *
         * @param WP_Post $post The current post object being edited.
         */
        public function render_custom_link_meta_box( $post ) {
            // Add a nonce field for security to verify the request origin.
            wp_nonce_field( 'custom_event_link_meta_box', 'custom_event_link_nonce' );

            // Get the current values for the custom fields.
            $fields = $this->get_custom_field_definitions();
            $eventbrite_entries = get_post_meta( $post->ID, $fields['Eventbrite Entries'], true );

            // Ensure it's an array for looping. Start with one empty entry if none exist.
            if ( ! is_array( $eventbrite_entries ) || empty( $eventbrite_entries ) ) {
                $eventbrite_entries = array(
                    array(
                        'html' => '',
                        'url'  => '',
                    ),
                );
            }
            ?>
            <style>
                /* Basic styling for the sort handle */
                .eventbrite-field-row {
                    border: 1px solid #ccc;
                    padding: 10px;
                    margin-bottom: 15px;
                    background: #fdfdfd;
                    position: relative;
                }
                .eventbrite-field-row .sort-handle {
                    cursor: grab;
                    position: absolute;
                    left: 5px;
                    top: 10px;
                    color: #888;
                    font-size: 20px; /* Adjust size for better visibility */
                    line-height: 1; /* Align vertically */
                }
                .eventbrite-field-row:hover .sort-handle {
                    color: #555;
                }
                .eventbrite-field-row .ticket-option-header {
                    display: flex; /* Use flexbox for alignment */
                    align-items: center; /* Vertically align items */
                    margin-bottom: 10px; /* Space below header */
                }
                .eventbrite-field-row .ticket-option-header .ticket-option-label {
                    margin: 0; /* Remove default paragraph margin */
                    padding-left: 30px; /* Space for the handle */
                }
                /* Hide the remove button initially for the first empty row if only one exists */
                .eventbrite-field-row.initial-empty-row .remove-eventbrite-field {
                    display: none;
                }
                /* Style for the jQuery UI Sortable placeholder */
                .ui-sortable-placeholder {
                    border: 1px dashed #bbb;
                    background: #eee;
                    visibility: visible !important;
                    height: 100px; /* Adjust as needed */
                    margin-bottom: 15px;
                }
                .ui-sortable-helper {
                    background: #fff; /* Keep helper background light */
                    box-shadow: 0 5px 15px rgba(0,0,0,0.2); /* Add shadow to dragged item */
                }
            </style>
            <div id="eventbrite-fields-wrapper">
                <?php foreach ( $eventbrite_entries as $index => $entry ) : ?>
                    <div class="eventbrite-field-row <?php echo ( $index === 0 && count( $eventbrite_entries ) === 1 && empty( $entry['html'] ) && empty( $entry['url'] ) ) ? 'initial-empty-row' : ''; ?>">
                        <div class="ticket-option-header">
                            <span class="dashicons dashicons-menu sort-handle"></span> <p class="ticket-option-label"><strong><?php printf( esc_html__( 'Ticket Option %d', 'tribe-ext-eventbrite-checkout-button' ), $index + 1 ); ?></strong></p>
                        </div>

                        <div class="tribe-ext-custom-html-field">
                            <label for="<?php echo esc_attr( $fields['Eventbrite Entries'] . '[' . $index . '][html]' ); ?>">
                                <?php esc_html_e( 'Eventbrite embed code:', 'tribe-ext-eventbrite-checkout-button' ); ?>
                            </label>
                            <textarea
                                id="<?php echo esc_attr( $fields['Eventbrite Entries'] . '[' . $index . '][html]' ); ?>"
                                name="<?php echo esc_attr( $fields['Eventbrite Entries'] . '[' . $index . '][html]' ); ?>"
                                rows="5"
                                class="large-text code"
                            ><?php echo esc_textarea( $entry['html'] ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'Custom code provided by Eventbrite for embedding a checkout popup on the client\'s site.', 'tribe-ext-eventbrite-checkout-button' ); ?>
                            </p>
                            <p class="description">
                                <?php esc_html_e( 'If no embed code is inserted, only the link at the bottom of the frontend is displayed. If an embed code is inserted, again no link is displayed.', 'tribe-ext-eventbrite-checkout-button' ); ?>
                            </p>
                        </div>
                        <div class="tribe-ext-custom-link-field">
                            <label for="<?php echo esc_attr( $fields['Eventbrite Entries'] . '[' . $index . '][url]' ); ?>">
                                <?php esc_html_e( 'Eventbrite URL:', 'tribe-ext-eventbrite-checkout-button' ); ?>
                            </label>
                            <input
                                type="url"
                                id="<?php echo esc_attr( $fields['Eventbrite Entries'] . '[' . $index . '][url]' ); ?>"
                                name="<?php echo esc_attr( $fields['Eventbrite Entries'] . '[' . $index . '][url]' ); ?>"
                                value="<?php echo esc_url( $entry['url'] ); ?>"
                                class="large-text"
                            />
                            <p class="description">
                                <?php esc_html_e( 'URL to Eventbrite site directly. Is used as fallback if no embed code is provided.', 'tribe-ext-eventbrite-checkout-button' ); ?>
                            </p>
                        </div>
                        <button type="button" class="button remove-eventbrite-field">
                            <?php esc_html_e( 'Remove Entry', 'tribe-ext-eventbrite-checkout-button' ); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <p>
                <button type="button" id="add-new-eventbrite-field" class="button button-primary">
                    <?php esc_html_e( 'Add New Ticket Option', 'tribe-ext-eventbrite-checkout-button' ); ?>
                </button>
            </p>
            <?php
            // Get the current value for the show overview checkbox
            $show_overview_key = $fields['Show Ticket Overview Link'];
            $show_overview = get_post_meta( $post->ID, $show_overview_key, true );
            // Default to true (checked) if not set
            if ( '' === $show_overview ) {
                $show_overview = '1';
            }
            ?>
            <div class="tribe-ext-show-overview-field">
                <label for="<?php echo esc_attr( $show_overview_key ); ?>">
                    <input
                        type="checkbox"
                        id="<?php echo esc_attr( $show_overview_key ); ?>"
                        name="<?php echo esc_attr( $show_overview_key ); ?>"
                        value="1"
                        <?php checked( $show_overview, '1' ); ?>
                    />
                    <?php esc_html_e( 'Show Link to ticket overview', 'tribe-ext-eventbrite-checkout-button' ); ?>
                </label>
            </div>
            <?php
        }

        /**
         * Saves the custom link data and Custom Button HTML when an event is saved or updated.
         *
         * @param int     $post_id The ID of the post being saved.
         * @param WP_Post $post    The post object.
         */
        public function save_custom_link_data( $post_id, $post ) {
            // Check if our nonce is set and valid for security.
            if ( ! isset( $_POST['custom_event_link_nonce'] ) || ! wp_verify_nonce( $_POST['custom_event_link_nonce'], 'custom_event_link_meta_box' ) ) {
                return;
            }

            // If this is an autosave, our form data will not be set, so we can skip.
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
                return;
            }

            // Check the user's permissions.
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }

            $fields = $this->get_custom_field_definitions();
            $meta_key = $fields['Eventbrite Entries'];
            $new_entries = array();

            // Process submitted entries
            if ( isset( $_POST[ $meta_key ] ) && is_array( $_POST[ $meta_key ] ) ) {
                // The order of elements in $_POST[$meta_key] will already reflect the sorted order due to how jQuery UI sorts.
                foreach ( $_POST[ $meta_key ] as $entry ) {
                    // Sanitize or retrieve values, ensuring defaults if not set
                    $html = isset( $entry['html'] ) ? $entry['html'] : '';
                    $url = isset( $entry['url'] ) ? esc_url_raw( $entry['url'] ) : ''; // Properly sanitize URL

                    // Only save the entry if at least one field has content
                    if ( ! empty( $html ) || ! empty( $url ) ) {
                        // WARNING: The HTML is stored unsanitized as per your original request.
                        // This introduces a severe Cross-Site Scripting (XSS) vulnerability.
                        // ONLY proceed if you fully understand and accept this security risk and trust the input source.
                        $new_entries[] = array(
                            'html' => $html,
                            'url'  => $url,
                        );
                    }
                }
            }

            // Update the post meta. If no valid entries, delete the meta key.
            if ( ! empty( $new_entries ) ) {
                update_post_meta( $post_id, $meta_key, $new_entries );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
            
            // Save the show overview checkbox value
            $show_overview_key = $fields['Show Ticket Overview Link'];
            $show_overview_value = isset( $_POST[ $show_overview_key ] ) ? '1' : '0';
            update_post_meta( $post_id, $show_overview_key, $show_overview_value );
        }

        /**
         * Outputs the hardcoded description, and the dynamic custom HTML/link buttons on the single event page.
         */
        public function output_custom_content() {
            $post_id = get_the_ID(); // Get the current event's ID.

            // Get the custom field values.
            $fields = $this->get_custom_field_definitions();
            $eventbrite_entries = get_post_meta( $post_id, $fields['Eventbrite Entries'], true );

            // Only show the section if there is at least one entry with html or url
            $has_content = false;
            if ( is_array( $eventbrite_entries ) && ! empty( $eventbrite_entries ) ) {
                foreach ( $eventbrite_entries as $entry ) {
                    if ( ! empty( $entry['html'] ) || ! empty( $entry['url'] ) ) {
                        $has_content = true;
                        break;
                    }
                }
            }
            if ( ! $has_content ) {
                return; // Do not output anything if no valid entry exists
            }

            // Hardcoded description text.
            $description_text = esc_html__( 'Zutritt zu diesen Filmen mit folgenden Tickets', 'tribe-ext-eventbrite-checkout-button' );

            // Hardcoded main button label for fallback URL.
            $button_label = esc_html__( 'Jetzt Ticket kaufen', 'tribe-ext-eventbrite-checkout-button' );

            ?>
            <div class="tribe-ext-custom-content-wrapper tribe-events-meta-group tribe-events-meta-group-details">
                <h3 class="tribe-events-single-section-title">
                    <?php esc_html_e( 'Tickets', 'tribe-ext-eventbrite-checkout-button' ); ?>
                </h3>
                <dl>
                    <dd class="tribe-ext-event-description">
                        <p><?php echo $description_text; ?></p>
                        
                        <?php
                        // Display each Eventbrite entry (embed code or fallback URL)
                        if ( is_array( $eventbrite_entries ) && ! empty( $eventbrite_entries ) ) {
                            foreach ( $eventbrite_entries as $entry ) {
                                $custom_button_html = isset( $entry['html'] ) ? $entry['html'] : '';
                                $link_url = isset( $entry['url'] ) ? $entry['url'] : '';

                                if ( ! empty( $custom_button_html ) ) {
                                    echo $custom_button_html; // WARNING: NO SANITIZATION APPLIED - BE CAREFUL!
                                } elseif ( ! empty( $link_url ) ) {
                                    // Only display the text link if a URL is provided AND the embed code field is empty.
                                    ?>
                                    <a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo $button_label; ?>
                                    </a>
                                    <?php
                                }
                            }
                        }
                        
                        // Check if the ticket overview link should be shown
                        $show_overview_key = $fields['Show Ticket Overview Link'];
                        $show_overview = get_post_meta( $post_id, $show_overview_key, true );
                        // Default to true (show link) if not set
                        if ( '' === $show_overview ) {
                            $show_overview = '1';
                        }
                        
                        if ( '1' === $show_overview ) :
                        ?>
                        <a href="<?php echo esc_url( 'https://alpinale.at/tickets' ); ?>" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Ticketübersicht', 'tribe-ext-eventbrite-checkout-button' ); ?>
                        </a>
                        <?php endif; ?>
                    </dd>
                </dl>
            </div>
            <?php
        }
    } // end class
} // end if class_exists check