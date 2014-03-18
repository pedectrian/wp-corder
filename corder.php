<?php
/*
  Plugin Name: C Order
  Plugin URI: http://taskodelic.com/corder
  Description: Plugin for simple client orders management.
  Version: 0.1
  Author: pedectrian
  Author URI: http://taskodelic.om
  Text Domain: corder
  Requires at least: 3.0
  Tested up to: 3.8
 */

class Corder
{
    const VERSION = '0.1';

    protected $wpdb;

    /** @var string $prefix */
    public $prefix = 'corder_client';

    public function __construct() {

        global $wpdb;

        $this->wpdb = $wpdb;

        // Add custom post type
        add_action( 'init', array( $this, 'create_order_post_type') );

        // Add custom post type list fields
        add_filter( 'manage_edit-corder_order_columns', array( $this, 'set_custom_edit_corder_order_columns' ));
        add_action( 'manage_corder_order_posts_custom_column', array( $this, 'custom_corder_order_column' ), 10, 2 );

        // Add custom post type edit meta box and set order title (Order #id)
        add_action( 'save_post', array( $this, 'save_corder_order_meta' ));
        add_filter( 'wp_insert_post_data', array( $this, 'set_corder_order_title' ));

        // Remove all actions for custom post type except edit
        add_filter( 'post_row_actions', array( $this, 'remove_row_actions' ));
    }

    /**
     * Adds custom post type 'corder_order'
     */
    public function create_order_post_type() {
        register_post_type( 'corder_order',
            array(
                'labels' => array(
                    'name'          => __( 'Orders' ),
                    'singular_name' => __( 'Order' )
                ),
                'public' => true,
                'publicly_queryable' => false,
                'query_var' => false,
                'has_archive' => true,
                'supports' => array(
                    'revisions',
                ),
                'menu_position' => 2,
                'register_meta_box_cb' => array( $this, 'corder_add_post_type_metabox' )
            )
        );
    }

    /**
     * Set corder_order post type list columns
     * @param $columns
     * @return mixed
     */
    public function set_custom_edit_corder_order_columns( $columns ) {

        unset( $columns['author'] );

        $newColumns = array(
            'name'          => __( 'Client Name', 'corder' ),
            'phone'         => __( 'Client Phone', 'corder' ),
            'town'          => __( 'Client Town', 'corder' ),
            'full_address'  => __( 'Client Full Address', 'corder' ),
            'delivery_type' => __( 'Client Delivery Type', 'corder' ),
        );

        return array_merge($columns, $newColumns);
    }

    /**
     * Get data for corder_order post type list fields
     * @param $column
     * @param $post_id
     */
    public function custom_corder_order_column( $column, $post_id ) {

        $term = get_post_meta( $post_id , '_' . $this->prefix . '_' . $column, true );

        // Change delivery ID to delivery name
        if( $column == 'delivery_type' ) {
            switch( $term ) {
                case 1:
                    $term = 'Самовывоз';
                    break;
                case 2:
                    $term = 'Курьер';
                    break;
            }
        }

        // Echo string or not found message
        if ( $term && is_string( $term ) ) {
            echo $term;
        } else {
            _e( 'Undefined value', 'corder' );
        }
    }

    /**
     * Saves corder_order post type meta fields
     * @param $post_id
     * @return bool
     */
    public function save_corder_order_meta( $post_id ) {

        // Form nonce validation
        if( !wp_verify_nonce( $_POST['corder_noncename'], plugin_basename(__FILE__) ) ) {
            return $post_id;
        }

        // is the user allowed to edit the post or page?
        if( ! current_user_can( 'edit_post', $post_id )){
            return $post_id;
        }

        $prefix = $this->prefix . '_';

        // Collect user data array
        $client = array(
            '_'.$prefix.'name'          =>  isset($_POST[$prefix.'name']) ? $_POST[$prefix.'name'] : '',
            '_'.$prefix.'phone'         =>  isset($_POST[$prefix.'phone']) ? $_POST[$prefix.'phone'] : '',
            '_'.$prefix.'town'          =>  isset($_POST[$prefix.'town']) ? $_POST[$prefix.'town'] : '',
            '_'.$prefix.'full_address'   =>  isset($_POST[$prefix.'full_address']) ? $_POST[$prefix.'full_address'] : '',
            '_'.$prefix.'delivery_type' =>  isset($_POST[$prefix.'delivery_type']) ? $_POST[$prefix.'delivery_type'] : '',
        );

        // add values as custom fields
        foreach( $client as $key => $value ) { // cycle through the $quote_post_meta array
            // if( $post->post_type == 'revision' ) return; // don't store custom data twice
            $value = implode(',', (array)$value); // if $value is an array, make it a CSV (unlikely)

            update_post_meta( $post_id, $key, $value);
        }

        return true;
    }

    /**
     * Sets corder_order title on save action
     */
    function set_corder_order_title( $post ){

        if(!isset($_GET['post_type']) || $_GET['post_type'] != 'corder_order' || $post['post_status'] != 'auto-draft') {
            return $post;
        }

        $order_id = get_option( 'order_id', 0 ) + 1;
        $post['post_title'] = "Order #{$order_id}";
        update_option( 'order_id', $order_id );

        return $post;
    }

    /**
     * Removes actions for corder_order post type
     * @param $actions
     * @return mixed
     */
    public function remove_row_actions( $actions )
    {
        global $current_screen;

        if( $current_screen->post_type != 'corder_order' )
            return $actions;

        unset( $actions['view'] );
        unset( $actions['trash'] );
        unset( $actions['inline hide-if-no-js'] );

        return $actions;
    }

    /**
     * Adds the corder_order meta box
     */
    function corder_add_post_type_metabox() {
        add_meta_box( 'corder_metabox', 'Order', array( $this, 'corder_metabox' ), 'corder_order', 'normal' );
    }

    /**
     * Meta box for corder_order edit page
     */
    public function corder_metabox() {
        global $post;

        // Noncename needed to verify where the data originated
        wp_nonce_field(plugin_basename(__FILE__), 'corder_noncename');

        // Get the data if its already been entered
        $prefix = 'corder_client';
        $corder_client_name = get_post_meta($post->ID, '_'.$prefix.'_name', true);
        $corder_client_phone = get_post_meta($post->ID, '_'.$prefix.'_phone', true);
        $corder_client_town = get_post_meta($post->ID, '_'.$prefix.'_town', true);
        $corder_client_fulladdress = get_post_meta($post->ID, '_'.$prefix.'_fulladdress', true);
        $corder_client_delivery_type = get_post_meta($post->ID, '_'.$prefix.'_delivery_type', true);

        // Echo out the field
        ?>

        <div class="width_full p_box">
            <p>
                <label>Client Name<br>
                    <input type="text" name="corder_client_name" class="widefat" value="<?php echo esc_attr($corder_client_name); ?>">
                </label>
            </p>
            <p>
                <label>Client Phone<br>
                    <input type="phone" name="corder_client_phone" class="widefat" value="<?php echo esc_attr($corder_client_phone); ?>">
                </label>
            </p>
            <p>
                <label>Client Town<br>
                    <textarea name="corder_client_town" class="widefat"><?php echo esc_attr($corder_client_town); ?></textarea>
                </label>
            </p>
            <p>
                <label>Client Full Address<br>
                    <textarea name="corder_client_full_address" class="widefat"><?php echo esc_attr($corder_client_fulladdress); ?></textarea>
                </label>
            </p>
            <p>
                <label>Client Delivery Type<br></label>
                <label><input type="radio" name="corder_client_delivery_type" value="1" <?php checked($corder_client_delivery_type, 1); ?> /> Самовывоз<br/></label>
                <label><input type="radio" name="corder_client_delivery_type" value="2" <?php checked($corder_client_delivery_type, 2); ?> /> Курьерская доставка</label>
            </p>
        </div>
    <?php
    }

    public function getOrderForm() {

        $processed = '';

        if( !isset($_POST['processed']) && isset($_POST['corder_noncename']) && wp_verify_nonce( $_POST['corder_noncename'], plugin_basename(__FILE__) ) ) {
            $post = array(
                'post_status' => 'publish',
                'post_type' => 'corder_order',
            );
            $order_id = get_option( 'order_id', 0 ) + 1;
            $post['post_title'] = "Order #{$order_id}";

            $post_id = wp_insert_post( $post );

            $prefix = $this->prefix . '_';

            // Collect user data array
            $client = array(
                '_'.$prefix.'name'          =>  isset($_POST[$prefix.'name']) ? $_POST[$prefix.'name'] : '',
                '_'.$prefix.'phone'         =>  isset($_POST[$prefix.'phone']) ? $_POST[$prefix.'phone'] : '',
                '_'.$prefix.'town'          =>  isset($_POST[$prefix.'town']) ? $_POST[$prefix.'town'] : '',
                '_'.$prefix.'full_address'   =>  isset($_POST[$prefix.'full_address']) ? $_POST[$prefix.'full_address'] : '',
                '_'.$prefix.'delivery_type' =>  isset($_POST[$prefix.'delivery_type']) ? $_POST[$prefix.'delivery_type'] : '',
            );

            // add values as custom fields
            foreach( $client as $key => $value ) { // cycle through the $quote_post_meta array
                // if( $post->post_type == 'revision' ) return; // don't store custom data twice
                $value = implode(',', (array)$value); // if $value is an array, make it a CSV (unlikely)

                update_post_meta( $post_id, $key, $value);
            }

            $processed = "<input type='hidden' name='processed' value='1'>";
            update_option( 'order_id', $order_id );
        }

        ?>

        <form method="POST" class="client-order-form">
            <?php echo $processed ?>
            <?php wp_nonce_field(plugin_basename(__FILE__), 'corder_noncename'); ?>
            <label class="f-label">ФИО</label>
            <input class="f-text" type="text" name="corder_client_name" placeholder="Иванова Мария Сергеевна">
            <label class="f-label">Полный адрес (с индексом)</label>
            <input class="f-text" type="text" name="corder_client_full_address" placeholder="142456, Москва, ул. Мира, д. 3, кв. 11">
            <label class="f-label">Телефон</label>
            <input class="f-text" type="text" name="corder_client_phone" placeholder="89123456789">

            <p>
                <label class="f-label">Тип доставки<br></label>
                <label><input type="radio" name="corder_client_delivery_type" value="1" checked /> Самовывоз<br/></label>
                <label><input type="radio" name="corder_client_delivery_type" value="2" /> Курьерская доставка</label>
            </p>

            <div class="align-center"><button type="submit" class="btn" >Заказать</button></div>
        </form>

<?php
    }
}

$corder = new Corder();