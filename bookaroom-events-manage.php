<?PHP
class bookaroom_events_manage
{
	var $rowCount;
	
	public static function bookaroom_manageEvents()
	{
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-closings.php' );
		#require_once( BOOKAROOM_PATH . 'bookaroom-events-settings.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();

		# first, is there an action? 
		$externals = self::getExternals();
		
		switch( $externals['action'] ):
			case 'filterResults':
				$_SESSION['bookaroom_temp_search_settings'] = $externals;
				
				if( !empty( $externals['submit_count'] ) ):
					update_option( 'bookaroom_search_events_page_num', 1 );
					update_option( 'bookaroom_search_events_per_page', $externals['submit_count'] );
				endif;
				
				if( !empty( $externals['submit'] ) and $externals['submit'] == 'Submit' ):
					update_option( 'bookaroom_search_events_page_num', 1 );
				endif;

				if( !empty( $externals['pageNum'] ) ):
					update_option( 'bookaroom_search_events_page_num', $externals['pageNum'] );
				endif;
				
				if( !empty( $externals['submit_nav'] ) ):
					$pageNum = get_option( 'bookaroom_search_events_page_num');
					
					switch( $externals['submit_nav'] ):
						case 'Prev':
							update_option( 'bookaroom_search_events_page_num', $pageNum - 1 );
							break;
						case 'Next':
							update_option( 'bookaroom_search_events_page_num', $pageNum + 1 );	
							break;
					endswitch;
				endif;		
				$externals['hideSearch'] = true;				
				$results = self::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );
				self::manageEvents( $externals, $results, true );
				break;
				
			default:
				$externals['hideSearch'] = false;
				$_SESSION['bookaroom_meetings_hash'] = NULL;
				$_SESSION['bookaroom_meetings_externalVals'] = NULL;
				update_option( 'bookaroom_search_events_page_num', 1 );
				self::manageEvents( $externals );
				break;
		endswitch;
		
	}
	
	protected static function checkID( $eventID )
	{
		# look up ID in database
		global $wpdb;
		
		$sql = "SELECT `ti`.`ti_id`, `ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_type`, `ti`.`ti_roomID`, `res`.`res_id`, `res`.`ev_desc`, 
					`res`.`ev_maxReg`, `res`.`ev_waitingList`, `res`.`ev_presenter`, `res`.`ev_privateNotes`, `res`.`ev_publicEmail`, 
					`res`.`ev_publicName`, `res`.`ev_publicPhone`, `res`.`ev_noPublish`, `res`.`ev_regStartDate`, `res`.`ev_regType`, 
					`res`.`ev_submitter`, `res`.`ev_title`, `res`.`ev_website`, `res`.`ev_webText`  
				FROM `{$wpdb->prefix}bookaroom_times` AS `ti`
				LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` AS `res` ON `ti`.`ti_extID` = `res`.`res_id` 
				WHERE `ti`.`ti_id` = '{$eventID}'";
		
		
		$cooked = $wpdb->get_row( $sql, ARRAY_A );
		if( $cooked == NULL ):
			return false;
		endif;
		
		return $cooked;
	}
	
	public static function getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList )
	{
		global $wpdb;
		global $rowCount;
		
		$where = array();
		$where[] = '`ti`.`ti_type` = "event"';
		$whereFinal = NULL;
		
		$page_num		= self::get_clean_option( 'bookaroom_search_events_page_num' );
		$per_page		= self::get_clean_option( 'bookaroom_search_events_per_page' );
		$order_by		= self::get_clean_option( 'bookaroom_search_events_order_by' );
		$sort_order		= self::get_clean_option( 'bookaroom_search_events_sort_order' );
		
		# age group
		if( !empty( $externals['regType'] ) ):
			if( $externals['regType'] == 'staff' ):
				$where[] = "`res`.`ev_regType` = 'staff'";				
			elseif( $externals['regType'] == 'yes' ):
				$where[] = "`res`.`ev_regType` = 'yes'";
			endif;
		endif;
		
		# age group
		if( !empty( $externals['ageGroup'] ) ):
			$where[] = "`ages`.`ea_ageID` IN (".implode( ',', $externals['ageGroup'] ).")";
		endif;
		
		# category group
		if( !empty( $externals['categoryGroup'] ) ):
			$where[] = "`cats`.`ec_catID` IN (".implode( ',', $externals['categoryGroup'] ).")";
		endif;		
		
		# check for no location
		if( !empty( $externals['noloc-branchID'] ) and array_key_exists( $externals['noloc-branchID'], $branchList ) ):
			# find all rooms in branch
			$where[] = "(`ti`.`ti_noLocation_branch` = '{$externals['noloc-branchID']}')";
		endif;
		
		# check for room
		if( !empty( $externals['roomID'] ) and array_key_exists( $externals['roomID'], $roomContList['id'] ) ):
			# find all rooms in branch
			$where[] = "(`ti`.`ti_roomID` = '{$externals['roomID']}')";
		endif;
		
		# check for branch
		if( !empty( $externals['branchID'] ) and array_key_exists( $externals['branchID'], $branchList ) ):
			# find all rooms in branch
			$branchArr = implode( ',',  $roomContList['branch'][$externals['branchID']] );
			$where[] = "(`ti`.`ti_roomID` IN ( {$branchArr} ) or `ti`.`ti_noLocation_branch` = '{$externals['branchID']}')";
		endif;
		
		# start time
		if( !empty( $externals['startDate'] ) and ( $startTimestamp =  date( 'Y-m-d H:i:s', strtotime( $externals['startDate'] ) ) ) !== false ):
			$where[] = "`ti`.`ti_startTime` >= '{$startTimestamp}'";
		endif;

		# end time
		if( !empty( $externals['endDate'] ) and ( $endTimestamp =  date( 'Y-m-d H:i:s', strtotime( $externals['endDate']." + 1 days") ) ) !== false ):
			$where[] = "`ti`.`ti_endTime` <= '$endTimestamp'";
		endif;

		# search term
		if( !empty( $externals['searchTerms'] ) ):
			$where[] = " MATCH ( `res`.`ev_desc`, `res`.`ev_presenter`, `res`.`ev_privateNotes`, `res`.`ev_publicEmail`, `res`.`ev_publicName`, `res`.`ev_submitter`, `res`.`ev_title`, `res`.`ev_website`, `res`.`ev_webText` ) AGAINST ('{$externals['searchTerms']}' IN NATURAL LANGUAGE MODE )";
			$scoreWhere = "`score` DESC, ";
		else:
			$scoreWhere = NULL;
		endif;

		# check for non-published
		if( $externals['published'] == 'noPublished' ):
			$where[] = "`res`.`ev_noPublish` = 1";			
		elseif( $externals['published'] == 'published' ):
			$where[] = "`res`.`ev_noPublish` = 0";
		endif;
		
		#  check for and build WHERE statment
		if( count( $where ) > 0 ):
			$whereFinal = 'WHERE '.implode( ' AND ', $where );
		endif;

		# build limit on page numbers
		$limit = NULL;
		
		if( $per_page !== 'All' ):
			if( ( $page_num - 1 ) * $per_page  < 1 ):
				$limit = ' LIMIT 0, '.$per_page;
			else:
				$limit = ' LIMIT '.( ( ( $page_num - 1 ) * $per_page ) ).', '.$per_page;
			endif;
		endif;
		
		# sort type
		$sql = "SELECT SQL_CALC_FOUND_ROWS MATCH ( `res`.`ev_desc`, `res`.`ev_presenter`, `res`.`ev_privateNotes`, `res`.`ev_publicEmail`, `res`.`ev_publicName`, `res`.`ev_submitter`, `res`.`ev_title`, `res`.`ev_website`, `res`.`ev_webText` ) AGAINST ('{$externals['searchTerms']}' IN NATURAL LANGUAGE MODE ) as `score`, 
		`ti`.`ti_id`, `ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_type`, `res`.`ev_title`, `res`.`ev_desc`, `ti`.`ti_roomID`, `ti`.`ti_noLocation_branch`, `ti`.`ti_extraInfo`, `res`.`ev_regType`, `res`.`ev_maxReg`, `res`.`ev_noPublish`, COUNT( DISTINCT `tiCount`.`ti_id` ) as eventCount, `res`.`res_id`
					FROM `{$wpdb->prefix}bookaroom_times` AS `ti`
					LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` AS `res` ON `res`.`res_id` = `ti`.`ti_extID`
					LEFT JOIN `{$wpdb->prefix}bookaroom_times` AS `tiCount` ON `tiCount`.`ti_extID` = `res`.`res_id` 
					LEFT JOIN `{$wpdb->prefix}bookaroom_eventAges` AS `ages` ON `ages`.`ea_eventID` = `res`.`res_id`
					LEFT JOIN `{$wpdb->prefix}bookaroom_eventCats` AS `cats` ON `cats`.`ec_eventID` = `res`.`res_id`

					{$whereFinal}
					GROUP BY `ti`.`ti_id`
					ORDER BY {$scoreWhere}`ti`.`ti_startTime`, `res`.`ev_title` 
					{$limit}";
		$final['results'] = $cooked = $wpdb->get_results( $sql, ARRAY_A );
		$rowCountArr = $wpdb->get_row( 'SELECT FOUND_ROWS() as rowcount' );
		$final['rowCount'] = $rowCountArr->rowcount;
				
		return $final;
		
	}
	
	protected static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'					=> FILTER_SANITIZE_STRING,
							'addDateVals'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'endDate'					=> FILTER_SANITIZE_STRING, 
							'eventID'					=> FILTER_SANITIZE_STRING, 
							'published'					=> FILTER_SANITIZE_STRING, 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'searchTerms'				=> FILTER_SANITIZE_STRING, 
							'startDate'					=> FILTER_SANITIZE_STRING,
							);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final = array_merge( $final, $getTemp );
		endif;
		
		# setup POST variables
		$postArr = array(	
							'action'					=> FILTER_SANITIZE_STRING,
							'addDateVals'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ),
							'ageGroup'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'categoryGroup'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'endDate'					=> FILTER_SANITIZE_STRING, 
							'eventID'					=> FILTER_SANITIZE_STRING, 
							'pageNum'					=> FILTER_SANITIZE_STRING, 
							'published'					=> FILTER_SANITIZE_STRING, 
							'regType'					=> FILTER_SANITIZE_STRING, 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'searchTerms'				=> FILTER_SANITIZE_STRING, 
							'startDate'					=> FILTER_SANITIZE_STRING,
							'submit'					=> FILTER_SANITIZE_STRING,
							'submit_count'				=> FILTER_SANITIZE_STRING,
							'submit_nav'				=> FILTER_SANITIZE_STRING,
							);
			
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) ):
			$final = array_merge( $final, $postTemp );
		endif;

		$arrayCheck = array_unique( array_merge( array_keys( $postArr ), array_keys( $getArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			elseif( is_array( $final[$key] ) ):
				$final[$key] = $final[$key];
			else:			
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;
		 
		if( !empty( $final['roomID'] ) && substr( $final['roomID'], 0, 7 ) == 'branch-' ):
			
			$final['branchID'] = substr( $final['roomID'], 7 );
			$final['roomID'] = NULL;
		else:
			$final['branchID'] = NULL;
		endif;
		
		if( !empty( $final['roomID'] ) && substr( $final['roomID'], 0, 6 ) == 'noloc-' ):
			$final['noloc-branchID'] = substr( $final['roomID'], 6 );
			$final['roomID'] = NULL;
		else:
			$final['noloc-branchID'] = NULL;
		endif;

		return $final;
	}
	
	protected static function getRegInfo( $eventID  )
	{
		global $wpdb;
		
		$sql = "	SELECT `reg_id`, `reg_fullName`, `reg_phone`, `reg_email`, `reg_notes`, `reg_dateReg` 
					FROM `{$wpdb->prefix}bookaroom_registrations` 
					WHERE `reg_eventID` = '{$eventID}' 
					ORDER BY `reg_dateReg`";

		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		return $cooked;
	}
	
	public static function manageEvents( $externals, $resultsArr =  array( 'results' => array(), 'rowCount' => false ), $searched = false, $errorMSG = NULL )
	{

		$results	= $resultsArr['results'];
		$rowCount	= $resultsArr['rowCount'];

		
		$page_num		= self::get_clean_option( 'bookaroom_search_events_page_num' );
		$per_page		= self::get_clean_option( 'bookaroom_search_events_per_page' );
		
		if( $per_page == 'All' ):
			$totalPages 	= 1;
		else:
			$totalPages 	= ceil( $rowCount / $per_page );
		endif;
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		# get template		
		$filename = BOOKAROOM_PATH . 'templates/events/eventList.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		# hide search
		if( $externals['hideSearch'] ):
			$contents = str_replace( '#hideSearch#', 'true', $contents );
		else:
			$contents = str_replace( '#hideSearch#', 'false', $contents );
		endif;
		# hide previous button if at start
		if( $page_num <= 1 ):
			$contents = str_replace( '#submit_prev_disabled#', ' disabled', $contents );
		else:
			$contents = str_replace( '#submit_prev_disabled#', NULL, $contents );
		endif;

		# hide next button if at end
		if( $page_num >= $totalPages ):
			$contents = str_replace( '#submit_next_disabled#', ' disabled', $contents );
		else:
			$contents = str_replace( '#submit_next_disabled#', NULL, $contents );
		endif;

			
		if( $page_num < 1 ):
			$page_num = 1;
		elseif( $page_num > $totalPages ):
			$page_num = $totalPages;
		endif;

		if( false == ( $eventLink = get_option( "bookaroom_eventLink" ) ) ):
			$eventLink = NULL;
		endif;
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# error message
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
			
		endif;
		
		# per page number on form
		$contents = str_replace( '#per_page#', $per_page, $contents );

		# row count - search results count
		$contents = str_replace( '#rowCount#', number_format( $rowCount ), $contents );
		
		#page numbers
		$some_pages_line	= repCon( 'some_pages', $contents );
		$none_pages_line	= repCon( 'none_pages', $contents, TRUE );
		
		#if( $totalPages <= 1 ):
		if( false ):
			$contents = str_replace( '#some_pages_line#', $none_pages_line, $contents );
		else:
			$contents = str_replace( '#some_pages_line#', $some_pages_line, $contents );
			
			$contents = str_replace( '#pageNum#', $page_num, $contents );
			$contents = str_replace( '#totalPages#', $totalPages, $contents );
			
			# create drop down
			$page_num_line = repCon( 'page_num', $contents );
			
			$final = array();
			$temp = NULL;
			
			for( $t=1; $t <= $totalPages; $t++ ):
				$selected = ( $page_num == $t ) ? ' selected="selected"' : NULL;
				
				$temp = $page_num_line;
				$temp = str_replace( '#page_num#', $t, $temp );
				$temp = str_replace( '#page_num_selected#', $selected, $temp );
				$final[] = $temp;
			
			endfor;
			
			$contents = str_replace( '#page_num_line#', implode( "\r\n", $final ), $contents );
			
			str_replace( '#pn_action#', 'filterResults', $contents );

			$goodArr = array( 'addDateVals', 'roomID', 'endDate', 'eventID', 'published', 'searchTerms', 'startDate', 'regType', 'branchID', 'noloc-branchID' );
			foreach( $goodArr as $val ):
				$contents = str_replace( "#pn_{$val}#", $externals[$val], $contents );
			endforeach;
			

			str_replace( '#pn_roomID#', $externals['roomID'], $contents );
			str_replace( '#pn_endDate#', $externals['endDate'], $contents );
			str_replace( '#pn_published#', $externals['published'], $contents );
			str_replace( '#pn_searchTerms#', $externals['searchTerms'], $contents );
			str_replace( '#pn_startDate#', $externals['startDate'], $contents );
			str_replace( '#pn_regType#', $externals['regType'], $contents );
			str_replace( '#pn_branchID#', $externals['branchID'], $contents );
			str_replace( '#pn_noloc-branchID#', $externals['noloc-branchID'], $contents );

			# create arrays foro age and category
			#'ageGroup', 'categoryGroup',
			$ageGroup_line = repCon( 'pn_ageGroup', $contents );
			 
			if( !empty( $externals['ageGroup'] ) and count( $externals['ageGroup'] > 0 ) ):
				$final = $temp = NULL;
				foreach( $externals['ageGroup'] as $key => $val ):
					$temp = $ageGroup_line;
					$temp = str_replace( '#pn_ageGroup#', $val, $temp );
					$final[] = $temp;
				endforeach;
				$contents = str_replace( '#pn_ageGroup_line#', implode( "\r\n", $final), $contents );
			else:
				$contents = str_replace( '#pn_ageGroup_line#', NULL, $contents );
			endif;

			# categories
			$categories_line = repCon( 'pn_categories', $contents );

			if( !empty( $externals['categoryGroup'] ) and count( $externals['categoryGroup'] > 0 ) ):
				$final = $temp = NULL;
				foreach( $externals['categoryGroup'] as $key => $val ):
					$temp = $categories_line;
					$temp = str_replace( '#pn_categoryGroup#', $val, $temp );
					$final[] = $temp;
				endforeach;
				$contents = str_replace( '#pn_categories_line#', implode( "\r\n", $final), $contents );
			else:
				$contents = str_replace( '#pn_categories_line#', NULL, $contents );
			endif;

		endif;
		
		
		# make regType list drop down
		$regType_line = repCon( 'regType', $contents );
		
		$final = array();
		$temp = $regType_line;

		$temp = str_replace( '#regType_desc#', 'Do not filter', $temp );
		$temp = str_replace( '#regType_val#', NULL, $temp );
		$selected = ( empty( $externals['regType'] ) or $externals['regType'] == NULL ) ? ' selected="selected"' : NULL;
		$temp = str_replace( '#regType_selected#', $selected, $temp );
		$final[] = $temp;
		
		foreach( array( 'no' => 'No Registration Required', 'yes' => 'Registration Required', 'staff' => 'Staff event' ) as $key => $val ):
			$temp = $regType_line;
			
			$temp = str_replace( '#regType_desc#', $val, $temp );
			$temp = str_replace( '#regType_val#', $key, $temp );
			$selected = ( !empty( $externals['regType'] ) and $externals['regType'] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#regType_selected#', $selected, $temp );
			$final[] = $temp;
		endforeach;
				
		$contents = str_replace( '#regType_line#', implode( "\r\n", $final ), $contents );

		# make branch list drop down
		$branch_line = repCon( 'branch', $contents );
		
		$final = array();
		
		# make branch drop
		$room_line = repCon( 'room', $contents );
		$temp = $room_line;
		$temp = str_replace( '#roomVal#', NULL, $temp );
		$selected = ( empty( $externals['branchID'] ) ) ? ' selected="selected"' : NULL;
		$temp = str_replace( '#roomDesc#', 'Do not filter', $temp );
		$temp = str_replace( '#roomSelected#', $selected, $temp );
		$temp = str_replace( '#roomSelectedClass#', NULL, $temp );			
		$final[] = $temp;

		
		foreach( $branchList as $key => $val ):
		
			$branchName = $val['branchDesc'];
			$temp = $room_line;
			$temp = str_replace( '#roomVal#', 'branch-'.$key, $temp );
			$selected = ( $externals['branchID'] == $val['branchID'] ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#roomDesc#', $branchName, $temp );
			$temp = str_replace( '#roomSelected#', $selected, $temp );
			$temp = str_replace( '#roomSelectedClass#', ' class="disabled"', $temp );			
			$final[] = $temp;
			
			$temp = $room_line;
			if( true == $val['branch_hasNoloc'] ):
				$selected = ( $externals['noloc-branchID'] == $val['branchID'] ) ? ' selected="selected"' : NULL;
				$temp = str_replace( '#roomVal#', 'noloc-'.$val['branchID'], $temp );
				$temp = str_replace( '#roomDesc#', '&nbsp;&nbsp;&nbsp;&nbsp;'.$branchName.' - No location required', $temp );
				$temp = str_replace( '#roomSelected#', $selected, $temp );
				$temp = str_replace( '#roomSelectedClass#', ' class="noloc"', $temp );
				$final[] = $temp;
			endif;
			# get all room conts
			$curRoomList = $roomContList['branch'][$val['branchID']];
			
			foreach( $curRoomList as $roomContID ):
				
				$temp = $room_line;
				
				$selected = ( $externals['roomID'] == $roomContID ) ? ' selected="selected"' : NULL;
				$temp = str_replace( '#roomVal#', $roomContID, $temp );
				$temp = str_replace( '#roomDesc#', '&nbsp;&nbsp;&nbsp;&nbsp;'.$roomContList['id'][$roomContID]['desc'].'&nbsp;['.$roomContList['id'][$roomContID]['occupancy'].']', $temp );
				$temp = str_replace( '#roomSelected#', $selected, $temp );
				$temp = str_replace( '#roomDisabled#', NULL, $temp );
				$final[] = $temp;

			endforeach;
		endforeach;
				
		$contents = str_replace( '#room_line#', implode( "\r\n", $final ), $contents );
		
		# date fills
		$contents = str_replace( '#startDate#', $externals['startDate'], $contents );
		$contents = str_replace( '#endDate#', $externals['endDate'], $contents );
		
		# search terms
		$contents = str_replace( '#searchTerms#', $externals['searchTerms'], $contents );
		
		# make age groups
		
		$ageList = bookaroom_settings_age::getNameList();
		
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$final = array();

		foreach( $ageList['active'] as $key => $val ):
			$temp = $ageGroup_line;
			$selected = ( !empty( $externals['ageGroup'] ) and in_array( $val['age_id'], $externals['ageGroup'] ) ) ?  ' selected="selected"' : NULL;
			
			$temp = str_replace( '#ageGroup_desc#', $val['age_desc'], $temp );
			$temp = str_replace( '#ageGroup_val#', $val['age_id'], $temp );
			$temp = str_replace( '#ageGroup_selected#', $selected, $temp );
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		
		# make category groups
		
		$categoryList = bookaroom_settings_categories::getNameList();
		$categoryGroup_line = repCon( 'categoryGroup', $contents );
		$final = array();

		foreach( $categoryList['active'] as $key => $val ):
			$temp = $categoryGroup_line;
			$selected = ( !empty( $externals['categoryGroup'] ) and in_array( $val['categories_id'], $externals['categoryGroup'] ) ) ?  ' selected="selected"' : NULL;
			
			$temp = str_replace( '#categoryGroup_desc#', $val['categories_desc'], $temp );
			$temp = str_replace( '#categoryGroup_val#', $val['categories_id'], $temp );
			$temp = str_replace( '#categoryGroup_selected#', $selected, $temp );
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#categoryGroup_line#', implode( "\r\n", $final ), $contents );
		
		# make published status drop down
		$registration_line		= repCon( 'registration', $contents );
		$noRegistration_line	= repCon( 'noRegistration', $contents, true );
		
		$multi_line = repCon( 'multi', $contents, true );
		$single_line = repCon( 'single', $contents );
		$published_line = repCon( 'published', $contents );
		
		$final = array();
		
		foreach( array( null => 'Do not filter', 'published' => 'Published', 'noPublished' => 'Not Published' ) as $key => $val ):
			$temp = $published_line;
			
			$temp = str_replace( '#published_desc#', $val, $temp );
			$temp = str_replace( '#published_val#', $key, $temp );
			$selected = ( $externals['published'] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#published_selected#', $selected, $temp );
			$final[] = $temp;
		endforeach;
				
		$contents = str_replace( '#published_line#', implode( "\r\n", $final ), $contents );
		
		# search retults:
		$search_line = repCon( 'search', $contents );
		

		
		# no search?
		if( $searched == false ):
			$contents = str_replace( '#search_line#', NULL, $contents );
		else:			
			$contents = str_replace( '#search_line#', $search_line, $contents );
			
			$searchItem_line	= repCon( 'searchItem', $contents );
			$staffEvent_line	= repCon( 'staffEvent', $searchItem_line );
			$searchNone_line	= repCon( 'searchNone', $contents, true );
			
			
			
			# no results"?
			if( count( $results ) == 0 ):
				$contents = str_replace( '#searchItem_line#', $searchNone_line, $contents );				
			else:
			# some results
				$final = array();
							
				foreach( $results as $key => $val ):
					$temp = $searchItem_line;
					
					$temp = str_replace( '#title#', $val['ev_title'], $temp );
					$temp = str_replace( '#desc#', $val['ev_desc'], $temp );
					
					if( empty( $val['ev_maxReg'] ) ):
						$temp = str_replace( '#registration_line#', $noRegistration_line, $temp );						
					else:
						$temp = str_replace( '#registration_line#', $registration_line, $temp );
						$temp = str_replace( '#total#', $val['ev_maxReg'], $temp );
						
						$regInfo = count( self::getRegInfo( $val['ti_id'] ) );
						$temp = str_replace( '#registered#', $regInfo, $temp );
						
					endif;
					
						if( $val['ev_regType'] == 'staff' ):
							$temp = str_replace( '#staffEvent_line#', $staffEvent_line, $temp );							
						else:
							$temp = str_replace( '#staffEvent_line#', NULL, $temp );
						endif;
					
					
					if( empty( $val['ti_roomID'] ) and !empty( $val['ti_noLocation_branch'] ) ):
						$temp = str_replace( '#room#', 'No location required', $temp );
						$temp = str_replace( '#branch#', $branchList[$val['ti_noLocation_branch']]['branchDesc'], $temp );
					else:		
						$roomName = ( empty( $roomContList['id'][$val['ti_roomID']]['desc'] ) ) ? 'None' : $roomContList['id'][$val['ti_roomID']]['desc'];
						$temp = str_replace( '#room#', $roomName, $temp );
						$branchName = ( empty( $roomContList['id'][$val['ti_roomID']] ) ) ? 'None' : $branchList[$roomContList['id'][$val['ti_roomID']]['branchID']]['branchDesc'];
						$temp = str_replace( '#branch#', $branchName, $temp );
					endif;
					
					#links 
					if( $val['eventCount'] > 1 ):
						$temp = str_replace( '#single_line#', $multi_line, $temp );						
					else:
						$temp = str_replace( '#single_line#', $single_line, $temp );
					endif;
					$temp = str_replace( '#eventID#', $val['ti_id'], $temp );
					#date functions
					$startTime = strtotime( $val['ti_startTime'] );
					$endTime = strtotime( $val['ti_endTime'] );
					
					$date = date( 'l, F jS, Y', $startTime );
					$startTime = date( 'g:i a', $startTime );
					$endTime = date( 'g:i a', $endTime );
					
					if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
						$times = 'All Day';
					else:
						$times = $startTime . ' to ' . $endTime;
					endif;
					
					$temp = str_replace( '#date#', $date, $temp );
					$temp = str_replace( '#time#', $times, $temp );
					$temp = str_replace( '#extraInfo#', $val['ti_extraInfo'], $temp );
					$temp = str_replace( '#eventLink#', $eventLink, $temp );
					
					
					$final[] = $temp;
				endforeach;
				
				
				
				
				$contents = str_replace( '#searchItem_line#', implode( "\r\n", $final ), $contents );
			endif;
			
		endif;
		
		
		echo $contents;
	}
	
	public static function get_clean_option( $type )
	{
		switch( $type ):
		
			case 'bookaroom_search_events_page_num':
				$temp = get_option( 'bookaroom_search_events_page_num' );
				
				if( !is_numeric( $temp ) ):
					return NULL;
				else:
					$final = $temp;
				endif;
				
				break;
				
			case 'bookaroom_search_events_per_page':
				$temp = get_option( 'bookaroom_search_events_per_page' );
				
				if( !in_array( $temp, array( 'All', '10', '25', '50', '100' ) ) ):
					$final = '10';
				else:
					$final = $temp;
				endif;
				
				break;
			
			case 'bookaroom_search_events_order_by':
				$temp = get_option( 'bookaroom_search_events_order_by' );
				
				if( !in_array( $temp, array( 'event_id', 'ti_startTime', 'title', 'branch', 'room', 'registrations' ) ) ):
					$final = 'event_id';
				else:
					$final = $temp;
				endif;
				
				break;
			
			case 'bookaroom_search_events_sort_order':
				$temp = get_option( 'bookaroom_search_events_order_by' );
				
				if( !in_array( $temp, array( 'asc', 'desc' ) ) ):
					$final = 'desc';
				else:
					$final = $temp;
				endif;
				
				break;
								
			default:
				$final = NULL;
				break;
		endswitch;
		
		return $final;
		
	}
}
?>