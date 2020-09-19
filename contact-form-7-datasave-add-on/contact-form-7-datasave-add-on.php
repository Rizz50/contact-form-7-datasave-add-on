<?php
/*
  Plugin Name: Contact Form 7 Datasave Add-On
*/

if( ! class_exists( 'WP_List_Table' ) ) {
  require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

function cf7_data_create_table() {
  global $wpdb;
  $charset_collate = $wpdb->get_charset_collate();
  require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

  //* Create the teams table
  $table_name = $wpdb->prefix . 'cf7_data';
  $sql = "CREATE TABLE $table_name (
    id INTEGER NOT NULL AUTO_INCREMENT,
    form TEXT NOT NULL,
    name TEXT NOT NULL,
    email TEXT NOT NULL,
    subject TEXT NULL,
    message TEXT NULL,
    date datetime,
    PRIMARY KEY (id)
  ) $charset_collate;";
  dbDelta( $sql );
}
register_activation_hook( __FILE__, 'cf7_data_create_table' );

class CF7_Data_Save extends WP_List_Table {

  function __construct(){
    global $status, $page;

    parent::__construct( array(
      'singular'  => __( 'contact_form_data', 'mycf7datasave' ),     //singular name of the listed records
      'plural'    => __( 'contact_forms_data', 'mycf7datasave' ),   //plural name of the listed records
      'ajax'      => false        //does this table support ajax?
    ));
    add_action( 'admin_head', array( &$this, 'admin_header' ) );            
  }

  function no_items() {
    _e( 'No data found.' );
  }

  function column_default( $item, $column_name ) {
    switch( $column_name ) {
      case 'id': 
      case 'name':
      case 'email':
      case 'subject':
      case 'message':
        return $item[ $column_name ];
      default:
        return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
    }
  }

  function get_columns(){
    $columns = array(
      'cb'        => '<input type="checkbox" />',
      'name' => __( 'Name', 'mycf7datasave' ),
      'email'    => __( 'Email', 'mycf7datasave' ),
      'subject'      => __( 'Subject', 'mycf7datasave' ),
      'message'      => __( 'Message', 'mycf7datasave' )
    );
    return $columns;
  }

  function column_name($item){
    $actions = array(
      // 'edit'      => sprintf('<a href="?page=%s&action=%s&contact_form_data=%s">Edit</a>',$_REQUEST['page'],'edit',$item['ID']),
      'delete'    => sprintf('<a href="?page=%s&action=%s&contact_form_data=%s">Delete</a>',$_REQUEST['page'],'single_delete',$item['id']),
    );
    return sprintf('%1$s %2$s', $item['name'], $this->row_actions($actions) );
  }

  function get_bulk_actions() {
    $actions = array(
      'delete'    => 'Delete'
    );
    return $actions;
  }

  public function process_bulk_action() {
    
    $action = $this->current_action();
    switch ( $action ) {
        case 'delete':
            global $wpdb;
            foreach($_POST['contact_form_data'] as $id) {
              $wpdb->query("DELETE FROM ".$wpdb->prefix . "cf7_data WHERE id = $id");
            }
            //wp_die( 'Delete something' );
            break;
        case 'save':
            wp_die( 'Save something' );
            break;
        default:
            // do nothing or something else
            return;
            break;
    }
    exit;

    return;
}

  function column_cb($item) {
    return sprintf(
      '<input type="checkbox" name="contact_form_data[]" value="%s" />', $item['id']
    );    
  }

  function prepare_items() {
    global $wpdb;
    $get_cf7_data = $wpdb->get_results("SELECT * FROM ". $wpdb->prefix . "cf7_data", ARRAY_A);
    $columns = $this->get_columns();
    $hidden = array();
    $sortable = array();
    $this->_column_headers = array($columns, $hidden, $sortable);
    $this->items = $get_cf7_data;;
    $this->process_bulk_action();
  }
} //class


function my_add_menu_items(){
  $hook = add_menu_page( 'Contact Form 7 Data List', 'Contact Form 7 Data List Example', 'activate_plugins', 'contact_form_7_data_list', 'render_contact_form_7_list_page' );
  add_action( "load-$hook", 'add_options' );
}


function add_options() {
  global $myCF7DataSave;
  $option = 'per_page';
  $args = array(
    'label' => 'Column',
    'default' => 10,
    'option' => 'columns_per_page'
  );
  add_screen_option( $option, $args );
  $myCF7DataSave = new CF7_Data_Save();
}
add_action( 'admin_menu', 'my_add_menu_items' );

function render_contact_form_7_list_page(){
  global $myCF7DataSave;
  echo '</pre><div class="wrap"><h2>Contact Form 7 Data List</h2>'; 
  $myCF7DataSave->prepare_items(); 
  ?>
  <form method="post">
    <input type="hidden" name="page" value="cf7_list_table">
  <?php
    $myCF7DataSave->search_box( 'search', 'search_id' );
    $myCF7DataSave->display(); 
    echo '</form></div>'; 
}

// Save contact form data
add_action('wpcf7_before_send_mail', 'save_form' );
function save_form( $wpcf7 ) {
  global $wpdb;
  $submission = WPCF7_Submission::get_instance();
 
  if ( $submission ) {
    $submited = array();
    $submited['title'] = $wpcf7->title();
    $submited['posted_data'] = $submission->get_posted_data();
  }

  $data = array(
    'name'  => $submited['posted_data']['your-name'],
    'email'  => $submited['posted_data']['your-email'],
    'subject'  => $submited['posted_data']['your-subject'],
    'message'  => $submited['posted_data']['your-message']
  );
 
  $wpdb->insert( $wpdb->prefix . 'cf7_data', 
    array( 
      'form'  => $submited['title'], 
      'name' => $data['name'],
      'email' => $data['email'],
      'subject' => $data['subject'],
      'message' => $data['message'],
      'date' => date('Y-m-d H:i:s')
    )
  );
}

// Delete single record
if(isset($_GET['action']) && $_GET['action'] == 'single_delete') {
  global $wpdb;
  $id = $_GET['contact_form_data'];
  $table = $wpdb->prefix . 'cf7_data';
  $wpdb->delete( $table, array( 'id' => $id ) );
}