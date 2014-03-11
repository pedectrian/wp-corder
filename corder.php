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

    public function __construct() {

        global $wpdb;

        $this->wpdb = $wpdb;
        // Create corder top admin menu
        //add_action('admin_menu', array($this, 'createMenu'));

//        register_activation_hook( __FILE__,  array($this, 'install') );
//        register_activation_hook( __FILE__,  array($this, 'fixturesLoad') );
        //register_deactivation_hook( __FILE__,  array($this, 'uninstall') );

        add_action( 'init', array($this, 'create_order_post_type') );

        add_filter( 'manage_edit-corder_order_columns', array(&$this, 'set_custom_edit_corder_order_columns') );
        add_action( 'manage_corder_order_posts_custom_column', array(&$this, 'custom_corder_order_column'), 10, 2 );
        add_action( 'save_post', array(&$this, 'save_corder_order_meta')); // save the custom fields
        add_action( 'save_post', array(&$this, 'set_corder_order_title')); // set custom title for order

        add_filter( 'post_row_actions', array(&$this, 'remove_row_actions') );

        // Create corder sub admin menu
        //add_action('admin_menu', array($this, 'createSubMenu'));
    }

    public function remove_row_actions( $actions )
    {
        global $current_screen;
        if( $current_screen->post_type != 'corder_order' ) return $actions;
        unset( $actions['view'] );
        unset( $actions['trash'] );
        unset( $actions['inline hide-if-no-js'] );

        return $actions;
    }
    public function set_custom_edit_corder_order_columns($columns) {
        unset( $columns['author'] );
        //unset( $columns['title'] );

        $columns['name'] = __( 'Client Name', 'corder' );
        $columns['phone'] = __( 'Client Phone', 'corder' );
        $columns['town'] = __( 'Client Town', 'corder' );
        $columns['fulladdress'] = __( 'Client Full Address', 'corder' );
        $columns['delivery_type'] = __( 'Client Delivery Type', 'corder' );

        return $columns;
    }
    function set_corder_order_title( $post_id ){
        global $wpdb;
        $date = date('l, d.m.Y', strtotime($_POST['rating_date']));
        $title = 'Order #' . $post_id;
        $where = array( 'ID' => $post_id );
        $wpdb->update( $wpdb->posts, array( 'post_title' => $title ), $where );
    }
    function save_corder_order_meta( $post_id ) { // save the data
        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times
        if( !wp_verify_nonce( $_POST['corder_noncename'], plugin_basename(__FILE__) ) ) {
            return $post_id;
        }

        // is the user allowed to edit the post or page?
        if( ! current_user_can( 'edit_post', $post_id )){
            return $post_id;
        }
        // ok, we're authenticated: we need to find and save the data
        // we'll put it into an array to make it easier to loop though

        $prefix = 'corder_client_';

        $client = array(
            '_'.$prefix.'name'          =>  isset($_POST[$prefix.'name']) ? $_POST[$prefix.'name'] : '',
            '_'.$prefix.'phone'         =>  isset($_POST[$prefix.'phone']) ? $_POST[$prefix.'phone'] : '',
            '_'.$prefix.'town'          =>  isset($_POST[$prefix.'town']) ? $_POST[$prefix.'town'] : '',
            '_'.$prefix.'fulladdress'   =>  isset($_POST[$prefix.'fulladdress']) ? $_POST[$prefix.'fulladdress'] : '',
            '_'.$prefix.'delivery_type' =>  isset($_POST[$prefix.'delivery_type']) ? $_POST[$prefix.'delivery_type'] : '',
        );

        // add values as custom fields
        foreach( $client as $key => $value ) { // cycle through the $quote_post_meta array
            // if( $post->post_type == 'revision' ) return; // don't store custom data twice
            $value = implode(',', (array)$value); // if $value is an array, make it a CSV (unlikely)
            if( get_post_meta( $post_id, $key, FALSE ) ) { // if the custom field already has a value
                update_post_meta( $post_id, $key, $value);
            } else { // if the custom field doesn't have a value
                add_post_meta( $post_id, $key, $value );
            }
            if( !$value ) { // delete if blank
                delete_post_meta( $post_id, $key );
            }
        }

        return true;
    }

    function custom_corder_order_column( $column, $post_id ) {

        $prefix = 'corder_client';
        $term = get_post_meta( $post_id , '_' . $prefix . '_' . $column, true );

        if($column == 'delivery_type') {
            switch($term) {
                case 1:
                    $term = 'Самовывоз';
                    break;
                case 2:
                    $term = 'Курьер';
                    break;
            }
        }

        if ( $term && is_string( $term ) )
            echo $term;
        else
            _e( 'Unable to get client\'s ' . $column, 'corder' );

    }

    public function create_order_post_type() {
        register_post_type( 'corder_order',
            array(
                'labels' => array(
                    'name' => __( 'Orders' ),
                    'singular_name' => __( 'Order' )
                ),
                'public' => true,
                'publicly_queryable' => false,
                'query_var' => false,
                'has_archive' => true,
                'supports' => array(
                    //'title',
                    //'editor',
                    //'excerpt',
                    //'thumbnail',
                    //'author',
                    //'trackbacks',
                    //'custom-fields',
                    //'comments',
                    'revisions',
                    //'page-attributes', // (menu order, hierarchical must be true to show Parent option)
                    //'post-formats',
                ),
                'menu_position' => 2,
                'register_meta_box_cb' => array(&$this, 'corder_add_post_type_metabox')
            )
        );
    }

    function corder_add_post_type_metabox() { // add the meta box
        add_meta_box( 'corder_metabox', 'Order', array(&$this, 'corder_metabox'), 'corder_order', 'normal' );
    }

    function corder_metabox() {
        global $post;
        // Noncename needed to verify where the data originated
        echo '<input type="hidden" name="corder_noncename" id="corder_noncename" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

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
                    <input type="text" name="corder_client_name" class="widefat" value="<?php echo $corder_client_name; ?>">
                </label>
            </p>
            <p><label>Client Phone<br>
                    <input type="phone" name="corder_client_phone" class="widefat" value="<?php echo $corder_client_phone; ?>">
                </label>
            </p>
            <p><label>Client Town<br>
                    <textarea name="corder_client_town" class="widefat"><?php echo $corder_client_town; ?></textarea>
                </label>
            </p>
            <p><label>Client Full Address<br>
                    <textarea name="corder_client_fulladdress" class="widefat"><?php echo $corder_client_fulladdress; ?></textarea>
                </label>
            </p>
            <p><label>Client Delivery Type<br></label>
                <label><input type="radio" name="corder_client_delivery_type" value="1" <?php echo $corder_client_delivery_type == 1 ? 'selected' : ''; ?> /> Самовывоз<br/></label>
                <label><input type="radio" name="corder_client_delivery_type" value="2" <?php echo $corder_client_delivery_type == 2 ? 'selected' : ''; ?> /> Курьерская доставка</label>
            </p>
        </div>
    <?php
    }

    public function createMenu() {
        add_menu_page('Corder', 'C Order', 'administrator', 'c-order', array( $this, 'corderList'), null, 3);
    }
    public function createSubMenu() {
        add_submenu_page( 'c-order', 'Add New', 'Add New', 'administrator', 'add-order', array( $this, 'corderNew') );
    }

    public function install() {

        $prfx = $this->wpdb->prefix;

        $sql =
            "CREATE TABLE {$prfx}c_order
             (id mediumint(9) NOT NULL AUTO_INCREMENT,
                  created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                  client_id int,
                  status_id int,
                  UNIQUE KEY id (id)
             );

             CREATE TABLE {$prfx}c_client
             (id mediumint(9) NOT NULL AUTO_INCREMENT,
                  full_name text,
                  phone text,
                  address_id int NOT NULL,
                  UNIQUE KEY id (id)
             );

             CREATE TABLE {$prfx}c_status
             (id mediumint(9) NOT NULL AUTO_INCREMENT,
                  created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                  order_id int,
                  status int,
                  UNIQUE KEY id (id)
             );

             CREATE TABLE {$prfx}c_address
             (id mediumint(9) NOT NULL AUTO_INCREMENT,
                  client_id int,
                  town text,
                  fulladdress text,
                  UNIQUE KEY id (id)
             );"
        ;


        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        add_option( "corder_db_version", self::VERSION );
    }

    public function fixturesLoad() {

        $prfx = $this->wpdb->prefix;

        $this->wpdb->insert($prfx . 'c_client', array(
                'full_name' => 'Александр Македонский',
                'phone' => '+79932993929',
                'address_id' => 1
            ));

        $this->wpdb->insert($prfx . 'c_order', array(
                'client_id' => 1,
                'status_id' => 1,
            ));

        $this->wpdb->insert($prfx . 'c_status', array(
                'order_id' => 1,
                'status' => 1,
            ));

        $this->wpdb->insert($prfx . 'c_address', array(
                'client_id' => 1,
                'town' => 'Москва',
                'fulladdress' => '123456, Россия, Москва, ул. Довата'
            ));
    }

    public function uninstall() {

        $corder  = $this->wpdb->prefix . "c_order";
        $client  = $this->wpdb->prefix . "c_client";
        $address = $this->wpdb->prefix . "c_address";
        $status  = $this->wpdb->prefix . "c_status";

        $this->wpdb->query("DROP TABLE IF EXISTS {$corder}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$client}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$address}");
        $this->wpdb->query("DROP TABLE IF EXISTS {$status}");
    }

    public function corderList() {
        ?>

        <div class="wrap">
            <h2>Corder list</h2>

            <form method="post" action="options.php">
                <?php settings_fields( 'baw-settings-group' ); ?>
                <?php do_settings_sections( 'baw-settings-group' ); ?>
                <table class="wp-list-table widefat fixed posts">
                    <thead>
                        <th>ID</th>
                        <th>Время создания</th>
                        <th>Имя клиента</th>
                        <th>Телефон</th>
                        <th>Полный адрес</th>
                        <th>Статус</th>
                    </thead>
                    <?php foreach($this->getOrders() as $order): ?>
                        <tr valign="top" class="alternate">
                            <td scope="row"><?php echo $order->id; ?></td>
                            <td><?php echo $order->created_at; ?></td>
                            <td><?php echo $order->full_name; ?></td>
                            <td><?php echo $order->phone; ?></td>
                            <td><?php echo $order->fulladdress; ?></td>
                            <td><?php echo $order->status; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

            </form>
        </div>
    <?php }

    public function corderNew() {

        if(isset($_POST['client'])) {
//            echo $_POST['client']['name'];
//
//            $prfx = $this->wpdb->prefix;
//
//            $this->wpdb->insert($prfx . 'c_client', array(
//                    'full_name' => $_POST['client']['name'],
//                    'phone' => $_POST['client']['phone'],
//                    'address_id' => 1
//                ));
//
//            $this->wpdb->query(
//                "INSERT INTO {$prfx}'c_client'('full_name', 'phone')"
//            );
        } else {
        ?>

        <div class="wrap">
            <h2>Add new</h2>

            <form method="POST">
                <div>
                    <label class="f-label">ФИО</label>
                    <input class="f-text" type="text" name="client[name]" placeholder="Иванова Мария Сергеевна"/>
                    <label class="f-label">Город</label>
                    <input class="f-text" type="text" name="client[city]" placeholder="Москва"/>
                    <label class="f-label">Полный адрес (с индексом)</label>
                    <input class="f-text" type="text" name="client[fulladdress]" placeholder="123456, Россия, Москва, ул. Довата"/>
                    <label class="f-label">Телефон</label>
                    <input class="f-text" type="text" name="client[phone]" placeholder="89123456789"/>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
    <?php }
    }

    public function getOrders() {
        $prfx = $this->wpdb->prefix;
        $sql = "
            SELECT
                cord.id, cord.created_at, cli.full_name, cli.phone, st.status, st.created_at st_created, addr.town, addr.fulladdress
                FROM {$prfx}" . "c_order cord
                    LEFT JOIN {$prfx}" . "c_client cli on cord.client_id = cli.id
                    LEFT JOIN {$prfx}" . "c_status st on cord.status_id = st.id
                    LEFT JOIN {$prfx}" . "c_address addr on cli.address_id = addr.id;"
        ;
        return $this->wpdb->get_results($sql);
    }
}

$corder = new Corder();