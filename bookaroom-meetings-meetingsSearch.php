<?PHP
class book_a_room_meetingsSearch
{
	############################################
	#
	# Meetings search
	#
	############################################
	
	public static function bookaroom_searchRequests( )
	{	
		$externals = self::getExternals();

		
		switch( $externals['action'] ):
			case 'filterResults':
				$results = self::getMeetingList( $externals );
				self::showSearch( $externals, $results );
				break;
				
			default:
				self::showSearch( $externals, array(), TRUE );
				break;
		endswitch;
	}
	
	public static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();

		# setup GET variables
		$getArr = array(	'action'					=> FILTER_SANITIZE_STRING);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = array_merge( $final, $getTemp );
		# setup POST variables
		$postArr = array(	'action'					=> FILTER_SANITIZE_STRING,
		
							'roomID'					=> FILTER_SANITIZE_STRING,
							'endDate'					=> FILTER_SANITIZE_STRING,
							'nonProfit'					=> FILTER_SANITIZE_STRING,
							'searchTerms'				=> FILTER_SANITIZE_STRING,
							'status'					=> FILTER_SANITIZE_STRING,
							'startDate'					=> FILTER_SANITIZE_STRING,
							'timestamp'					=> FILTER_SANITIZE_STRING,);
	
	

		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final = array_merge( $final, $postTemp );

		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			elseif( is_array( $final[$key] ) && ( $key == 'hours' || $key == 'amenity' || $key = 'res_id' ) ):
				$final[$key] = array_filter( $final[$key], 'strlen' );
			elseif( is_array( $final[$key] ) ):
				$final[$key] = array_keys( $final[$key] );
			else:			
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;
		
		# calendar timestamp
		if( !empty( $final['submitCal'] ) ):
			$final['timestamp'] = mktime( 0, 0, 0, $final['calMonth'], 1, $final['calYear'] );

		endif;
		
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
	
	public static function getMeetingList( $externals )
	{
		global $wpdb;
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();


		$page_num	= get_option( 'bookaroom_search_events_page_num' );
		$per_page	= get_option( 'bookaroom_search_events_per_page' );
		$order_by	= get_option( 'bookaroom_search_events_order_by' );
		$sort_order	= get_option( 'bookaroom_search_events_sort_order' );

		$where = array();
		
		$where[] = "`ti`.`ti_type` = 'meeting'";
		

		# check for branch
		if( !empty( $externals['roomID'] ) and array_key_exists( $externals['roomID'], $roomContList['id'] ) ):
			# find all rooms in branch
			$where[] = "(`ti`.`ti_roomID` = '{$externals['roomID']}')";
			#@$where[] = '
			
		endif;
		
		# check for branch
		if( !empty( $externals['branchID'] ) and array_key_exists( $externals['branchID'], $branchList ) ):
			# find all rooms in branch
			$branchArr = implode( ',',  $roomContList['branch'][$externals['branchID']] );
			$where[] = "(`ti`.`ti_roomID` IN ( {$branchArr} ) or `ti`.`ti_noLocation_branch` = '{$externals['branchID']}')";
			#@$where[] = '
			
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
			$where[] = " MATCH ( `res`.`me_desc`, `res`.`me_eventName`, `res`.`me_contactName`, `res`.`me_contactEmail`, `res`.`me_notes` ) AGAINST ('{$externals['searchTerms']}' IN NATURAL LANGUAGE MODE )";
			$scoreWhere = "`score` DESC, ";
		else:
			$scoreWhere = NULL;
		endif;

		# check for status
		if( !empty( $externals['status'] ) ):
			$statusArr = array( 'Pending' => 'pending', 'Pend. Payment' => 'pendPayment', 'Approved' => 'approved', 'Denied' => 'denied', 'Archived' => 'archived' );
			
			if( array_key_exists( $externals['status'], $statusArr ) ):

				$where[] = "`res`.`me_status` = '{$statusArr[$externals['status']]}'";
			endif;
		endif;
		
		# check for non-profit
		if( $externals['nonProfit'] == 'Non-profit' ):
				$where[] = "`res`.`me_nonProfit` = 1";			
		elseif( $externals['nonProfit'] == 'Profit' ):
				$where[] = "`res`.`me_nonProfit` = 0";
		endif;
				
		#  check for and build WHERE statment
		if( count( $where ) > 0 ):
			$whereFinal = 'WHERE '.implode( ' AND ', $where );
		else:
		
		endif;
		
		$sql = "SELECT MATCH ( `res`.`me_desc`, `res`.`me_eventName`, `res`.`me_contactName`, `res`.`me_contactEmail`, `res`.`me_notes` ) AGAINST ('{$externals['searchTerms']}' IN NATURAL LANGUAGE MODE ) as `score`, 

`res`.`res_id`, 
`res`.`me_amenity`, 
`res`.`me_contactAddress1`, 
`res`.`me_contactAddress2`, 
`res`.`me_contactCity`, 
`res`.`me_contactEmail`, 
`res`.`me_contactName`, 
`res`.`me_contactPhonePrimary`, 
`res`.`me_contactPhoneSecondary`, 
`res`.`me_contactState`, 
`res`.`me_contactWebsite`, 
`res`.`me_contactZip`, 
`res`.`me_desc`, 
`res`.`me_eventName`, 
`res`.`me_nonProfit`, 
`res`.`me_numAttend`, 
`res`.`me_notes`, 
`res`.`me_status`, 
`ti`.`ti_created`, 	
		
		
		
		`ti`.`ti_roomID`, `ti`.`ti_noLocation_branch`, `ti`.`ti_id`, `ti`.`ti_startTime`, `ti`.`ti_endTime`, COUNT( DISTINCT `tiCount`.`ti_id` ) as eventCount
					FROM `{$wpdb->prefix}bookaroom_times` AS `ti`
					LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` AS `res` ON `res`.`res_id` = `ti`.`ti_extID`
					LEFT JOIN `{$wpdb->prefix}bookaroom_times` AS `tiCount` ON `tiCount`.`ti_extID` = `res`.`res_id` 

					{$whereFinal}
					GROUP BY `ti`.`ti_id`
					ORDER BY {$scoreWhere}`ti`.`ti_startTime`, `res`.`ev_title`";
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		return $cooked;
		
	}
	
	protected static function showSearch( $externals, $pendingList = array(), $startPage = FALSE )
	{
		$filename = BOOKAROOM_PATH . 'templates/meetings/searchPending.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		
		# no location
		if( !empty( $externals['roomID'] ) and substr( $externals['roomID'], 0, 6 ) == 'noloc-' ):
			$branchInfo = explode( '-', $externals['roomID'] );
			if( !array_key_exists( $branchInfo['0'], $branchList ) ):
				$branchID = NULL;
				$roomID = NULL;
			endif;
		else:
			$branchID = bookaroom_events::branch_and_room_id( $externals['roomID'], $branchList, $roomContList );
			$roomID = $externals['roomID'];
		endif;
		
		# make branch drop
		$room_line = repCon( 'room', $contents );
		$final = array();
		

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
		/*
		# make branch drop
		$branch_line = repCon( 'branch', $contents );
		
		$final = array();
		$temp = $branch_line;
		$selected = ( empty( $externals['branchID'] ) or !array_key_exists( $externals['branchID'], $branchList ) ) ? ' selected="selected"' : NULL;
		$temp = str_replace( '#branch_desc#', 'Do not filter', $temp );
		$temp = str_replace( '#branchID#', NULL, $temp );
		$temp = str_replace( '#branch_selected#', $selected, $temp );
		$final[] = $temp;
		
		foreach( $branchList as $key => $val ):
			$temp = $branch_line;
			$selected = ( !empty( $externals['branchID'] ) and $externals['branchID'] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#branch_desc#', $val['branchDesc'], $temp );
			$temp = str_replace( '#branchID#', $key, $temp );
			$temp = str_replace( '#branch_selected#', $selected, $temp );
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#branch_line#', implode( "\r\n", $final ), $contents );
`		*/

		# make nonProfit drop
		$nonProfit_line = repCon( 'nonProfit', $contents );
		
		$final = array();
		$temp = $nonProfit_line;
		$goodArr = array( 'Profit', 'Non-profit' );
		$selected = ( empty( $externals['nonProfit'] ) or !array_key_exists( $externals['nonProfit'], $goodArr ) ) ? ' selected="selected"' : NULL;
		$temp = str_replace( '#nonProfit_desc#', 'Do not filter', $temp );
		$temp = str_replace( '#nonProfit_val#', NULL, $temp );
		$temp = str_replace( '#nonProfit_selected#', $selected, $temp );
		$final[] = $temp;
		
		foreach( $goodArr as $val ):
			$temp = $nonProfit_line;
			$selected = ( !empty( $externals['nonProfit'] ) and $externals['nonProfit'] == $val ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#nonProfit_desc#', $val, $temp );
			$temp = str_replace( '#nonProfit_val#', $val, $temp );
			$temp = str_replace( '#nonProfit_selected#', $selected, $temp );
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#nonProfit_line#', implode( "\r\n", $final ), $contents );		
		
		# make status drop
		$status_line = repCon( 'status', $contents );
		
		$final = array();
		$temp = $status_line;
		$statusArr = array( 'Pending', 'Pend. Payment', 'Approved', 'Denied', 'Archived' );
		$selected = ( empty( $externals['status'] ) or !array_key_exists( $externals['status'], $goodArr ) ) ? ' selected="selected"' : NULL;
		$temp = str_replace( '#status_desc#', 'Do not filter', $temp );
		$temp = str_replace( '#status_val#', '', $temp );
		$temp = str_replace( '#status_selected#', $selected, $temp );
		$final[] = $temp;
		
		foreach( $statusArr as $val ):
			$temp = $status_line;
			$selected = ( !empty( $externals['status'] ) and $externals['status'] == $val ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#status_desc#', $val, $temp );
			$temp = str_replace( '#status_val#', $val, $temp );
			$temp = str_replace( '#status_selected#', $selected, $temp );
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#status_line#', implode( "\r\n", $final ), $contents );		
		
		
		# dates
		$contents = str_replace( '#startDate#', $externals['startDate'], $contents );
		$contents = str_replace( '#endDate#', $externals['endDate'], $contents );
		
		# search terms
		$contents = str_replace( '#searchTerms#', $externals['searchTerms'], $contents );
		
		
		################################################
		#
		# search results
		#
		################################################

		$some_line = repCon( 'some', $contents );
		$none_line = repCon( 'none', $contents, TRUE );
		
		if( empty( $pendingList ) ):
			$contents = str_replace( '#some_line#', $none_line, $contents );
			echo $contents;
			return true;
		endif;
		
		$contents = str_replace( '#some_line#', $some_line, $contents );
				
		$line_line = repCon( 'line', $contents );
		
		$count = 0;
		$final = array();
		
		$statusArr = array( 'pending' => 'Pending', 'pendPayment' => 'Pend. Payment', 'approved' => 'Approved', 'denied' => 'Denied', 'archived' => 'Archived' );
		$option['bookaroom_profitDeposit']				= get_option( 'bookaroom_profitDeposit' );
		$option['bookaroom_nonProfitDeposit']			= get_option( 'bookaroom_nonProfitDeposit' );
		$option['bookaroom_profitIncrementPrice']		= get_option( 'bookaroom_profitIncrementPrice' );
		$option['bookaroom_nonProfitIncrementPrice']	= get_option( 'bookaroom_nonProfitIncrementPrice' );
		$option['bookaroom_baseIncrement']				= get_option( 'bookaroom_baseIncrement' );
		$count = 1;
		foreach( $pendingList as $key => $val):
			$temp = $line_line;
		
			
			$temp = str_replace( '#roomName#', $roomContList['id'][$val['ti_roomID']]['desc'], $temp );			
			$temp = str_replace( '#branchName#', $branchList[$roomContList['id'][$val['ti_roomID']]['branchID']]['branchDesc'], $temp );
			$temp = str_replace( '#date#', date( 'M. jS, Y', strtotime( $val['ti_startTime'] ) ), $temp );
			$temp = str_replace( '#createdDate#', date( 'M. jS, Y', strtotime( $val['ti_created'] ) ), $temp );
			$temp = str_replace( '#createdTime#', date( 'g:i a', strtotime( $val['ti_created'] ) ), $temp );
			$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $val['ti_startTime'] ) ), $temp );
			$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $val['ti_endTime'] ) ), $temp );
			$temp = str_replace( '#eventName#', htmlspecialchars_decode( $val['me_eventName'] ), $temp );
		
			$notes = 0;		
			$temp = str_replace( '#contactName#', $val['me_contactName'], $temp );
			$temp = str_replace( '#noteInformation#', book_a_room_meetings::noteInformation( $val['me_contactName'], $notes ), $temp );
			$temp = str_replace( '#notesNum#', $notes, $temp );
			$temp = str_replace( '#itemCount#', $count++, $temp );
			
			$temp = str_replace( '#email#', $val['me_contactEmail'], $temp );
			
			$temp = str_replace( '#phone#', book_a_room_meetings::prettyPhone( $val['me_contactPhonePrimary'] ), $temp );
			#$temp = str_replace( '#phone#', $val['me_contactPhonePrimary'], $temp );
			
			$nonProfit = ( empty( $val['me_nonProfit'] ) ) ? 'No' : 'Yes';
			$temp = str_replace( '#nonprofit#', $nonProfit, $temp );
			$temp = str_replace( '#res_id#', $val['res_id'], $temp );

		

		$roomCount = count( $roomContList['id'][$val['ti_roomID']]['rooms'] );

		if( empty( $val['me_nonProfit'] ) ):
			# find how many increments
			$minutes = ( ( strtotime( $val['ti_endTime'] ) - strtotime( $val['ti_startTime'] ) ) / 60 ) / $option['bookaroom_baseIncrement'] ;
			$roomPrice = $minutes * $option['bookaroom_profitIncrementPrice'] * $roomCount;
			$deposit = 	intval( $option['bookaroom_profitDeposit'] );
		else:
			# find how many increments
			$minutes = ( ( strtotime( $val['ti_endTime'] ) - strtotime( $val['ti_startTime'] ) ) / 60 ) / $option['bookaroom_baseIncrement'];
			$roomPrice = $minutes * $option['bookaroom_nonProfitIncrementPrice'] * $roomCount;
			$deposit = 	intval( $option['bookaroom_nonProfitDeposit'] );	
		endif;
		
		$temp = str_replace( '#deposit#', $deposit, $temp );
		$temp = str_replace( '#roomPrice#', $roomPrice, $temp );
		$temp = str_replace( '#totalPrice#', $roomPrice+$deposit, $temp );
		
		

		#$status = $statusArr[$val['me_status']];
		$temp = str_replace( '#status#', $statusArr[$val['me_status']], $temp );
		
		$final[] = $temp;
		
		endforeach;
		
		$contents = str_replace( '#line_line#', implode( "\r\n", $final ), $contents );
		echo $contents;	
	}
}