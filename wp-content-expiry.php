<?php
/**
 * Plugin Name:       Content Expiry
 * Description:       Schedule an expiry date for posts and pages — on expiry they are automatically moved to draft, trash, or private.
 * Version:           1.0.0
 * Author:            Azan Umer
 * License:           MIT
 * Text Domain:       wce
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WCE_META_DATE', '_wce_expiry_date' );     // Stored as Y-m-d H:i:s (site timezone).
define( 'WCE_META_ACTION', '_wce_expiry_action' );

/** Meta box on posts and pages. */
function wce_add_meta_box() {
    add_meta_box( 'wce-expiry', 'Content Expiry', 'wce_meta_box', array( 'post', 'page' ), 'side', 'default' );
}
add_action( 'add_meta_boxes', 'wce_add_meta_box' );

function wce_meta_box( $post ) {
    wp_nonce_field( 'wce_save', 'wce_nonce' );
    $date   = get_post_meta( $post->ID, WCE_META_DATE, true );
    $action = get_post_meta( $post->ID, WCE_META_ACTION, true );
    if ( ! in_array( $action, array( 'draft', 'trash', 'private' ), true ) ) {
        $action = 'draft';
    }
    // datetime-local needs "Y-m-d\TH:i"; stored value converts cleanly.
    $value = $date ? gmdate( 'Y-m-d\TH:i', strtotime( $date ) ) : '';
    ?>
    <p><label for="wce_date">Expiry date &amp; time</label><br>
    <input type="datetime-local" id="wce_date" name="wce_date" value="<?php echo esc_attr( $value ); ?>" style="width:100%"></p>
    <p><label for="wce_action">On expiry</label><br>
    <select id="wce_action" name="wce_action" style="width:100%">
        <option value="draft" <?php selected( $action, 'draft' ); ?>>Move to draft</option>
        <option value="trash" <?php selected( $action, 'trash' ); ?>>Move to trash</option>
        <option value="private" <?php selected( $action, 'private' ); ?>>Make private</option>
    </select></p>
    <p class="description">Leave blank for no expiry. A notice is shown in the post list 7 days before expiry.</p>
    <?php
}

function wce_save( $post_id ) {
    if ( ! isset( $_POST['wce_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wce_nonce'] ), 'wce_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $raw = isset( $_POST['wce_date'] ) ? sanitize_text_field( wp_unslash( $_POST['wce_date'] ) ) : '';
    if ( '' === $raw ) {
        delete_post_meta( $post_id, WCE_META_DATE );
        delete_post_meta( $post_id, WCE_META_ACTION );
        return;
    }
    // datetime-local gives "Y-m-d\TH:i"; validate strictly before storing.
    $dt = DateTime::createFromFormat( 'Y-m-d\TH:i', $raw, wp_timezone() );
    if ( ! $dt ) {
        return; // Ignore malformed input rather than storing garbage.
    }
    update_post_meta( $post_id, WCE_META_DATE, $dt->format( 'Y-m-d H:i:s' ) );
    $action = isset( $_POST['wce_action'] ) ? sanitize_key( wp_unslash( $_POST['wce_action'] ) ) : 'draft';
    update_post_meta( $post_id, WCE_META_ACTION, in_array( $action, array( 'draft', 'trash', 'private' ), true ) ? $action : 'draft' );
}
add_action( 'save_post', 'wce_save' );

/** Hourly sweep: apply the chosen action to expired published posts. */
function wce_activate() {
    if ( ! wp_next_scheduled( 'wce_sweep' ) ) {
        wp_schedule_event( time(), 'hourly', 'wce_sweep' );
    }
}
register_activation_hook( __FILE__, 'wce_activate' );

function wce_deactivate() {
    wp_clear_scheduled_hook( 'wce_sweep' );
}
register_deactivation_hook( __FILE__, 'wce_deactivate' );

function wce_sweep() {
    $now = current_time( 'mysql' ); // Site timezone, matching stored values.
    $expired = get_posts( array(
        'post_type'      => 'any',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'meta_query'     => array(
            array(
                'key'     => WCE_META_DATE,
                'value'   => $now,
                'compare' => '<=',
                'type'    => 'DATETIME',
            ),
        ),
    ) );
    foreach ( $expired as $post ) {
        $action = get_post_meta( $post->ID, WCE_META_ACTION, true );
        if ( 'trash' === $action ) {
            wp_trash_post( $post->ID );
        } elseif ( 'private' === $action ) {
            wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'private' ) );
        } else {
            wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ) );
        }
        // Expired once: clear the date so a re-publish does not instantly re-expire.
        delete_post_meta( $post->ID, WCE_META_DATE );
        delete_post_meta( $post->ID, WCE_META_ACTION );
    }
    return count( $expired );
}
add_action( 'wce_sweep', 'wce_sweep' );

/** Show expiry in the post list, with a warning when under 7 days away. */
function wce_columns( $columns ) {
    $columns['wce_expiry'] = 'Expires';
    return $columns;
}
add_filter( 'manage_posts_columns', 'wce_columns' );
add_filter( 'manage_pages_columns', 'wce_columns' );

function wce_column_content( $column, $post_id ) {
    if ( 'wce_expiry' !== $column ) {
        return;
    }
    $date = get_post_meta( $post_id, WCE_META_DATE, true );
    if ( ! $date ) {
        echo '—';
        return;
    }
    $ts = strtotime( $date );
    if ( $ts < time() + ( 7 * DAY_IN_SECONDS ) ) {
        echo '<strong style="color:#b32d2e;">' . esc_html( date_i18n( get_option( 'date_format' ), $ts ) ) . '</strong>';
    } else {
        echo esc_html( date_i18n( get_option( 'date_format' ), $ts ) );
    }
}
add_action( 'manage_posts_custom_column', 'wce_column_content', 10, 2 );
add_action( 'manage_pages_custom_column', 'wce_column_content', 10, 2 );
