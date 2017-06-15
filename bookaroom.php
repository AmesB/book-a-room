<?PHP
/*
Plugin Name: Book a Room
Plugin URI: https://wordpress.org/plugins/book-a-room/
Description: Book a Room is a library oriented meeting room management and event calendar system.
Version: 1.6.7
Author: Colin Tomele
Author URI: http://heightslibrary.org
License: GPLv2 or later
*/
global $bookaroom_db_version;
$bookaroom_db_version = "1.3";

define( 'BOOKAROOM_PATH', plugin_dir_path( __FILE__ ) );
require_once( BOOKAROOM_PATH . 'bookaroom-meetings-public.php' );
require_once( BOOKAROOM_PATH . 'bookaroom-events.php' );
require_once( BOOKAROOM_PATH . 'bookaroom-payments.php' );
require_once( BOOKAROOM_PATH . 'sharedFunctions.php' );

register_activation_hook( __FILE__, array( 'bookaroom_init', 'on_activate' ) );
register_deactivation_hook( __FILE__, array( 'bookaroom_init', 'on_deactivate' ) );
register_uninstall_hook( __FILE__, array( 'bookaroom_init', 'on_uninstall' ) );

add_action( 'init', 'myStartSession', 1);
add_action( 'wp_logout', 'myEndSession' );
add_action( 'wp_login', 'myEndSession' );
add_action( 'init', 'my_script_enqueuer' );

#add_filter( 'the_content',  array( 'bookaroom_public', 'mainForm' ) );

add_action( 'admin_notices', array( 'bookaroom_init', 'plugin_activation_message' ) ) ;

add_action( 'admin_menu', array( 'bookaroom_settings', 'add_settingsPage' ) );

add_filter(		'gform_pre_render',			array( 'bookaroom_creditCardPayments', 'returnIncomingValidation' ) );
add_action(		'gform_after_submission',	array( 'bookaroom_creditCardPayments', 'finishedSubmission' ));

function my_script_enqueuer() {
	
	add_shortcode( 'bookaroom_payment',	array( 'bookaroom_creditCardPayments', 'managePayments' )  );
	add_shortcode( 'meetingRooms',	array( 'bookaroom_public', 'mainForm' ) );
	
	$width = get_option( 'bookaroom_screenWidth' );

	if( !empty( $width ) || $width == 1 ):
		wp_enqueue_style( 'myCSS', plugins_url( 'book-a-room/css/bookaroom_thin.css' ) );
	else:
		wp_enqueue_style( 'myCSS', plugins_url( 'book-a-room/css/bookaroom_day.css' ) );
	endif;
	
	global $bookaroom_db_version;
	if( get_option( 'bookaroom_db_version' ) !== $bookaroom_db_version ):
		update_bookaroom_database();
	endif;
	
	wp_enqueue_script( 'bookaroom_js', plugins_url( 'book-a-room/js/jstree/jquery.jstree.js' ), false );

}

function update_bookaroom_database()
{
	global $wpdb;
	global $bookaroom_db_version;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
	#
	# 1.2 to 1.3 - I added functions to internationalize addresses. This changes the state from a 2 to 255 long value.
	if( get_option( 'bookaroom_db_version' ) <= '1.2' ):
		$sql = "ALTER TABLE {$wpdb->prefix}bookaroom_reservations CHANGE  me_contactState me_contactState VARCHAR( 255 );";
		$wpdb->query( $sql );
		$sql = "ALTER TABLE {$wpdb->prefix}bookaroom_reservations_deleted CHANGE  me_contactState me_contactState VARCHAR( 255 );";
		$wpdb->query( $sql );
	endif;
}

class bookaroom_init
# simple class for activating, deactivating and uninstalling plugin
{
    public static function on_activate()
	# this is only run when hooked by activating plugin
    {
		
		global $wpdb;
		global $bookaroom_db_version;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		# create table for amenities		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_amenities (
				  amenityID int(10) unsigned NOT NULL AUTO_INCREMENT,
				  amenityDesc varchar(128) NOT NULL,
				  amenity_isReservable tinyint(1) NOT NULL DEFAULT '0',
				  PRIMARY KEY  (amenityID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# create table for branches
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_branches (
				  branchID int(11) unsigned NOT NULL AUTO_INCREMENT,
				  branchDesc varchar(64) NOT NULL,
				  branchAddress text NOT NULL,
				  branchMapLink varchar(255) NOT NULL,
				  branchImageURL text,
				  branch_isPublic tinyint(1) NOT NULL,
				  branch_hasNoloc tinyint(1) NOT NULL DEFAULT '1',
				  branchOpen_0 time DEFAULT NULL,
				  branchOpen_1 time DEFAULT NULL,
				  branchOpen_2 time DEFAULT NULL,
				  branchOpen_3 time DEFAULT NULL,
				  branchOpen_4 time DEFAULT NULL,
				  branchOpen_5 time DEFAULT NULL,
				  branchOpen_6 time DEFAULT NULL,
				  branchClose_0 time DEFAULT NULL,
				  branchClose_1 time DEFAULT NULL,
				  branchClose_2 time DEFAULT NULL,
				  branchClose_3 time DEFAULT NULL,
				  branchClose_4 time DEFAULT NULL,
				  branchClose_5 time DEFAULT NULL,
				  branchClose_6 time DEFAULT NULL,
				  PRIMARY KEY  (branchID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# create table for citie list		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_cityList (
				  cityID int(10) unsigned NOT NULL AUTO_INCREMENT,
				  cityDesc varchar(128) NOT NULL,
				  UNIQUE KEY cityID (cityID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# create table for closings
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_closings (
				  closingID int(10) unsigned NOT NULL AUTO_INCREMENT,
				  reoccuring tinyint(1) NOT NULL DEFAULT '0',
				  type enum('date','range','special') NOT NULL,
				  startDay tinyint(3) unsigned DEFAULT NULL,
				  startMonth tinyint(3) unsigned DEFAULT NULL,
				  startYear smallint(5) unsigned DEFAULT NULL,
				  endDay tinyint(3) unsigned DEFAULT NULL,
				  endMonth tinyint(3) unsigned DEFAULT NULL,
				  endYear smallint(5) unsigned DEFAULT NULL,
				  spWeek tinyint(3) unsigned DEFAULT NULL,
				  spDay tinyint(3) unsigned DEFAULT NULL,
				  spMonth smallint(6) DEFAULT NULL,
				  spYear smallint(5) unsigned DEFAULT NULL,
				  allClosed tinyint(1) NOT NULL,
				  roomsClosed text,
				  closingName varchar(128) NOT NULL,
				  username varchar(128) NOT NULL,
				  changed timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  PRIMARY KEY  (closingID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# create table for event age selection
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_eventAges (
				  ea_id int(11) NOT NULL AUTO_INCREMENT,
				  ea_eventID int(11) NOT NULL,
				  ea_ageID int(11) NOT NULL,
				  PRIMARY KEY  (ea_id),
				  KEY ea_eventID (ea_eventID,ea_ageID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
		
		# create table for event category selection
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_eventCats (
				  ec_id int(11) NOT NULL AUTO_INCREMENT,
				  ec_eventID int(11) NOT NULL,
				  ec_catID int(11) NOT NULL,
				  PRIMARY KEY  (ec_id),
				  KEY ec_eventID (ec_eventID,ec_catID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
				
		# create table for event Age groups
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_event_ages (
				  age_id int(11) unsigned NOT NULL AUTO_INCREMENT,
				  age_desc varchar(254) CHARACTER SET latin1 NOT NULL,
				  age_order int(10) unsigned NOT NULL,
				  age_active tinyint(1) NOT NULL DEFAULT '1',
				  PRIMARY KEY (age_id),
				  UNIQUE KEY age_id (age_id)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# create table for event Category groups
		#$table_name = $wpdb->prefix . "bookaroom_event_categories";
		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_event_categories (
				  categories_id int(11) unsigned NOT NULL AUTO_INCREMENT,
				  categories_desc varchar(254) NOT NULL,
				  categories_order int(10) unsigned NOT NULL,
				  categories_active tinyint(1) NOT NULL DEFAULT '1',
				  PRIMARY KEY  (categories_id)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );
		
		# Create table for registrations
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_registrations (
				  reg_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				  reg_fullName varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				  reg_phone varchar(34) COLLATE utf8_unicode_ci DEFAULT NULL,
				  reg_email varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
				  reg_notes text COLLATE utf8_unicode_ci,
				  reg_eventID int(11) NOT NULL,
				  reg_dateReg timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  PRIMARY KEY (reg_id),
				  FULLTEXT KEY reg_fullName (reg_fullName)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
		
		# Create table for reservations		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_reservations (
				  res_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				  res_created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  ev_desc text NOT NULL,
				  ev_maxReg int(10) unsigned DEFAULT NULL,
				  ev_amenity text,
				  ev_waitingList int(11) DEFAULT NULL,
				  ev_presenter varchar(255) DEFAULT NULL,
				  ev_privateNotes text,
				  ev_publicEmail varchar(128) DEFAULT NULL,
				  ev_publicName varchar(255) DEFAULT NULL,
				  ev_publicPhone varchar(15) DEFAULT NULL,
				  ev_noPublish tinyint(1) NOT NULL DEFAULT '0',
				  ev_regStartDate timestamp NULL DEFAULT NULL,
				  ev_regType enum('yes','no','staff') DEFAULT NULL,
				  ev_submitter varchar(255) NOT NULL,
				  ev_title varchar(255) DEFAULT NULL,
				  ev_website varchar(255) DEFAULT NULL,
				  ev_webText varchar(255) DEFAULT NULL,
				  me_amenity text NOT NULL,
				  me_contactAddress1 varchar(128) NOT NULL,
				  me_contactAddress2 varchar(128) DEFAULT NULL,
				  me_contactCity varchar(64) NOT NULL,
				  me_contactEmail varchar(255) NOT NULL,
				  me_contactName varchar(128) NOT NULL,
				  me_contactPhonePrimary varchar(15) NOT NULL,
				  me_contactPhoneSecondary varchar(15) DEFAULT NULL,
				  me_contactState varchar(255) NOT NULL,
				  me_contactWebsite varchar(255) DEFAULT NULL,
				  me_contactZip varchar(10) NOT NULL,
				  me_desc text NOT NULL,
				  me_eventName varchar(255) NOT NULL,
				  me_nonProfit tinyint(1) NOT NULL DEFAULT '0',
				  me_numAttend smallint(5) unsigned NOT NULL,
				  me_notes text NOT NULL,
				  me_status set('pending','pendPayment','approved','denied','archived') NOT NULL DEFAULT 'pending',
				  me_salt varchar(40) NOT NULL,
				  me_creditCardPaid tinyint(1) DEFAULT NULL,
				  me_libcardNum varchar(64) DEFAULT NULL,
				  me_social tinyint(1) DEFAULT NULL,
				  UNIQUE KEY res_id (res_id),
				  FULLTEXT KEY ev_presenter (ev_presenter,ev_privateNotes,ev_publicEmail,ev_publicName,ev_submitter,ev_title,ev_website,ev_webText,ev_desc),
				  FULLTEXT KEY me_contactEmail (me_contactEmail,me_contactName,me_desc,me_eventName,me_notes)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		dbDelta( $sql );

		# Create table for deleted reservations
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_reservations_deleted (
				  del_id int(11) NOT NULL AUTO_INCREMENT,
				  del_changed timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  res_id int(10) unsigned NOT NULL,
				  res_created timestamp NULL DEFAULT NULL,
				  ev_desc text NOT NULL,
				  ev_maxReg int(10) unsigned DEFAULT NULL,
				  ev_amenity text,
				  ev_waitingList int(11) DEFAULT NULL,
				  ev_presenter varchar(255) DEFAULT NULL,
				  ev_privateNotes text,
				  ev_publicEmail varchar(128) DEFAULT NULL,
				  ev_publicName varchar(255) DEFAULT NULL,
				  ev_publicPhone varchar(15) DEFAULT NULL,
				  ev_noPublish tinyint(1) NOT NULL DEFAULT '0',
				  ev_regStartDate timestamp NULL DEFAULT NULL,
				  ev_regType enum('yes','no','staff') DEFAULT NULL,
				  ev_submitter varchar(255) NOT NULL,
				  ev_title varchar(255) DEFAULT NULL,
				  ev_website varchar(255) DEFAULT NULL,
				  ev_webText varchar(255) DEFAULT NULL,
				  me_amenity text NOT NULL,
				  me_contactAddress1 varchar(128) NOT NULL,
				  me_contactAddress2 varchar(128) DEFAULT NULL,
				  me_contactCity varchar(64) NOT NULL,
				  me_contactEmail varchar(255) NOT NULL,
				  me_contactName varchar(128) NOT NULL,
				  me_contactPhonePrimary varchar(15) NOT NULL,
				  me_contactPhoneSecondary varchar(15) DEFAULT NULL,
				  me_contactState varchar(255) NOT NULL,
				  me_contactWebsite varchar(255) DEFAULT NULL,
				  me_contactZip varchar(10) NOT NULL,
				  me_desc text NOT NULL,
				  me_eventName varchar(255) NOT NULL,
				  me_nonProfit tinyint(1) NOT NULL DEFAULT '0',
				  me_numAttend smallint(5) unsigned NOT NULL,
				  me_notes text NOT NULL,
				  me_status set('pending','pendPayment','approved','denied','archived') NOT NULL DEFAULT 'pending',
				  PRIMARY KEY  (del_id),
				  FULLTEXT KEY ev_presenter (ev_presenter,ev_privateNotes,ev_publicEmail,ev_publicName,ev_submitter,ev_title,ev_website,ev_webText,ev_desc)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
				
		# Create table for meeting room containers. This is the top 
		# level for rooms, and is used in case you have rooms that
		# can be made of two or more other rooms.
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_roomConts (
				  roomCont_ID int(11) unsigned NOT NULL AUTO_INCREMENT,
				  roomCont_desc varchar(64) NOT NULL,
				  roomCont_branch int(11) NOT NULL,
				  roomCont_occ int(11) NOT NULL,
				  roomCont_isPublic tinyint(1) NOT NULL DEFAULT '1',
				  roomCont_hideDaily tinyint(1) NOT NULL DEFAULT '0',
				  PRIMARY KEY  (roomCont_ID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# create tale for room container members (which rooms are
		# in each room container
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_roomConts_members (
				  rcm_roomContID int(10) unsigned NOT NULL,
				  rcm_roomID int(10) unsigned NOT NULL,
				  KEY rcm_roomContID (rcm_roomContID,rcm_roomID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
		
		# Create table for meeting rooms		
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_rooms (
				  roomID int(11) unsigned NOT NULL AUTO_INCREMENT,
				  room_desc varchar(64) NOT NULL,
				  room_amenityArr text COMMENT 'This is a CSV array of amenities',
				  room_branchID int(11) unsigned NOT NULL,
				  PRIMARY KEY  (roomID),
				  KEY roomID (roomID)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		dbDelta( $sql );

		# Create table for times for each reservation
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_times (
				  ti_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				  ti_type enum('event','meeting') NOT NULL,
				  ti_extID int(10) unsigned NOT NULL,
				  ti_created timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  ti_startTime timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
				  ti_endTime timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
				  ti_roomID int(10) unsigned DEFAULT NULL,
				  ti_extraInfo text NOT NULL,
				  ti_noLocation_branch int(11) DEFAULT NULL,
				  ti_attendance int(11) unsigned DEFAULT NULL,
				  ti_attNotes text NOT NULL,
				  PRIMARY KEY  (ti_id),
				  KEY ti_type (ti_type),
				  KEY ti_id (ti_id,ti_type,ti_extID),
				  KEY ti_extID (ti_extID),
				  KEY ti_startTime (ti_startTime)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
		
		# Create table for deleted times for each reservation	
		$sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bookaroom_times_deleted (
				  del_id int(10) unsigned NOT NULL AUTO_INCREMENT,
				  del_changed timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				  ti_id int(10) unsigned NOT NULL,
				  ti_type enum('event','meeting') NOT NULL,
				  ti_extID int(10) unsigned NOT NULL,
				  ti_created timestamp NULL DEFAULT NULL,
				  ti_startTime timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
				  ti_endTime timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
				  ti_roomID int(10) unsigned DEFAULT NULL,
				  ti_extraInfo text NOT NULL,
				  ti_noLocation_branch int(11) DEFAULT NULL,
				  ti_attendance int(11) DEFAULT NULL,
				  ti_attNotes int(11) DEFAULT NULL,
				  PRIMARY KEY  (del_id)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";
		
		dbDelta( $sql );
				
		# TODO
		# add defaults only if empty
		# update the DB creation
		

		# defaults
		add_option( "bookaroom_db_version", $bookaroom_db_version );
		add_option( "bookaroom_alertEmail", '' );
		add_option( "bookaroom_bufferSize", '' );
		add_option( "bookaroom_content_contract", '' );
		add_option( "bookaroom_defaultEmailDaily", '' );
		add_option( "bookaroom_nonProfitDeposit", '' );
		add_option( "bookaroom_nonProfitIncrementPrice", '' );
		add_option( "bookaroom_eventLink", '' );
		add_option( "bookaroom_profitDeposit", '' );
		add_option( "bookaroom_profitIncrementPrice", '' );
		add_option( "bookaroom_baseIncrement", '30' );
		add_option( "bookaroom_cleanupIncrement", '1' );
		add_option( "bookaroom_reserveAllowed", '90' );
		add_option( "bookaroom_reserveBuffer", '2' );
		add_option( "bookaroom_reservedColor", '#448' );
		add_option( "bookaroom_reservedFont", '#FFF' );
		add_option( "bookaroom_setupColor", '#BBF' );
		add_option( "bookaroom_setupFont", '#000' );
		add_option( "bookaroom_setupIncrement", '0' );
		add_option( "bookaroom_waitingListDefault", '10' );
		add_option( "bookaroom_reservation_URL", '' );
		add_option( "bookaroom_installing", 'yes' );
		add_option( "bookaroom_daysBeforeRemind", '5' );
		add_option( 'bookaroom_paymentLink', '' );
		add_option( 'bookaroom_libcardRegex', '' );
		add_option( 'bookaroom_obfuscatePublicNames', '' );

		add_option( 'bookaroom_addressType', 'usa' );
		add_option( 'bookaroom_address1_name', 'Street Address 1' );
		add_option( 'bookaroom_address2_name', 'Street Address 2' );
		add_option( 'bookaroom_city_name', 'City' );
		add_option( 'bookaroom_state_name', 'State/Province/Territory' );
		add_option( 'bookaroom_defaultState_name', '' );
		add_option( 'bookaroom_zip_name', 'Post Code' );
		add_option( 'bookaroom_hide_contract', false );
		
		# searches
		add_option( 'bookaroom_search_events_page_num', '1' );
		add_option( 'bookaroom_search_events_per_page', '20' );
		add_option( 'bookaroom_search_events_order_by', 'event_id' );
		add_option( 'bookaroom_search_events_sort_order', 'desc' );
		
		# default mails
		add_option( 'bookaroom_newAlert_subject', 					'Confirmation of Meeting Room Request' );
		add_option( 'bookaroom_newInternal_subject', 				'Staff Reciept' );
		add_option( 'bookaroom_nonProfit_pending_subject',			'Meeting room request - 501(c)(3) information needed' );
		add_option( 'bookaroom_profit_pending_subject',				'Meeting room request - payment needed' );
		add_option( 'bookaroom_regChange_subject', 					'Event status change.' );
		add_option( 'bookaroom_requestAcceptedNonprofit_subject', 	'Request Accepted (Nonprofit)' );
		add_option( 'bookaroom_requestAcceptedProfit_subject', 		'Request Accepted (Profit)' );
		add_option( 'bookaroom_requestDenied_subject', 				'Request Denied' );
		add_option( 'bookaroom_requestPayment_subject', 			'Payment Received' );
		add_option( 'bookaroom_requestReminder_subject', 			'Request Reminder' );
		
		add_option( 'bookaroom_newAlert_body', 						"<h3>Confirmation of Meeting Room Request</h3>\r\n\r\nThank you. Your request has been submitted. This is just a confirmation that you have submitted a request.\r\n\r\nThis does not mean your request has been approved. We will notify you via email if your request has been accepted or denied.\r\n\r\nIf you are a nonprofit group, you will be required to send your 501(3)(c) documentation, otherwise, you will need to send your room fee to the following address, or stop into any branch.\r\n\r\nThe following payments are required:\r\nDeposit: $ {deposit}\r\nRoom Cost: $ {roomPrice}\r\nTotal: $ {totalPrice}\r\nYour total payment is due by {paymentDate}.\r\n\r\n\r\n<strong>Request</strong>: {eventName}\r\n<strong>Purpose</strong>:{desc}\r\n\r\n<strong>Branch name</strong>: {branchName}\r\n<strong>Room Name</strong>: {roomName}\r\n<strong>Date</strong>: {date}\r\n<strong>Time</strong>: {startTime} to {endTime}\r\n<strong>Event name</strong>:  {eventName}\r\n<strong>Number of attendees</strong>: {numAttend}\r\n<strong>Amenities</strong>: {amenity}\r\n<strong>Nonprofit?</strong>: {nonProfit}" );
		add_option( 'bookaroom_newInternal_body', 					"<h3>Confirmation of Meeting Room Request</h3>\r\nThank you. Your request has been submitted. Since this is a staff event, it is considered approved.\r\n\r\n<strong>Event Name</strong>: {eventName}\r\n<strong>Description</strong>:{desc}\r\n\r\n<strong>Branch name</strong>: {branchName}\r\n<strong>Room Name</strong>: {roomName}\r\n<strong>Date</strong>: {date}\r\n<strong>Time</strong>: {startTime} to {endTime}\r\n<strong>Event name</strong>:  {eventName}\r\n<strong>Number of attendees</strong>: {numAttend}\r\n<strong>Amenities</strong>: {amenity}\r\n<strong>Nonprofit?</strong>: {nonProfit}" );
		add_option( 'bookaroom_nonProfit_pending_body', 			"Your meeting room request is pending until we receive proper documentation of 501(c)(3) status. Please note that we need the IRS letter that confirms your group's nonprofit status, not the EIN or State Tax Exempt form.\r\n\r\nYour nonprofit information is due before {paymentDate}\r\n" );
		add_option( 'bookaroom_profit_pending_body', 				"Your meeting room request is pending until we receive payment. Your payment is due before {paymentDate}\r\n" );
		add_option( 'bookaroom_regChange_body', 					"Due to a cancellation, you have been moved from the waiting list and can attend the following event:\r\n\r\n{eventName}\r\n{date} at {startTime} at {branchName}" );
		add_option( 'bookaroom_requestAcceptedNonprofit_body', 		"Your meeting room request has been accepted for event: {desc} on {date}.\r\n\r\n<strong>Address:</strong> {contactAddress1}\r\n<strong>Address 2:</strong> {contactAddress2}\r\n<strong>Amenities:</strong> {amenity}\r\n<strong>Branch Name:</strong> {branchName}\r\n<strong>City:</strong> {contactCity}\r\n<strong>Contact Name:</strong> {contactName}\r\n<strong>Date:</strong> {date}\r\n<strong>Email:</strong> {contactEmail}\r\n<strong>End Time:</strong> {endTime}\r\n<strong>Event Name:</strong> {eventName}\r\n<strong>Nonprofit:</strong> {nonProfit}\r\n<strong>Number of Attendees:</strong> {numAttend}\r\nstrong>Primary Phone:</strong> {contactPhonePrimary}\r\n<strong>Purpose:</strong> {desc}\r\n<strong>Room Name:</strong> {roomName}\r\n<strong>Secondary Phone:</strong> {contactPhoneSecondary}\r\n<strong>Start Time:</strong> {startTime}\r\n<strong>State:</strong> {contactState}\r\n<strong>Zip:</strong> {contactZip}" );
		add_option( 'bookaroom_requestAcceptedProfit_body', 		"Your meeting room request has been accepted for event: {desc} on {date}.\r\n\r\n<strong>Date:</strong> {date}\r\n<strong>Email:</strong> {contactEmail}\r\n<strong>End Time:</strong> {endTime}\r\n<strong>Event Name:</strong> {eventName}\r\n<strong>Nonprofit:</strong> {nonProfit}\r\n<strong>Number of Attendees:</strong> {numAttend}\r\n<strong>Primary Phone:</strong> {contactPhonePrimary}\r\n<strong>Purpose:</strong> {desc}\r\n<strong>Room Name:</strong> {roomName}\r\n<strong>Secondary Phone:</strong> {contactPhoneSecondary}\r\n<strong>Start Time:</strong> {startTime}\r\n<strong>State:</strong> {contactState}\r\n<strong>Zip:</strong> {contactZip}\r\n\r\n<strong>Room Deposit</strong>: \${roomDeposit}\r\n<strong>Room Cost</strong>: \${roomPrice}\r\n<strong>Total Cost</strong>: \${totalPrice}\r\nYour total payment is due by {paymentDate}." );
		add_option( 'bookaroom_requestDenied_body', 				"Your meeting room request has been denied for event: {desc} on {date}.\r\n\r\nIf you have questions please contact us" );
		add_option( 'bookaroom_requestPayment_body', 				"Payment has been received and your meeting room request is approved and completed." );
		add_option( 'bookaroom_requestReminder_body', 				"Request Reminder body" );
		
		
	
	}

	public static function plugin_activation_message()
	{
		global $bookaroom_db_version;

		if( get_option( "bookaroom_installing" ) == 'yes' ):
			update_option( 'bookaroom_installing', 'no' );
			$html = '<div class="updated"><p>(Please install the events calendar plugin. You will not be able to view the calendar until it is installed and configured.)</p>
<p>To set up your meeting rooms, first click on Meeting Room Settings on the left hand menu. There are descriptions of each option at the bottom of the page.</p>
<p>Next, set up your amenities. These should include any extras that can be reserved with the room like coffee urns, dry erase boards and projectors.</p>
<p>Once you\'ve got your amenities set, add your Branches. This is also where you configure the hours, address and image for each branch.</p>
<p>Next, add in your Rooms. A room is a <em><strong>physical</strong></em> space that can be reserved. If you have 2 meetings rooms, even if they can be reserved together, you would only add the two physical locations as a room.</p>
<p>Finally, add in your Room Containers. Room containers are <em><strong>virtual</strong></em> spaces that are actually being reserved. If you have two rooms that can be reserved separately or together as one larger space, you would add 3 containers; two would each contain one room and the third would contain both rooms.</p>
<p>To configure alerts and content, make sure you edit the Email Admin and Content Admin!</p></div><!-- /.updated -->';
			echo $html;
		endif;
	}
	
    public static function on_deactivate()
	# this is only run when hooked by de-activating plugin
    {
		# TODO fix deactivation and uninstall
		#update_option( "bookaroom_installing", 'yes' );
		


    }

    public static function on_uninstall()
	# this is only run when hooked by uninstalling plugin
    {
        // important: check if the file is the one that was registered with the uninstall hook (function)
       
		#if ( __FILE__ != WP_UNINSTALL_PLUGIN )
        #    return;

		global $wpdb;
		global $bookaroom_db_version;
	
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_amenities" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_branches" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_cityList" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_closings" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_eventAges" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_eventCats" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_event_ages" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_event_categories" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_registrations" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_reservations" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_reservations_deleted" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_roomConts" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_roomConts_members" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_rooms" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_times" );
		$wpdb->query( "DROP TABLE {$wpdb->prefix}bookaroom_times_deleted" );
				
		delete_option( "bookaroom_db_version" );
		delete_option( "bookaroom_alertEmail" );
		delete_option( "bookaroom_bufferSize" );
		delete_option( "bookaroom_content_contract" );
		delete_option( "bookaroom_defaultEmailDaily" );
		delete_option( "bookaroom_nonProfitDeposit" );
		delete_option( "bookaroom_nonProfitIncrementPrice" );
		delete_option( "bookaroom_eventLink" );
		delete_option( "bookaroom_profitDeposit" );
		delete_option( "bookaroom_profitIncrementPrice" );
		delete_option( "bookaroom_baseIncrement" );
		delete_option( "bookaroom_cleanupIncrement" );
		delete_option( "bookaroom_reserveAllowed" );
		delete_option( "bookaroom_reserveBuffer" );
		delete_option( "bookaroom_reservedColor" );
		delete_option( "bookaroom_reservedFont" );
		delete_option( "bookaroom_setupColor" );
		delete_option( "bookaroom_setupFont" );
		delete_option( "bookaroom_setupIncrement" );
		delete_option( "bookaroom_waitingListDefault" );
		delete_option( "bookaroom_reservation_URL" );
		delete_option( "bookaroom_daysBeforeRemind" );
		
		delete_option( 'bookaroom_addressType' );
		delete_option( 'bookaroom_address1_name' );
		delete_option( 'bookaroom_address2_name' );
		delete_option( 'bookaroom_city_name' );
		delete_option( 'bookaroom_state_name' );
		delete_option( 'bookaroom_zip_name' );
		

		delete_option( 'bookaroom_newAlert_subject' );
		delete_option( 'bookaroom_newInternal_subject' );
		delete_option( 'bookaroom_nonProfit_pending_subject' );
		delete_option( 'bookaroom_profit_pending_subject' );
		delete_option( 'bookaroom_regChange_subject' );
		delete_option( 'bookaroom_requestAcceptedNonprofit_subject' );
		delete_option( 'bookaroom_requestAcceptedProfit_subject' );
		delete_option( 'bookaroom_requestDenied_subject' );
		delete_option( 'bookaroom_requestPayment_subject' );
		delete_option( 'bookaroom_requestReminder_subject' );
		delete_option( 'bookaroom_paymentLink' );
		delete_option( 'bookaroom_libcardRegex' );
		delete_option( 'bookaroom_obfuscatePublicNames' );
		
		delete_option( 'bookaroom_newAlert_body' );
		delete_option( 'bookaroom_newInternal_body' );
		delete_option( 'bookaroom_nonProfit_pending_body' );
		delete_option( 'bookaroom_profit_pending_body' );
		delete_option( 'bookaroom_regChange_body' );
		delete_option( 'bookaroom_requestAcceptedNonprofit_body' );
		delete_option( 'bookaroom_requestAcceptedProfit_body' );
		delete_option( 'bookaroom_requestDenied_body' );
		delete_option( 'bookaroom_requestPayment_body' );
		delete_option( 'bookaroom_requestReminder_body' );
		
		delete_option( 'bookaroom_search_events_page_num' );
		delete_option( 'bookaroom_search_events_per_page' );
		delete_option( 'bookaroom_search_events_order_by' );
		delete_option( 'bookaroom_search_events_sort_order' );
		
    }

}


class bookaroom_settings
# main settings functions
{
	public static function add_settingsPage()
	{
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-admin.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-content.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-email.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-meetingsSearch.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-events.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-events-manage.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-events-staff.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-reports.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-customerSearch.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-help.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-cityManagement.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-events-manage-age.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-events-manage-categories.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-locale.php' );
		
		
		# Room management pages

		$pendingList = book_a_room_meetings::getPending();
		$pendingCount = !empty( $pendingList['status']['pending'] ) ? count( $pendingList['status']['pending'] ) : 0;
		$pendingPayCount = !empty( $pendingList['status']['pendPayment'] ) ? count( $pendingList['status']['pendPayment'] ) : 0;
		$pending501C3Count = !empty( $pendingList['status']['501C3'] ) ? count( $pendingList['status']['501C3'] ) : 0;
		$deniedCount = !empty( $pendingList['status']['denied'] ) ? count( $pendingList['status']['denied'] ) : 0;
		$approvedCount = !empty( $pendingList['status']['approved'] ) ? count( $pendingList['status']['approved'] ) : 0;

		# create and event
		add_menu_page( 'Book a Room Event Management', 'Create/Manage Events', 'read', 'bookaroom_event_management',  array( 'bookaroom_events', 'bookaroom_adminEvents' ), '', 200 );
		add_submenu_page( 'bookaroom_event_management', 'Create/Manage Events', 'Create Event', 'read', 'bookaroom_event_management',  array( 'bookaroom_events', 'bookaroom_adminEvents' ) );
		add_submenu_page( 'bookaroom_event_management', 'Create/Manage Events', 'Manage Events', 'read', 'bookaroom_event_management_upcoming',  array( 'bookaroom_events_manage', 'bookaroom_manageEvents' ) );
		add_submenu_page( 'bookaroom_event_management', 'Search Registrations', 'Search Registrations', 'read', 'bookaroom_event_management_customerSearch',  array( 'bookaroom_customerSearch', 'bookaroom_findUser' ) );
		add_submenu_page( 'bookaroom_event_management', 'View Staff Events', 'View Staff Events', 'read', 'bookaroom_event_management_staff',  array( 'bookaroom_events_staff', 'bookaroom_staffCalendar' ) );
		
		# manage reservations
		add_menu_page( 'Book a Room Management', 'Manage Reservations', 'read', 'bookaroom_meetings', array( 'book_a_room_meetings', 'bookaroom_pendingRequests' ) );
		#add_submenu_page( 'bookaroom_meetings', "", '<span style="display:block; margin:1px 0 1px -5px; padding:0; height:2px; line-height:1px; background:#DDD;"></span>', "manage_options", "#" );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Pending Requests', "Pending [{$pendingCount}]", 'read', 'bookaroom_meetings',  array( 'book_a_room_meetings', 'bookaroom_pendingRequests' ) );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Pending Payments', "Pend. Payments [{$pendingPayCount}]", 'read', 'bookaroom_meetings_pendingPayment',  array( 'book_a_room_meetings', 'bookaroom_pendingPayment' ) );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Pending 501(c)3', "Pend. 501(c)3 [{$pending501C3Count}]", 'read', 'bookaroom_meetings_pending501C3',  array( 'book_a_room_meetings', 'bookaroom_pending501C3' ) );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Approved Requests', "Approved [{$approvedCount}]", 'read', 'bookaroom_meetings_approvedRequests',  array( 'book_a_room_meetings', 'bookaroom_approvedRequests' ) );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Denied Requests', "Denied [{$deniedCount}]", 'read', 'bookaroom_meetings_deniedRequests',  array( 'book_a_room_meetings', 'bookaroom_deniedRequests' ) );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Archived Requests', 'Archived', 'read', 'bookaroom_meetings_archivedRequests',  array( 'book_a_room_meetings', 'bookaroom_archivedRequests' ) );
		add_submenu_page( 'bookaroom_meetings', 'Meeting Room Meetings - Search Requests', 'Search', 'read', 'bookaroom_meetings_search',  array( 'book_a_room_meetingsSearch', 'bookaroom_searchRequests' ) );
		
		# Reports		
		#add_submenu_page( 'bookaroom_meetings', "", '<span style="display:block; margin:1px 0 1px -5px; padding:0; height:2px; line-height:1px; background:#DDD;"></span>', "read", "#" );
		#add_submenu_page( 'bookaroom_meetings', 'Meeting Room Settings - Reports', 'Reports', 'manage_options', 'bookaroom_settings_reports',  array( 'bookaroom_reports', 'bookaroom_reportsAdmin' ) );

		# Daily Schedule
		add_menu_page( 'Book a Room Daily Schedules', 'Daily Schedules', 'manage_options', 'bookaroom_daily_schedules', array( 'book_a_room_meetings', 'bookaroom_contactList' )  );

		add_submenu_page( 'bookaroom_daily_schedules', 'Meeting Room Meetings - Contact List', 'Contact List', 'read', 'bookaroom_daily_schedules',  array( 'book_a_room_meetings', 'bookaroom_contactList' ) );
		add_submenu_page( 'bookaroom_daily_schedules', 'Meeting Room Meetings - Daily Meetings', 'Daily Meetings', 'read', 'bookaroom_daily_schedules_meetings',  array( 'book_a_room_meetings', 'bookaroom_dailyMeetings' ) );
add_submenu_page( 'bookaroom_daily_schedules', 'Meeting Room Meetings - Daily Room Signs', 'Daily Room Signs', 'read', 'bookaroom_daily_schedules_signs',  array( 'book_a_room_meetings', 'bookaroom_dailyRoomSigns' ) );

#		add_submenu_page( 'bookaroom_meetings', "", '<span style="display:block; margin:1px 0 1px -5px; padding:0; height:2px; line-height:1px; background:#DDD;"></span>', "read", "#" );

		# Manage events
		add_menu_page( 'Book a Room Event Settings', 'Event Settings', 'manage_options', 'bookaroom_event_settings', array( 'bookaroom_settings_age', 'showFormAge' ) );
		
		add_submenu_page( 'bookaroom_event_settings', 'Manage Age Groups', 'Manage Age Groups', 'manage_options', 'bookaroom_event_settings',  array( 'bookaroom_settings_age', 'showFormAge' ) );

		add_submenu_page( 'bookaroom_event_settings', 'Manage Categories', 'Manage Categories', 'manage_options', 'bookaroom_event_settings_categories',  array( 'bookaroom_settings_categories', 'showFormCategories' ) );
				
		# Manage Meeting Room settings
		add_menu_page( 'Book a Room Settings', 'Meeting Room Settings', 'manage_options', 'bookaroom_Settings', array( 'bookaroom_settings_admin', 'bookaroom_admin_admin' ) );

		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings', 'Settings', 'manage_options', 'bookaroom_Settings',  array( 'bookaroom_settings_admin', 'bookaroom_admin_admin' ) );
		
		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Amenities', 'Amenities Admin', 'manage_options', 'bookaroom_Settings_Amenities',  array( 'bookaroom_settings_amenities', 'bookaroom_admin_amenities' ) );
		
		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Branches', 'Branch Admin', 'manage_options', 'bookaroom_Settings_Branches',  array( 'bookaroom_settings_branches', 'bookaroom_admin_branches' ) );

		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Rooms', 'Room Admin', 'manage_options', 'bookaroom_Settings_Rooms',  array( 'bookaroom_settings_rooms', 'bookaroom_admin_rooms' ) );

		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Room Containers', 'Containers Admin', 'manage_options', 'bookaroom_Settings_RoomCont',  array( 'bookaroom_settings_roomConts', 'bookaroom_admin_roomCont' ) );
				
		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - City Management', 'City Admin', 'manage_options', 'bookaroom_Settings_cityManagement',  array( 'bookaroom_settings_cityManagement', 'bookaroom_admin_mainCityManagement' ) );
				
		add_submenu_page( 'bookaroom_Settings', "", '<span style="display:block; margin:1px 0 1px -5px; padding:0; height:2px; line-height:1px; background:#DDD;"></span>', "manage_options", "#" );
		
		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Email', 'Email Admin', 'manage_options', 'bookaroom_Settings_Email',  array( 'bookaroom_settings_email', 'bookaroom_admin_email' ) );
		
		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Content', 'Content Admin', 'manage_options', 'bookaroom_Settings_Content',  array( 'bookaroom_settings_content', 'bookaroom_admin_content' ) );

		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Closings', 'Closings Admin', 'manage_options', 'bookaroom_Settings_Closings',  array( 'bookaroom_settings_closings', 'bookaroom_admin_closings' ) );
		
		add_submenu_page( 'bookaroom_Settings', 'Meeting Room Settings - Locale', 'Locale Admin', 'manage_options', 'bookaroom_Settings_Locale',  array( 'bookaroom_settings_locale', 'bookaroom_admin_locale' ) );


		# Help files
		add_menu_page( 'Bookaroom Help', 'Bookaroom Help', 'manage_options', 'Bookaroom_Help', array( 'bookaroom_help', 'showHelp' ) );

		add_submenu_page( 'Bookaroom_Help', 'Meeting Room Help', 'Main Help', 'manage_options', 'Bookaroom_Help',  array( 'bookaroom_help', 'showHelp' ) );
		
		#add_submenu_page( 'Bookaroom_Help', 'Meeting Room Help', 'Setup Help', 'manage_options', 'Bookaroom_Help_Setup',  array( 'bookaroom_help', 'showHelp_setup' ) );
		#initialize		
		add_action( 'admin_init', array( 'bookaroom_settings', 'bookaroom_init' ) );
	}
	

	
	public static function bookaroom_init()
	{
		register_setting( 'bookaroom_options', 'bookaroom_alertEmail' );
		register_setting( 'bookaroom_options', 'bookaroom_baseIncrement' );
		register_setting( 'bookaroom_options', 'bookaroom_baseIncrement' );
		register_setting( 'bookaroom_options', 'bookaroom_bufferSize' );
		register_setting( 'bookaroom_options', 'bookaroom_cleanupIncrement' );
		register_setting( 'bookaroom_options', 'bookaroom_reserveAllowed' );
		register_setting( 'bookaroom_options', 'bookaroom_reserveBuffer' );
		register_setting( 'bookaroom_options', 'bookaroom_reservedColor' );
		register_setting( 'bookaroom_options', 'bookaroom_reservedFont' );
		register_setting( 'bookaroom_options', 'bookaroom_setupColor' );
		register_setting( 'bookaroom_options', 'bookaroom_setupFont' );
		register_setting( 'bookaroom_options', 'bookaroom_setupIncrement' );
		register_setting( 'bookaroom_options', 'bookaroom_reservation_URL' );
		register_setting( 'bookaroom_options', 'bookaroom_defaultEmailDaily' );
		register_setting( 'bookaroom_options', 'bookaroom_daysBeforeRemind' );
		
		register_setting( 'bookaroom_options', 'bookaroom_profitDeposit' );
		register_setting( 'bookaroom_options', 'bookaroom_nonProfitDeposit' );
		register_setting( 'bookaroom_options', 'bookaroom_profitIncrementPrice' );
		register_setting( 'bookaroom_options', 'bookaroom_nonProfitIncrementPrice' );
		
		register_setting( 'bookaroom_options', 'bookaroom_eventLink' );
		
		register_setting( 'bookaroom_options', 'bookaroom_content_contract' );
	
		
	}
	
	public static function checkID( $curID, $itemList, $multi = FALSE )
	# make sure that ID isn't empty and that there is a corresponding
	# entry in the branch list
	{
		if( empty( $curID ) or empty( $itemList ) or count( $itemList ) == 0 ):
			return FALSE;
		endif;
		
		# if multidimensional, run on each sub array
		if( $multi == TRUE ):
			$isInArray = FALSE;
			foreach( $itemList as $key => $val ):
				# is it in there?
				if( array_key_exists( $curID, $val ) ):
					$isInArray = TRUE;
				endif;
			endforeach;
			
			# if not found, return FALSE
			if( $isInArray == FALSE ):
				return FALSE;
			endif;
		else:
		# single dimensional array gets simple array search
			if( !array_key_exists( $curID, $itemList ) ):
				return FALSE;
			endif;
		endif;
		return TRUE;
	}
	
	public static function dupeCheck( $itemList, $itemDesc, $itemID = NULL )
	# check for duplicate item name
	# if item ID is entered, ignore dupe for that ID so that,
	# when editing, you can you can use the same name as the currently
	# edited item
	{
		if( !is_array( $itemList ) ):
			$final = FALSE;
		elseif( ( $foundID = array_search( strtolower( $itemDesc ), array_map( 'strtolower', $itemList ) ) ) == FALSE ):
			$final = FALSE;
		elseif( !is_null( $itemID ) and $itemID == $foundID ):
			$final = FALSE;
		else:
			$final = TRUE;
		endif;
		
		return $final;
		
	}
		
	public static function showMessage( $messagePage )
	{
		# get template
		$filename = BOOKAROOM_PATH . "templates/{$messagePage}.html";
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
		
	}
}

if( !function_exists( "preme" ) ):
	function preme( $arr="-----------------+=+-----------------" ) // print_array
	{
		if( $arr === TRUE )	$arr = "**TRUE**";
		if( $arr === FALSE )	$arr = "**FALSE**";
		if( $arr === NULL )	$arr = "**NULL**";
		
		echo "<pre>";
		print_r( $arr );
		echo "</pre>";
	
	}
endif;
if( !function_exists( "repCon" ) ):
	function repCon( $name, &$contents, $returnNull = FALSE )
	{
		$start			= '#' . $name . '_start#';
		$end			= '#' . $name . '_end#';
		$replacement	= ( $returnNull == FALSE ) ? '#' . $name . '_line#' : NULL;
		
		//
		// find the beginning of the chunk based on $start
		$head = stristr( $contents, $start );
		// first, find the end of the chunk, then find how many characters are after
		// the chunk
		$foot = strlen( stristr( $head, $end ) )-strlen( $end );
		// find the length of the head
		$length = strlen( $head );
		// the final is the whole chunk including $start and $end
		$final = substr( $head, 0, $length - $foot );
		// display is the string INSIDE $start and $end and will be returned
		$display = substr( $final, strlen( $start ), -strlen( $end ) );
		// replace the chunk with $replacement in the search string
		$contents = str_replace( $final, $replacement, $contents );
		
		return $display;
	}
endif;
	if( !function_exists( "myStartSession" ) ):
	function myStartSession() {
		if(!session_id()) {
			session_start();
		}
	}
endif;
	if( !function_exists( "myEndSession" ) ):
	function myEndSession() {
		session_destroy ();
	}
endif;
	if( !function_exists( "encrypt" ) ):
	function encrypt($input_string, $key){
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', $key, TRUE);
		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $h_key, $input_string, MCRYPT_MODE_ECB, $iv));
	}
endif;
if( !function_exists( "decrypt" ) ):
	function decrypt($encrypted_input_string, $key){
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$h_key = hash('sha256', $key, TRUE);
		return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $h_key, base64_decode($encrypted_input_string), MCRYPT_MODE_ECB, $iv));
	}
endif;
function CHUH_BaR_Main_permalinkFix( $onlyID = false )
{
	$urlInfoRaw = parse_url( get_permalink() );
			
	$urlInfo = (!empty( $urlInfoRaw['query'] ) ) ? $urlInfoRaw['query'] : NULL;
	if( $onlyID ):
		$finalArr = explode( '=', $urlInfo );
		if( !empty( $finalArr[1] ) ):
			return $finalArr[1];
		else:
			return NULL;
		endif;
		
	endif;
	
	if( empty( $urlInfo ) ):
		$permalinkCal = '?';
	else:
		$permalinkCal = '?'.$urlInfo.'&';
	endif;
	
	return $permalinkCal;	
}
?>