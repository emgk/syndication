<?php

if( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Syndication_Logger_List_Table extends WP_List_Table {

	public $prepared_data = array();

	public $found_data = array();

	public $syndication_logger_table = null;

	public function __construct(){
		global $status, $page;

		parent::__construct( array(
			'singular'  => __( 'log', 'push-syndication' ),
			'plural'    => __( 'logs', 'push-syndication' ),
			'ajax'      => false
		) );

		add_action( 'admin_head', array( $this, 'admin_header' ) );
	}

	public function admin_header() {
		$current_page = ( isset( $_GET['page'] ) ) ? (int) $_GET['page'] : false;
		if( 'syndication_dashboard' != $current_page )
			return;

		?>
		<style type="text/css">
			.wp-list-table .column-object_id { width: 5%; }
			.wp-list-table .column-log_id { width: 10%; }
			.wp-list-table .column-time { width: 15%; }
			.wp-list-table .column-msg_type { width: 10%; }
			.wp-list-table .column-message { width: 50%; }
			.wp-list-table .column-status { width: 10%; }
		</style>
		<?php
	}

	public function no_items() {
		_e( 'No log entries found.' );
	}

	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'object_id':
			case 'log_id':
			case 'time':
			case 'msg_type':
			case 'status':
			case 'message':
				return $item[ $column_name ];

			default:
				return print_r( $item, true );
		}
	}

	public function get_sortable_columns() {
		$sortable_columns = array(
			'object_id' => array( 'object_id',	false ),
			'log_id'	=> array( 'log_id', 	false ),
			'time'		=> array( 'time', 		false ),
			'msg_type' 	=> array( 'msg_type',	false ),
			'message'  	=> array( 'message', 	false ),
			'status'   	=> array( 'status',		false )
			);
		return $sortable_columns;
	}

	public function get_columns(){
		$columns = array(
			'object_id'	=> __( 'Object ID', 'push-syndication' ),
			'log_id'	=> __( 'Log ID', 	'push-syndication' ),
			'time'		=> __( 'Time', 		'push-syndication' ),
			'msg_type'	=> __( 'Type', 		'push-syndication' ),
			'status'	=> __( 'Status', 	'push-syndication' ),
			'message'	=> __( 'Message', 	'push-syndication' ),
			);
		return $columns;
	}

	public function usort_reorder( $a, $b ) {
		$orderby = ( ! empty( $_GET['orderby'] ) ) ? esc_attr( $_GET['orderby'] ) : 'time';
		$order = ( ! empty($_GET['order'] ) ) ? esc_attr( $_GET['order'] ) : 'desc';
		$result = strcmp( $a[$orderby], $b[$orderby] );
		return ( $order === 'asc' ) ? $result : -$result;
	}

	public function column_log_id($item){
		return sprintf('%1$s', substr( $item['log_id'], 0, 3 ) . ' ... ' . substr( $item['log_id'], -3 ) );
	}

	public function get_bulk_actions() {
		$actions = array();
		return $actions;
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$log_id = ( isset( $_REQUEST['log_id'] ) ) ? esc_attr( $_REQUEST['log_id'] ) : null;
		$msg_type = null;
		$object_id = null;
		$object_type = 'post';
		$log_status = null;
		$date_start = null;
		$date_end = null;
		$message = null;
		$storage_type = 'object';

		$log_data = Syndication_Logger::instance()->get_messages(
				$log_id,
				$msg_type,
				$object_id,
				$object_type,
				$log_status,
				$date_start,
				$date_end,
				$message,
				$storage_type
		);

		foreach( $log_data as $site_id => $log_items ) {
			$this->prepared_data = array_merge( $this->prepared_data, $log_items );
		}
		usort( $this->prepared_data, array( $this, 'usort_reorder' ) );

		$per_page = $this->get_items_per_page( 'per_page' );
		$current_page = $this->get_pagenum();
		$total_items = count( $this->prepared_data );

		$this->found_data = array_slice( $this->prepared_data,( ( $current_page-1 )* $per_page ), $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		) );
		$this->items = $this->found_data;
	}

	protected function extra_tablenav( $which ) {
		?>
		<div class="alignleft actions">
			<?php
			if ( 'top' == $which && !is_singular() ) {

				$this->create_log_id_dropdown();
				// create months_dropdown
				// create site dropdown
				// create event dropdown

				submit_button( __( 'Filter' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
			}

			?>
		</div>
		<?php
	}

	private function create_log_id_dropdown() {
		$requested_log_id = isset( $_REQUEST['log_id'] ) ? esc_attr( $_REQUEST['log_id'] ) : 0;
		?>
		<label for="filter-by-log-id"><?php _e( 'Filter by Log ID' ); ?></label>
		<select name="log_id" id="filter-by-log-id">
			<option<?php selected( $requested_log_id, 0 ); ?> value="0"><?php _e( 'All logs' ); ?></option>
			<?php
			$log_ids = array();
			foreach ( $this->prepared_data as $row ) {
				if ( 0 == $row['log_id'] )
					continue;

				$log_id = esc_attr( $row['log_id'] );
				if ( ! isset( $log_ids[$log_id] ) ) {
					$log_ids[$log_id] = sprintf( "<option %s value='%s'>%s</option>\n",
						selected( $requested_log_id, $log_id, false ),
						esc_attr( $log_id ),
						esc_attr( $log_id )
					);
				}
			}

			echo implode( "\n", $log_ids );
			?>
		</select>
		<?php
	}
}


class Syndication_Logger_Viewer {

	public $syndication_logger_table;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_items' ) );
	}

	public function add_menu_items(){
		$hook = add_submenu_page( 'edit.php?post_type=syn_site', 'Logs', 'Logs', 'activate_plugins', 'syndication_dashboard', array( $this, 'render_list_page' ) );
		add_action( "load-$hook", array( $this, 'initialize_list_table' ) );
	}

	public function initialize_list_table() {
		if ( ! empty( $_POST['log_id'] ) && ( empty( $_GET['log_id'] ) || esc_attr( $_GET['log_id'] ) != esc_attr( $_POST['log_id'] ) ) ) {
			wp_safe_redirect( add_query_arg( array( 'log_id' => esc_attr( $_REQUEST['log_id'] ) ), wp_unslash($_SERVER['REQUEST_URI'] ) ) );
			exit;
		}
		$this->syndication_logger_table = new Syndication_Logger_List_Table();
	}

	public function render_list_page(){
		?>
		<div class="wrap"><h2><?php _e( "Syndication Logs", "syndication" ); ?></h2>
			<?php
			$this->syndication_logger_table->prepare_items();
			?>
			<form method="post" action="">
				<input type="hidden" name="page" value="ttest_list_table">
				<?php
				$this->syndication_logger_table->search_box( 'search', 'search_id' );

				$this->syndication_logger_table->display();
				?>
			</form>

			<div id="ajax-response"></div>
			<br class="clear" />
		</div>
		<?php
	}
}
