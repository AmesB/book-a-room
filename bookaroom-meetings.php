<?PHP
class book_a_room_meetings
{
	############################################
	#
	# Meetings managment
	#
	############################################
	public static function bookaroom_admin()
	{
		self::bookaroom_pendingRequests( 'all' );
	}
	
	public static function bookaroom_pendingRequests( $pendingType )
	{	
		$typeArr = array( 'pending', 'pendPayment', '501C3', 'approved', 'denied', 'archived', 'all' );
		$pendingType = trim( $pendingType );
		
		if( empty( $pendingType ) or !in_array( $pendingType, $typeArr) ):
			$pendingType = 'pending';
		endif;
		
		# make lines
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-public.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-cityManagement.php' );
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$cityList = bookaroom_settings_cityManagement::getCityList( );
		
		$pendingList = self::getPending();
		$externals = self::getExternals();
		
		switch( $externals['action'] ):
			case 'changeStatusShow':
				self::changeStatusShow();
				break;
				
			case 'changeReservation':
				# check hours
				$baseIncrement = get_option( 'bookaroom_baseIncrement' );
			
				if( !empty( $externals['hours'] ) ):
					$externals['startTime'] = current( $externals['hours'] );
				else:
					$externals['startTime'] = $externals['timestamp'];
				endif;
				
				if( !empty( $externals['hours'] ) ):
					$externals['endTime'] = end( $externals['hours'] ) + ( $baseIncrement * 60 );
				else:
					$externals['endTime'] = $externals['timestamp'];
				endif;
				
				if( empty( $externals['roomID'] ) && !empty( $externals['branchID'] ) ):
					$externals['roomID'] = $roomContList['branch'][$externals['branchID']][0];
				endif;
				
				if( ( $errorMSG = bookaroom_public::showForm_checkHoursError( $externals['startTime'], $externals['endTime'], $externals['roomID'], $roomContList, $branchList, $_SESSION['bookaroom_res_id'] ) ) == TRUE ):
					self::showChangeReservationMeeting( $externals, $errorMSG, $_SESSION['bookaroom_res_id'] );
					break;
				endif;
				$requestInfo = $pendingList['id'][$_SESSION['bookaroom_res_id']];
				
				$requestInfo['startTime'] = $externals['startTime'];
				$requestInfo['endTime'] = $externals['endTime'];

				echo bookaroom_public::showForm_publicRequest( $externals['roomID'], $branchList, $roomContList, $roomList, $amenityList, $cityList, $requestInfo, array(), $_SESSION['bookaroom_res_id'] );
				break;
				
			case 'changeReservationSetup':
				$_SESSION['bookaroom_res_id'] = $externals['res_id'];
				$final = $pendingList['id'][$externals['res_id']];
				$final['timestamp'] = strtotime( $final['startTime'] );
				# setup times
				$baseIncrement = get_option( 'bookaroom_baseIncrement' );

				for($i = strtotime( $final['startTime'] ); $i< strtotime( $final['endTime'] ); $i += ( $baseIncrement*60 ) ):
					$final['hours'][] = $i;
				endfor;
				
				$externals = $final;

				self::showChangeReservationMeeting( $externals );
				break;
				
			case 'changeStatus':
				if( empty( $externals['res_id'] ) ): 
					self::showError( 'res_id', $externals['res_id'] );
					break;
				endif;

				$statusArr = array( 'pending', 'pendPayment', 'approved', 'denied', 'archived' );
				
				if( !in_array( $externals['status'], $statusArr ) ):
					$header = 'Change Status';
					$title = 'Invalid status.';
					$message = "You have selected an invalid status. {$externals['status']} does not exist.";
					$linkName = 'Return to pending request home.';
					$linkURL = '?page=bookaroom_meetings';
					die();
					self::showGenericError( $header, $title, $message, $linkName, $linkURL );
					break;
				endif;
				
				
				if( !is_array( $externals['res_id'] ) ):
					$externals['resList'] = array( $externals['res_id'] );
				else:
					$externals['resList'] = $externals['res_id'];
				endif;
				
				self::changeStatus( $externals, $pendingList, $branchList, $roomContList, $amenityList );
				
				break;
				
			case 'edit':
				if( !array_key_exists( $externals['res_id'], $pendingList['id'] ) ):
					self::showError( 'res_id', $externals['res_id'] );
					break;
				endif;
				$requestInfo = $pendingList['id'][$externals['res_id']];
				$_SESSION['bookaroom_res_id'] = $externals['res_id'];
				$requestInfo['startTime'] = strtotime( $requestInfo['startTime'] );
				$requestInfo['endTime'] = strtotime( $requestInfo['endTime'] );
				$requestInfo['amenity'] = unserialize( $requestInfo['amenity'] );
				
				echo bookaroom_public::showForm_publicRequest( $requestInfo['roomID'], $branchList, $roomContList, $roomList, $amenityList, $cityList, $requestInfo, array(), $externals['res_id'] );
				break;
				
			case 'editCheck':
				self::showForm_updateRequest( $externals, $branchList, $roomContList, $roomList, $amenityList, $_SESSION['bookaroom_res_id'] );
				break;
				
			case 'editCheckShow':
				if( empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					$_SESSION['bookaroom_temp_search_settings'] = NULL;
				endif;
				self::showForm_updateRequestShow( $externals['res_id'], $_SESSION['bookaroom_temp_search_settings'] );
				break;
				
			case 'view':
				if( !array_key_exists( $externals['res_id'], $pendingList['id'] ) ):
					self::showError( 'res_id', $externals['res_id'] );
					break;
				endif;

				self::showView( $externals['res_id'], $pendingList, $roomContList, $roomList, $branchList, $amenityList );
				break;
			
			case 'showPendPayment':
				self::showPending( 'pendPayment', $pendingList, $roomContList, $roomList, $branchList, $amenityList );
				break;
				
			default:
				self::showPending( $pendingList, $roomContList, $roomList, $branchList, $amenityList, $pendingType );
				break;
				
		endswitch;
		
	}
	
	protected static function changeStatus( $externals, $pendingList, $branchList, $roomContList, $amenityList )
	{
		global $wpdb;
		# check that there is a non empty array for res list
		if( empty( $externals['resList'] ) || !is_array( $externals['resList'] ) ):
			self::showGenericError( 'Requests', 'Modify Requests', 'You have not selected any valid reservations to edit. Please try again.', 'Return to Pending Request home.', '?page=bookaroom_meetings' );
			return false;
		endif;
		
		$final = array( 'fail' => array(), 'noChange' => array(), 'changed' => array() );
		
		# cycle through res list.
		foreach( $externals['resList'] as $val ):
			# double check ID
			if(	!array_key_exists( $val, $pendingList['id'] ) ):
				$final['fail'][] = $val;
			# check if status is different
			elseif( $pendingList['id'][$val]['status'] == $externals['status'] ):
				$final['noChange'][] = $val;
			# update database
			else:
				$final['changed'][] = $val;
			# mail alerts
			endif;
		endforeach;
		
		if( !empty( $final['changed'] ) ):
			# get correct status email
			# status that required an outgoing email: pendPayment, denied, accepted.
			
			$sendMail = FALSE;
			$needHash = FALSE;

			
			switch ( $externals['status'] ):
				
				case 'pending':
				case 'archive':
				case 'accepted':
					break;
				
				case 'approved':
					$sendMail = TRUE;
					break;
				
				case 'denied':
					$sendMail = 'denied';
					
					$subject = get_option( 'bookaroom_requestDenied_subject' );

					$body = nl2br( get_option( 'bookaroom_requestDenied_body' ) );
					$costIncrement = 0;
					break;
									
				case 'pendPayment':
					$sendMail = TRUE;
					# get data for email

					break;
				endswitch;
				
				# CREATE HASH and LINK
				# -----------------------------------
				#$hash = $val.$pendingList['id'][$val]['salt'];
				#
				#$pendingList['id'][$val]['ccLink'] = 'http://'.$_SERVER['SERVER_NAME'] . get_option( 'bookaroom_reservation_URL' ) . '/?action=ccPayment&res_id='.$val.'&hash='.$hash;
				# SEND EMAIL

				foreach( $final['changed'] as $val ):
				
					if( $sendMail == true ):
						if( $sendMail !== 'denied' ):
							if( $pendingList['id'][$val]['nonProfit'] == TRUE ) :
								if( $externals['status'] == 'pendPayment' ):
									$subject = get_option( 'bookaroom_nonProfit_pending_subject' );
									$body = nl2br( get_option( 'bookaroom_nonProfit_pending_body' ) );
								else:
									$subject = get_option( 'bookaroom_requestAcceptedNonprofit_subject' );
									$body = nl2br( get_option( 'bookaroom_requestAcceptedNonprofit_body' ) );
								endif;
								$pendingList['id'][$val]['roomDeposit'] = get_option( 'bookaroom_nonProfitDeposit' );
								$costIncrement =  get_option( 'bookaroom_nonProfitIncrementPrice' );
							else:
								
								# old code, remove after testing
								
								#$subject = get_option( 'bookaroom_requestAcceptedProfit_subject' );
								#$body = nl2br( get_option( 'bookaroom_requestAcceptedProfit_body' ) );
								#$pendingList['id'][$val]['roomDeposit'] = get_option( 'bookaroom_profitDeposit' );
								
								if( $externals['status'] == 'pendPayment' ):
									$subject = get_option( 'bookaroom_profit_pending_subject' );
									$body = nl2br( get_option( 'bookaroom_profit_pending_body' ) );
								else:
									$subject = get_option( 'bookaroom_requestAcceptedProfit_subject' );
									$body = nl2br( get_option( 'bookaroom_requestAcceptedProfit_body' ) );
								endif;
								$pendingList['id'][$val]['roomDeposit'] = get_option( 'bookaroom_profitDeposit' );
								$costIncrement =  get_option( 'bookaroom_profitIncrementPrice' );
							endif;
						endif;
						if( empty( $pendingList['id'][$val]['roomDeposit'] ) ):
							$pendingList['id'][$val]['roomDeposit'] = '0';
						endif;
						
						# &&&
						$roomCount = count( $roomContList['id'][$pendingList['id'][$val]['roomID']]['rooms'] );
						
						
						$pendingList['id'][$val]['roomPrice'] = ( ( ( ( strtotime( $pendingList['id'][$val]['endTime'] ) - strtotime( $pendingList['id'][$val]['startTime'] ) ) / 60 ) / get_option( 'bookaroom_baseIncrement' ) ) * $costIncrement * $roomCount );
						$pendingList['id'][$val]['totalPrice'] = $pendingList['id'][$val]['roomPrice'] + $pendingList['id'][$val]['roomDeposit'];

						$pendingList['id'][$val]['date'] = date( 'l, F jS, Y', strtotime( $pendingList['id'][$val]['startTime'] ) );
						$pendingList['id'][$val]['startTime'] = date( 'g:i a', strtotime( $pendingList['id'][$val]['startTime'] ) );
						$pendingList['id'][$val]['endTime'] = date( 'g:i a', strtotime( $pendingList['id'][$val]['endTime'] ) );
						
						$pendingList['id'][$val]['nonProfit'] = ( $pendingList['id'][$val]['nonProfit'] == TRUE ) ? 'Yes' : 'No';
						$pendingList['id'][$val]['roomName'] = $roomContList['id'][$pendingList['id'][$val]['roomID']]['desc'];
						$pendingList['id'][$val]['branchName'] = $branchList[$roomContList['id'][$pendingList['id'][$val]['roomID']]['branchID']]['branchDesc'];
						
						$amenity = array();
						
						if( !empty( $pendingList['id'][$val]['amenity'] ) ):
							foreach( unserialize( $pendingList['id'][$val]['amenity'] ) as $am ):
								$amenity[] = $amenityList[$am];
							endforeach;
						endif;
						if( count( $amenity ) == 0 ):
							$amenity = 'None';
						else:
							$amenity = implode( ', ', $amenity );
						endif;
						
						$pendingList['id'][$val]['amenity'] = $amenity;
						
						$fromName	= get_option( 'bookaroom_alertEmailFromName' );
						$fromEmail	= get_option( 'bookaroom_alertEmailFromEmail' );
						$replyToOnly	= ( true == get_option( 'bookaroom_emailReplyToOnly' ) ) ? "From: {$fromName}\r\nReply-To: {$fromName} <$fromEmail>\r\n" : "From: {$fromName} <$fromEmail>\r\n";
												
						# payment Link
						$pendingList['id'][$val]['paymentLink'] = self::makePaymentLink( $pendingList['id'][$val]['totalPrice'], $val );
						

						$valArr = array( 'amenity', 'amenityVal', 'branchName', 'ccLink', 'contactAddress1', 'contactAddress2', 'contactCity', 'contactEmail', 'contactName', 'contactPhonePrimary', 'contactPhoneSecondary', 'contactState', 'contactWebsite', 'contactZip', 'date', 'desc', 'endTime', 'endTimeDisp', 'eventName', 'formDate', 'nonProfit', 'nonProfitDisp', 'numAttend', 'paymentLink', 'roomDeposit', 'roomID', 'roomName', 'roomPrice', 'startTime', 'startTimeDisp', 'totalPrice' );
						
						foreach( $valArr as $val2 ):
							if( !empty( $pendingList['id'][$val][$val2] ) ):
								$body = str_replace( "{{$val2}}", $pendingList['id'][$val][$val2], $body );
							else:
								$body = str_replace( "{{$val2}}", NULL, $body );
							endif;
						endforeach;

# replace hash and link

						# date 1 - two weeks from now
						$timeArr = getdate( strtotime(  $pendingList['id'][$val]['created'] ) );
						$date1 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+14, $timeArr['year'] );
						$date3 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+1, $timeArr['year'] );
						
						# two weeks before event	
						$timeArr = getdate( strtotime( $pendingList['id'][$val]['date'] . ' ' . $pendingList['id'][$val]['startTime']) );
						$date2 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']-14, $timeArr['year'] );

						
						# check dates
						$mainDate = min( $date1, $date2 );
						
						if( $mainDate < $date3 ):
							$mainDate = $date3;
						endif;
						
						$body = str_replace( '{paymentDate}', date( 'm-d-Y', $mainDate ), $body );
			
						$headers  = 'MIME-Version: 1.0' . "\r\n";
						$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
							$replyToOnly . 
							'X-Mailer: PHP/' . phpversion();
						
						mail( $pendingList['id'][$val]['contactEmail'], $subject, $body, $headers );
						
					endif;
				
				# UPDATE DATABASE
				$table_name = $wpdb->prefix . "bookaroom_reservations";
				
				$wpdb->update(	$table_name, 
						array( 'me_status'				=> $externals['status'] ), 
						array( 'res_id' => $val ) );
			endforeach;
		endif;

		$_SESSION['showData'] = $final;
		echo '<META HTTP-EQUIV="Refresh" Content="0; URL=?page=bookaroom_meetings&action=changeStatusShow">';
	}
	
	public static function changeStatusShow()
	{	
		$final = @$_SESSION['showData'];
		unset( $_SESSION['showData'] );
		
		$filename = BOOKAROOM_PATH . 'templates/meetings/changeStatus.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#changed#', count( $final['changed'] ), $contents );
		$contents = str_replace( '#noChange#', count( $final['noChange'] ), $contents );
		$contents = str_replace( '#fail#', count( $final['fail'] ), $contents );
		
		echo $contents;
	}
	
	public static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();

		# setup GET variables
		$getArr = array(	'action'					=> FILTER_SANITIZE_STRING, 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'branchID'					=> FILTER_SANITIZE_STRING, 
							'timestamp'					=> FILTER_SANITIZE_STRING, 
							'submitCal'					=> FILTER_SANITIZE_STRING, 
							'calYear'					=> FILTER_SANITIZE_STRING, 
							'calMonth'					=> FILTER_SANITIZE_STRING, 
							'calDay'					=> FILTER_SANITIZE_STRING, 
							'ccLink'					=> FILTER_SANITIZE_STRING, 
							'res_id'					=> FILTER_SANITIZE_STRING);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = array_merge( $final, $getTemp );

		# setup POST variables
		$postArr = array(	'action'					=> FILTER_SANITIZE_STRING,
							'amenity'						=> array(	'filter'    => FILTER_SANITIZE_STRING,
																		'flags'     => FILTER_REQUIRE_ARRAY ), 
							'contactAddress1'			=> FILTER_SANITIZE_STRING,
							'contactAddress2'			=> FILTER_SANITIZE_STRING,
							'contactCity'				=> FILTER_SANITIZE_STRING,
							'contactEmail'				=> FILTER_SANITIZE_STRING,
							'contactName'				=> FILTER_SANITIZE_STRING,
							'contactPhonePrimary'		=> FILTER_SANITIZE_STRING,
							'contactPhoneSecondary'		=> FILTER_SANITIZE_STRING,
							'contactState'				=> FILTER_SANITIZE_STRING,
							'contactWebsite'			=> FILTER_SANITIZE_STRING,
							'contactZip'				=> FILTER_SANITIZE_STRING,
							'desc'						=> FILTER_SANITIZE_STRING,
							'emailAddress'				=> FILTER_SANITIZE_STRING,
							'endTime'					=> FILTER_SANITIZE_STRING,
							'eventName'					=> FILTER_SANITIZE_STRING,
							'libcardNum'				=> FILTER_SANITIZE_STRING,	
							'isSocial'					=> FILTER_SANITIZE_STRING,
							'nonProfit'					=> FILTER_SANITIZE_STRING,
							'notes'						=> FILTER_SANITIZE_STRING,
							'numAttend'					=> FILTER_SANITIZE_STRING,
							'roomID'					=> FILTER_SANITIZE_STRING,
							'status'					=> FILTER_SANITIZE_STRING,
							'startTime'					=> FILTER_SANITIZE_STRING,
							'timestamp'					=> FILTER_SANITIZE_STRING, 
							'branchID'					=> FILTER_SANITIZE_STRING, 
							'viewAll'					=> FILTER_SANITIZE_STRING, 
							'res_id'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'hours'						=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ) );

	

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
		
		return $final;
	}
	
	public static function getPending( $date = NULL, $statusArr = array(), $isEvent = NULL, $showHidden = false, $approvedOnly = false, $viewAll = false )
	{
		global $wpdb;
		
		array_walk($statusArr, create_function('&$value,$key', '$value = \'"\'.$value.\'"\';'));
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		$table_name = $wpdb->prefix . "bookaroom_times";
		$table_nameRes = $wpdb->prefix . "bookaroom_reservations";
		
		$where = array();
		if( $approvedOnly == true ):
			$where[] = "( `ti`.`ti_type` = 'meeting' AND `res`.`me_status` = 'approved' ) OR ( `ti`.`ti_type` = 'event'  )";
		else:
			#$where[] = "( `ti`.`ti_type` = 'meeting' AND `res`.`me_status` IN ( 'approved', 'pending', 'pendPayment' ) ) OR ( `ti`.`ti_type` = 'event'  )";
		endif;
		
		# isEvent - search for events, too?
		#if( $isEvent == 'both' ):
			# staus array
		#else
		if( $isEvent == TRUE ):
			# staus array
			# if empty, search all. If not, seach for only those statuses
			if( count( $statusArr ) > 0 ):
				$where[] = "`ti`.`ti_type` = 'event' OR (`ti`.`ti_type` = 'meeting' AND `res`.`me_status` IN (". implode( ',', $statusArr ) . ") )";
			endif;		
		else:
			#$where[] = "`ti`.`ti_type` = 'meeting'";
			if( count( $statusArr ) > 0 ):
				$where[] = " `ti`.`ti_type` = 'meeting' AND `res`.`me_status` IN (". implode( ',', $statusArr ) . ")";
			else:
				$where[] = "`ti`.`ti_type` = 'meeting'";
			endif;
		endif;

		if( $showHidden == false ):
			$where[] = "( `ti`.`ti_roomID` = 0 ) or ( `ti`.`ti_roomID` != 0 and `cont`.`roomCont_hideDaily` = 0 )";
		endif;
		# date restriction

		if( !empty( $date ) ):
			$startDateStamp = date( 'Y-m-d H:i:s', $date );
			$endDateStamp = date( 'Y-m-d H:i:s', $date + 86400 );
			
			$where[] = "`ti_startTime` < '{$endDateStamp}' and `ti_endTime` >= '{$startDateStamp}'";
		endif;
	
		if( count( $where ) > 0 ):
			$where = ' WHERE (' . implode( ') AND (', $where ) . ')';
		else:
			$where = NULL;
		endif;
		
		$sql = "	SELECT 
					`res`.`res_id`, 
					`ti`.`ti_startTime` as `startTime`, 
					`ti`.`ti_endTime` as `endTime`, 
					`ti`.`ti_roomID` as `roomID`, 
					`ti`.`ti_created` as `created`, 
					`ti`.`ti_type` as `type`, 
					`ti`.`ti_noLocation_branch` as `noLocation_branch`, 
					
					IF( `ti`.`ti_type` = 'meeting', `res`.`me_eventName`, `res`.`ev_title` ) as `eventName`, 
					IF( `ti`.`ti_type` = 'meeting', `res`.`me_desc`, `res`.`ev_desc` ) as `desc`, 
					
					
					`res`.`me_numAttend` as `numAttend`, 
					IF( `ti`.`ti_type` = 'meeting', `res`.`me_contactName`, `res`.`ev_publicName` ) as `contactName`, 
					
					
					
					`res`.`me_libcardNum` as `libcardNum`, 
					`res`.`me_social` as `isSocial`, 
					`res`.`me_contactPhonePrimary` as `contactPhonePrimary`, 
					`res`.`me_contactPhoneSecondary` as `contactPhoneSecondary`, 
					`res`.`me_contactAddress1` as `contactAddress1`, 
					`res`.`me_contactAddress2` as `contactAddress2`, 
					`res`.`me_contactCity` as `contactCity`, 
					`res`.`me_contactState` as `contactState`, 
					`res`.`me_contactZip` as `contactZip`, 
					`res`.`me_contactEmail` as `contactEmail`, 
					`res`.`me_contactWebsite` as `contactWebsite`, 
					`res`.`me_nonProfit` as `nonProfit`, 
					`res`.`me_amenity` as `amenity`, 
					`res`.`me_status` as `status`, 
					`res`.`me_notes` as `notes` 

				FROM `{$table_name}` as `ti` 
				LEFT JOIN `{$table_nameRes}` as `res` ON `ti`.`ti_extID` = `res`.`res_id` 
				LEFT JOIN `{$wpdb->prefix}bookaroom_roomConts` as `cont` ON `ti`.`ti_roomID` = `cont`.`roomCont_ID` 
				{$where} 
				ORDER BY `res`.`me_status`, `ti`.`ti_startTime`";
		$temp = $wpdb->get_results( $sql, ARRAY_A );
		
		if( empty( $temp ) ):
			 $final = NULL;
		else:
			foreach( $temp as $key => $val ):
				if( $val['status'] == 'pendPayment' and $val['nonProfit']  == true ):
					$val['status'] = '501C3';

				endif;
				
				$final['status'][$val['status']][$val['res_id']] = $val['res_id'];
				$final['id'][$val['res_id']] = $val;
				$final['status']['all'][$val['res_id']] = $val['res_id'];
				
				#if( empty( $val['noLocation_branch'] )):
				if( !empty( $val['roomID'] )):
				
					$final['location'][$branchList[$roomContList['id'][$val['roomID']]['branchID']]['branchDesc']][$roomContList['id'][$val['roomID']]['desc']][] = $val;
				else:
				
					
					$final['location'][$branchList[$val['noLocation_branch']]['branchDesc']]['No location specified'][] = $val;
					
				endif;
			endforeach;
		endif;
		
		return $final;
	}
	
	public static function getDaily( $date )
	{
		global $wpdb;
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		$table_name = $wpdb->prefix . "bookaroom_times";
		$table_nameRes = $wpdb->prefix . "bookaroom_reservations";
		
		$where = array();
		
		$startDateStamp = date( 'Y-m-d H:i:s', $date );
		$endDateStamp = date( 'Y-m-d H:i:s', $date + 86400 );
		$where[] = "`ti_startTime` < '{$endDateStamp}' and `ti_endTime` >= '{$startDateStamp}'";
		$where[] = "( `ti`.`ti_type` = 'meeting' AND `res`.`me_status` = 'approved' ) OR ( `ti`.`ti_type` = 'event' AND `res`.`ev_noPublish` = 0 )";
		
			
		if( count( $where ) > 0 ):
			$where = ' WHERE (' . implode( ') AND (', $where ) . ')';
		else:
			$where = NULL;
		endif;
		
		$sql = "	SELECT 
					`res`.`res_id`, 
					`ti`.`ti_startTime` as `startTime`, 
					`ti`.`ti_endTime` as `endTime`, 
					`ti`.`ti_roomID` as `roomID`, 
					`ti`.`ti_created` as `created`, 
					`ti`.`ti_type` as `type`, 
					`ti`.`ti_noLocation_branch` as `noLocation_branch`, 
					`res`.`me_numAttend` as `numAttend`, 
					`res`.`me_contactPhonePrimary` as `contactPhonePrimary`, 
					`res`.`me_contactPhoneSecondary` as `contactPhoneSecondary`, 
					`res`.`me_contactAddress1` as `contactAddress1`, 
					`res`.`me_contactAddress2` as `contactAddress2`, 
					`res`.`me_contactCity` as `contactCity`, 
					`res`.`me_contactState` as `contactState`, 
					`res`.`me_contactZip` as `contactZip`, 
					`res`.`me_contactEmail` as `contactEmail`, 
					`res`.`me_contactWebsite` as `contactWebsite`, 
					`res`.`me_nonProfit` as `nonProfit`, 
					`res`.`me_amenity` as `amenity`, 
					`res`.`me_status` as `status`, 
					`res`.`me_notes` as `notes` 
					IF( `ti`.`ti_type` = 'meeting', `res`.`me_contactName`, `res`.`ev_publicName` ) as `contactName`, 
					IF( `ti`.`ti_type` = 'meeting', `res`.`me_eventName`, `res`.`ev_title` ) as `eventName`, 
					IF( `ti`.`ti_type` = 'meeting', `res`.`me_desc`, `res`.`ev_desc` ) as `desc`, 

				FROM `{$table_name}` as `ti` 
				LEFT JOIN `{$table_nameRes}` as `res` ON `ti`.`ti_extID` = `res`.`res_id` 
				LEFT JOIN `{$wpdb->prefix}bookaroom_roomConts` as `cont` ON `ti`.`ti_roomID` = `cont`.`roomCont_ID` 
				{$where} 
				ORDER BY `res`.`me_status`, `ti`.`ti_startTime`";
		$temp = $wpdb->get_results( $sql, ARRAY_A );
		
		if( empty( $temp ) ):
			 $final = NULL;
		else:
			foreach( $temp as $key => $val ):
				if( $val['status'] == 'pendPayment' and $val['nonProfit']  == true ):
					$val['status'] = '501C3';

				endif;
				
				$final['status'][$val['status']][$val['res_id']] = $val['res_id'];
				$final['id'][$val['res_id']] = $val;
				$final['status']['all'][$val['res_id']] = $val['res_id'];
				
				if( empty( $val['noLocation_branch'] )):
					@$final['location'][$branchList[$roomContList['id'][$val['roomID']]['branchID']]['branchDesc']][$roomContList['id'][$val['roomID']]['desc']][] = $val;
				else:
					@$final['location'][$branchList[$val['noLocation_branch']]['branchDesc']]['No location specified'][] = $val;
					
				endif;
			endforeach;
		endif;
		
		return $final;
	}
	
	public static function bookaroom_approvedRequests()
	{
		self::bookaroom_pendingRequests( 'approved' );
	}
	
	public static function bookaroom_archivedRequests()
	{
		self::bookaroom_pendingRequests( 'archived' );
	}

	public static function bookaroom_contactList()
	{
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-public.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$externals = self::getExternals();
		
		$filename = BOOKAROOM_PATH . 'templates/meetings/contactList.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# date
		if( empty( $externals['timestamp'] ) or strtotime( $externals['timestamp'] ) == false ):
			$timestamp = time();
		else:
			$timestamp = strtotime( $externals['timestamp'] );
		endif;

		$contents = str_replace( '#dateChosen#', date( 'l, F jS, Y', $timestamp ), $contents );
		
		# get reservations for date
		
		$pendingList = self::getPending( $timestamp );
		
		$niceTime = date( 'm/d/Y', $timestamp );
	
		$contents = str_replace( '#formDate#', $niceTime, $contents );
		
		# get lines
		$contact_line	= repCon( 'contact', $contents );
		$none_line		= repCon( 'none', $contents, TRUE );
		
		# none
		
		if( empty( $pendingList['id'] )):
			$contents = str_replace( '#contact_line#', $none_line, $contents );
		else:		
		# some
			$temp = NULL;
			$final = array();
			$typeArr = array( 'pending' => 'New Pend.', 'pendPayment' => 'Pend. Payment', '501C3' => 'Pend 501(c)3', 'approved' => 'Approved', 'denied' => 'Denied', 'archived' => 'Archived' );
			
			foreach( $pendingList['id'] as $key => $val ):
				if( $val['status'] == 'denied' ):
					continue;
				endif;

				$temp = $contact_line;
				$temp = str_replace( '#name#', $val['contactName'], $temp );
				$temp = str_replace( '#phone#', self::prettyPhone( $val['contactPhonePrimary'] ), $temp );
				if( empty( $contactPhoneSecondary ) ):
					$secondary = NULL;
				else:
					$secondary = '<br />'.self::prettyPhone( $val['contactPhoneSecondary'] );
				endif;
				$temp = str_replace( '#phone2#', $secondary, $temp );
				$temp = str_replace( '#email#', $val['contactEmail'], $temp );
				$temp = str_replace( '#meetingDesc#', htmlspecialchars_decode( $val['eventName'] ), $temp );
				$temp = str_replace( '#type#', ucwords( $val['type'] ), $temp );

				$temp = str_replace( '#branch#', $branchList[$roomContList['id'][$val['roomID']]['branchID']]['branchDesc'], $temp );
				$temp = str_replace( '#room#', $roomContList['id'][$val['roomID']]['desc'], $temp );

				$temp = str_replace( '#date#', date( 'l, F jS, Y', strtotime( $val['startTime'] ) ), $temp );
				$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $val['startTime'] ) ), $temp );
				$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $val['endTime'] ) ), $temp );
				
				$temp = str_replace( '#status#', $typeArr[$val['status']], $temp );
				
				$final[] = $temp;
			endforeach;
			$contents = str_replace( '#contact_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		echo $contents;
		
	}
	
	public static function bookaroom_dailyMeetings()
	{
		
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-public.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$externals = self::getExternals();
		$defaultEmail = get_option( 'bookaroom_defaultEmailDaily' );

		$filename = BOOKAROOM_PATH . 'templates/meetings/dailyMeetings.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );

		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$filename = BOOKAROOM_PATH . 'templates/meetings/dailyMeetingsEmail.html';	
		$handle = fopen( $filename, "r" );
		$contentsEmail = fread( $handle, filesize( $filename ) );

		#$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );

		fclose( $handle );
		
		# date
		if( empty( $externals['timestamp'] ) or strtotime( $externals['timestamp'] ) == false ):
			$timestamp = time();
		else:
			$timestamp = strtotime( $externals['timestamp'] );
		endif;
		
		# display version
		$contents = str_replace( '#dateChosen#', date( 'l, F jS, Y', $timestamp ), $contents );
		$contents = str_replace( '#defaultEmail#', $defaultEmail, $contents );
		# email version
		$contentsEmail = str_replace( '#dateChosen#', date( 'l, F jS, Y', $timestamp ), $contentsEmail );
		
		# get reservations for date
		$viewAll = ( !empty( $externals['viewAll'] ) ) ? array( 'approved', 'pending', 'pendPayment' ) : array( 'approved' );
		
		$pendingList = self::getPending(  $timestamp, $viewAll, true, false );
		
		$niceTime = date( 'm/d/Y', $timestamp );
	
		$contents = str_replace( '#formDate#', $niceTime, $contents );
		
		if( !empty( $externals['viewAll'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#viewAll_checked#', $checked, $contents );
		
		# lines for display
		$some_line = repCon( 'some', $contents );
		$none_line = repCon( 'none', $contents, TRUE );
		$reservation_line = repCon( 'reservation', $some_line);
		$reservations_line = repCon( 'reservations', $some_line, TRUE);
		$subRes = repCon( 'subRes', $reservations_line);

		# lines for email
		$email_some_line = repCon( 'some', $contentsEmail );
		$email_none_line = repCon( 'none', $contentsEmail, TRUE );
		$email_reservation_line = repCon( 'reservation', $email_some_line);
		$email_reservations_line = repCon( 'reservations', $email_some_line, TRUE);
		$email_subRes = repCon( 'subRes', $email_reservations_line);

		if( count( $pendingList['location'] ) == 0 ):
			# display version
			$contents = str_replace( '#some_line#', $none_line, $contents );
			# email version
			$contentsEmail = str_replace( '#some_line#', $email_none_line, $contentsEmail );
		else:
			# display
			$contents = str_replace( '#some_line#', $some_line, $contents );
			# email
			$contentsEmail = str_replace( '#some_line#', $email_some_line, $contentsEmail );
			
			ksort( $pendingList['location'] );
			$final = array();
			$finalEmail = array();
			
			# cycle through branches
			$count = 1;
			
			
			foreach( $pendingList['location'] as $key => $val ):
				# cycle through rooms
				ksort( $val );
				
				foreach( $val as $rmKey => $room ):
					$eventName = htmlspecialchars_decode( $room[0]['eventName'] );
					$eventDesc = htmlspecialchars_decode( $room[0]['desc'] );
					
					if( $room[0]['type'] == 'meeting' ):
						if( $room[0]['status'] == 'pending' ):
							$eventName = '<em><strong>Pending:</strong></em><br />'.$eventName;										
							$eventDesc = '<em><strong>Pending:</strong></em><br />'.$eventDesc;
						elseif( $room[0]['status'] == 'pendPayment' ):
							$eventName = '<em><strong>Pending Payment:</strong></em><br />'.$eventName;
							$eventDesc = '<em><strong>Pending Payment:</strong></em><br />'.$eventDesc;
						elseif( $room[0]['status'] == '501C3' ):
							$eventName = '<em><strong>Pending Payment 501(3)(c):</strong></em><br />'.$eventName;
							$eventDesc = '<em><strong>Pending Payment 501(3)(c):</strong></em><br />'.$eventDesc;
						endif;
					endif;

					$bgColor = ( $count++%2 ) ? '#FFF'  : '#DDD';
					# if only 1
					if( count( $room ) == 1 ):
						# display
						$temp = $reservation_line;
						
						$temp = str_replace( '#type#', ucwords( $room[0]['type'] ), $temp );						
						$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $room[0]['startTime'] ) ), $temp );
						$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $room[0]['endTime'] ) ), $temp );
						#$temp = str_replace( '#organization#', htmlspecialchars_decode( $room[0]['eventName'] ), $temp );
						#$temp = str_replace( '#desc#', htmlspecialchars_decode( $room[0]['desc'] ), $temp );
						$temp = str_replace( '#organization#', $eventName, $temp );
						$temp = str_replace( '#desc#', $eventDesc, $temp );						
						
						# email
						$tempEmail = $email_reservation_line;
						$tempEmail = str_replace( '#branchName#', $key, $tempEmail );
						$tempEmail = str_replace( '#roomName#', $rmKey, $tempEmail );
						$tempEmail = str_replace( '#startTime#', date( 'g:i a', strtotime( $room[0]['startTime'] ) ), $tempEmail );
						$tempEmail = str_replace( '#endTime#', date( 'g:i a', strtotime( $room[0]['endTime'] ) ), $tempEmail );
						$tempEmail = str_replace( '#organization#', htmlspecialchars_decode( $room[0]['eventName'] ), $tempEmail );
						$tempEmail = str_replace( '#desc#', htmlspecialchars_decode( $room[0]['desc'] ), $tempEmail );
						$tempEmail = str_replace( '#bgColor#', $bgColor, $tempEmail );
						# amenity
						if( empty( $room[0]['amenity'] ) ):
							$temp = str_replace( '#amenities#', '&nbsp;', $temp );
							$tempEmail = str_replace( '#amenities#', '&nbsp;', $tempEmail );
						else:
							$amenFinal = array();
							foreach( unserialize( $room[0]['amenity'] ) as $amenity ):
								$amenFinal[] = $amenityList[$amenity];
							endforeach;
							$temp = str_replace( '#amenities#', implode( ', ', $amenFinal ), $temp );
							$tempEmail = str_replace( '#amenities#', implode( ', ', $amenFinal ), $tempEmail );
						endif;
						if( empty( $room[0]['roomID']) and !empty( $room[0]['noLocation_branch'] ) ):
							$temp = str_replace( '#branchName#', $branchList[$room[0]['noLocation_branch']]['branchDesc'], $temp );
							$temp = str_replace( '#roomName#', 'No location specified.', $temp );
						else:
							$temp = str_replace( '#branchName#', $key, $temp );
							$temp = str_replace( '#roomName#', $rmKey, $temp );
						endif;
						$final[] = $temp;
						$finalEmail[] = $tempEmail;
					else:
						# display
						$temp = $reservations_line;
						$temp = str_replace( '#rowspan#', count( $room ), $temp );
						$temp = str_replace( '#branchName#', $key, $temp );
						$temp = str_replace( '#roomName#', $rmKey, $temp );
						# email
						$tempEmail = $email_reservations_line;
						$tempEmail = str_replace( '#rowspan#', count( $room ), $tempEmail );
						$tempEmail = str_replace( '#branchName#', $key, $tempEmail );
						$tempEmail = str_replace( '#roomName#', $rmKey, $tempEmail );
						$tempEmail = str_replace( '#bgColor#', $bgColor, $tempEmail );
						
						$finalRoom = array();
						$finalRoomEmail = array();
						
						$first = TRUE;
						
						#display
						$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $room[0]['startTime'] ) ), $temp );
						$temp = str_replace( '#type#', ucwords( $room[0]['type'] ), $temp );
						$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $room[0]['endTime'] ) ), $temp );
						#$temp = str_replace( '#organization#', htmlspecialchars_decode( $room[0]['eventName'] ), $temp );						
						#$temp = str_replace( '#desc#', htmlspecialchars_decode( $room[0]['desc'] ), $temp );
						$temp = str_replace( '#organization#', $eventName, $temp );						
						$temp = str_replace( '#desc#', $eventDesc, $temp );
					
						# email
						$tempEmail = str_replace( '#startTime#', date( 'g:i a', strtotime( $room[0]['startTime'] ) ), $tempEmail );
						$tempEmail = str_replace( '#endTime#', date( 'g:i a', strtotime( $room[0]['endTime'] ) ), $tempEmail );
						$tempEmail = str_replace( '#desc#', $room[0]['desc'], $tempEmail );
						$tempEmail = str_replace( '#organization#', htmlspecialchars_decode( $room[0]['eventName'] ), $tempEmail );
						$tempEmail = str_replace( '#desc#', htmlspecialchars_decode( $room[0]['desc'] ), $tempEmail );
	
						# amenity
						if( empty( $room[0]['amenity'] ) ):
							$temp = str_replace( '#amenities#', '&nbsp;', $temp );
							$tempEmail = str_replace( '#amenities#', '&nbsp;', $tempEmail );
						else:
							$amenFinal = array();
							foreach( unserialize( $room[0]['amenity'] ) as $amenity ):
								$amenFinal[] = $amenityList[$amenity];
							endforeach;
							$temp = str_replace( '#amenities#', implode( ', ', $amenFinal ), $temp );
							$tempEmail = str_replace( '#amenities#', implode( ', ', $amenFinal ), $tempEmail );
						endif;
						

						
						foreach( $room as $roomVal ):
							$eventName = htmlspecialchars_decode( $roomVal['eventName'] );
							$eventDesc = htmlspecialchars_decode( $roomVal['desc'] );
							if( $roomVal['type'] == 'meeting' ):
								if( $roomVal['status'] == 'pending' ):
									$eventName = '<em><strong>Pending:</strong></em><br />'.$eventName;										
									$eventDesc = '<em><strong>Pending:</strong></em><br />'.$eventDesc;
								elseif( $roomVal['status'] == 'pendPayment' ):
									$eventName = '<em><strong>Pending Payment:</strong></em><br />'.$eventName;
									$eventDesc = '<em><strong>Pending Payment:</strong></em><br />'.$eventDesc;
								endif;
							endif;

							if( $first == TRUE ):
								$first = FALSE;
								continue;
							endif;
							$bgColor = ( $count++%2 ) ? '#FFF'  : '#DDD';
							# display
							$tempRoom = $subRes;
							$tempRoom = str_replace( '#startTime#', date( 'g:i a', strtotime( $roomVal['startTime'] ) ), $tempRoom );
							$tempRoom = str_replace( '#endTime#', date( 'g:i a', strtotime( $roomVal['endTime'] ) ), $tempRoom );
							#$tempRoom = str_replace( '#organization#', htmlspecialchars_decode( $roomVal['eventName'] ), $tempRoom );
							#$tempRoom = str_replace( '#desc#', htmlspecialchars_decode( $roomVal['desc'] ), $tempRoom );
							$tempRoom = str_replace( '#organization#', $eventName, $tempRoom );
							$tempRoom = str_replace( '#desc#', $eventDesc, $tempRoom );
							
							# display
							$tempRoomEmail = $email_subRes;
							$tempRoomEmail = str_replace( '#startTime#', date( 'g:i a', strtotime( $roomVal['startTime'] ) ), $tempRoomEmail );
							$tempRoomEmail = str_replace( '#endTime#', date( 'g:i a', strtotime( $roomVal['endTime'] ) ), $tempRoomEmail );
							$tempRoomEmail = str_replace( '#organization#', htmlspecialchars_decode( $roomVal['eventName'] ), $tempRoomEmail );
							$tempRoomEmail = str_replace( '#desc#', htmlspecialchars_decode( $roomVal['desc'] ), $tempRoomEmail );
							$tempRoomEmail = str_replace( '#bgColor#', $bgColor, $tempRoomEmail );
	
							# amenity
							if( empty( $roomVal['amenity'] ) ):
								$tempRoom = str_replace( '#amenities#', '&nbsp;', $tempRoom );
								$tempRoomEmail = str_replace( '#amenities#', '&nbsp;', $tempRoomEmail );
							else:
								$amenFinal = array();
								foreach( unserialize( $roomVal['amenity'] ) as $amenity ):
									$amenFinal[] = $amenityList[$amenity];
								endforeach;
								$tempRoom = str_replace( '#amenities#', implode( ', ', $amenFinal ), $tempRoom );
								$tempRoomEmail = str_replace( '#amenities#', implode( ', ', $amenFinal ), $tempRoomEmail );
							endif;
						
							$finalRoom[] = $tempRoom;	
							$finalRoomEmail[] = $tempRoomEmail;
							
						endforeach;
						$temp = str_replace( '#subRes_line#', implode( "\r\n", $finalRoom ), $temp );
						$tempEmail = str_replace( '#subRes_line#', implode( "\r\n", $finalRoomEmail ), $tempEmail );
						

						
						$final[] = $temp;
						$finalEmail[] = $tempEmail;
						
					endif;

				endforeach;
			endforeach;
			
			$contents = str_replace( '#reservation_line#', implode( "\r\n", $final ), $contents );
			$contentsEmail = str_replace( '#reservation_line#', implode( "\r\n", $finalEmail ), $contentsEmail );
			
		endif;

		# email		
		if( $externals['action'] == 'sendEmail' ):
		
			if( empty( $externals['emailAddress'] ) || !filter_var( $externals['emailAddress'], FILTER_VALIDATE_EMAIL ) ):
				$contents = str_replace( '#emailError#', "Please enter a valid email address for the <strong>Email</strong> field.", $contents );
			else:
				$fromName	= get_option( 'bookaroom_alertEmailFromName' );
				$fromEmail	= get_option( 'bookaroom_alertEmailFromEmail' );
				$replyToOnly	= ( true == get_option( 'bookaroom_emailReplyToOnly' ) ) ? "From: {$fromName}\r\nReply-To: {$fromName} <$fromEmail>\r\n" : "From: {$fromName} <$fromEmail>\r\n";

				$headers  = 'MIME-Version: 1.0' . "\r\n";
				$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
				$headers .= "{$replyToOnly}: {$fromName} <$fromEmail>\r\n";
				$headers .=	'X-Mailer: PHP/' . phpversion();
				
				mail( $externals['emailAddress'], 'Meeting Room Schedule for '. date( 'l, F jS, Y', $timestamp ), $contentsEmail, $headers );
				$contents = str_replace( '#emailError#', "Your email was sent.", $contents );				
			endif;
		else:
			$contents = str_replace( '#emailError#', '&nbsp;', $contents );
		endif;
		
		
		# daily print out templates
		$_SESSION['bookaroom_meetingRooms_pendingList'] = serialize( $pendingList );
		
		
		echo $contents;
	}	
	
	public static function bookaroom_deniedRequests()
	{
		self::bookaroom_pendingRequests( 'denied' );
	}
	
	public static function bookaroom_pendingPayment()
	{
		self::bookaroom_pendingRequests( 'pendPayment' );

	}
	
	public static function bookaroom_pending501C3()
	{
		self::bookaroom_pendingRequests( '501C3' );

	}	
	
	public static function showChangeReservationMeeting( $externals, $errorMSG = NULL, $res_id = NULL )
	#, $branchList, $roomContList, $roomList, $amenityList, $errorMSG = NULL, $res_id = NULL, $contents = NULL )
	{
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		if( empty( $res_id ) && !empty( $externals['res_id'] ) ):
			$res_id = $externals['res_id'];
		endif;
		
		$roomID = $externals['roomID'];
		#$branchID = $externals['branchID'];
		$timestamp = $externals['timestamp'];
		$hours = $externals['hours'];
		
		if( empty( $contents ) ):
			$filename = BOOKAROOM_PATH . 'templates/meetings/pending_changeReservation.html';	
			$handle = fopen( $filename, "r" );
			$contents = fread( $handle, filesize( $filename ) );
			fclose( $handle );
		endif;

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		#require_once( BOOKAROOM_PATH . '/bookaroom-meetings-public.php' );
		
		#errors
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );			
		endif;
		# find time information

		# day of the week
		if( empty( $timestamp ) ):
			$timestamp = current_time('timestamp');
		endif;
		# get branch and room
		# if Room ID is not empty and is valid
		if( !empty( $roomContList ) && !empty( $roomID ) && in_array( $roomID, array_keys( $roomContList['id'] ) ) ):
			# find that room's branch
			# if there is no valid branch, both are null (can't have a 
			# valid room that has no branch)
			if( empty( $roomContList['id'][$roomID]['branchID'] ) ):
				$branchID = NULL;
				$roomID = NULL;
			# if valid branch, map it.
			else:
				$branchID = $roomContList['id'][$roomID]['branchID'];
			endif;
		# if the room is empty, check for a branch ID, make sure it's valid
		elseif( !empty( $branchID ) && in_array( $branchID, array_keys( $branchList ) ) ):
			# since we have a valid branch, lets see if there are any rooms (make sure that
			# the room list is an array and not empty )
			if( !empty( $roomContList['branch'][$branchID] ) && is_array( $roomContList['branch'][$branchID] ) ):
				# make the room ID the first room
				$roomID = current( $roomContList['branch'][$branchID] );
			else: 
				# if no rooms, show no available rooms
				$roomID = NULL;
			endif;

		else:
			$branchID = NULL;
			$roomID = NULL;
		endif;

		# get lines
		$branchSome_line		= repCon('branchSome', $contents );
		$branchSelected_line	= repCon('branchSelected', $contents, TRUE );
		$branchNoneExist_line	= repCon('branchNoneExist', $contents, TRUE );

		$roomSome_line			= repCon('roomSome', $contents);
		$roomSelected_line		= repCon('roomSelected', $contents, TRUE);
		$roomNoneOrBranch_line	= repCon('roomNoneOrBranch', $contents, TRUE );
		$roomNoneExist_line		= repCon('roomNoneExist', $contents, TRUE );
		$roomNoBranch_line		= repCon('roomNoBranch', $contents, TRUE );
		$roomClosed_line		= repCon('roomClosed', $contents, TRUE );

		# branch
		############################################
		# check for branch with no rooms
		
		# check if no branches
		if( count( $branchList ) == 0 ):
			$contents = str_replace( '#branchSome_line#', $branchNoneExist_line, $contents );
			$contents = str_replace( '#roomSome_line#', $roomNoneOrBranch_line, $contents );
			echo $contents;
			return FALSE;
		# if branches, display them
		else:
			if( empty( $branchID ) ):
				$branchID = key( $branchList );
			endif;
			
			$temp = NULL;
			$final = array();
			
			foreach( $branchList as $key => $val ):
				# is this the current branch?
				if( !empty( $branchID ) && $branchID == $key ):
					$temp = $branchSelected_line;

					$temp = str_replace( '#branchName#', $val['branchDesc'], $temp );
					$final[] = $temp;
				# non-selected branch
				else:
					$temp = $branchSome_line;
					$temp = str_replace( '#branchName#', $val['branchDesc'], $temp );
					$temp = str_replace( '#branchID#', $val['branchID'], $temp );
					$temp = str_replace( '#timestamp#', $timestamp, $temp );						
					$final[] = $temp;
				endif;
			endforeach;
			$contents = str_replace( '#branchSome_line#', implode( "\r\n", $final ), $contents );
		endif;
		# rooms
		#######################################################
		# if no rooms in branch show error

		if( empty( $roomContList['branch'][$branchID] ) ):
			$contents = str_replace( '#roomSome_line#', $roomNoneExist_line, $contents );
		else:
			# if roomID is empty, pick first room
			if( empty( $roomID ) ):
				$roomID = current( $roomContList['branch'][$branchID] );
			endif;
			
			# cycle rooms
			$temp = NULL;
			$final = array();

			foreach( $roomContList['branch'][$branchID] as $key => $val ):
	
				# if container is empty, skip
				if( empty( $roomContList['id'][$val]['rooms'] ) ):
					continue;
				endif;
				# is this the current room?
				if( $roomID == $val ):
					$temp = $roomSelected_line;
					$temp = str_replace( '#roomName#', $roomContList['id'][$val]['desc'], $temp );
					$temp = str_replace( '#occupancy#', $roomContList['id'][$val]['occupancy'], $temp );					
					$final[] = $temp;
				# non-selected branch
				else:
					$temp = $roomSome_line;
					$temp = str_replace( '#roomName#', $roomContList['id'][$val]['desc'], $temp );
					$temp = str_replace( '#occupancy#', $roomContList['id'][$val]['occupancy'], $temp );
					$temp = str_replace( '#roomID#', $val, $temp );
					$temp = str_replace( '#timestamp#', $timestamp, $temp );
					$final[] = $temp;	
				endif;
			endforeach;
			$contents = str_replace( '#roomSome_line#', implode( "\r\n", $final ), $contents );		
		endif;
		
		$contents = bookaroom_public::showRooms_smallCalendar( $contents, $roomID, $timestamp );
		
		# If no rooms, show no rooms in time area
		$hoursRoomsSome = repCon( 'hoursRoomsSome', $contents );
		$hoursRoomsNone = repCon( 'hoursRoomsNone', $contents, TRUE );
		$hoursRoomsClosed = repCon( 'hoursRoomsClosed', $contents, TRUE );
		
		$dayOfWeek = date( 'w', $timestamp );

		if( empty( $roomContList['branch'][$branchID] ) ):
			$contents = str_replace( '#hoursRoomsSome_line#', $hoursRoomsNone, $contents );

		elseif( empty( $branchList[$branchID]["branchOpen_{$dayOfWeek}"] ) || empty( $branchList[$branchID]["branchClose_{$dayOfWeek}"] ) ):
			$contents = str_replace( '#hoursRoomsSome_line#', $hoursRoomsClosed, $contents );
		else:
			$contents = str_replace( '#hoursRoomsSome_line#', $hoursRoomsSome, $contents );
			
			$hourReg_line			= repCon( 'hourReg', $contents );
			$hourReserved_line		= repCon( 'hourReserved', $contents, TRUE );
			$hourUnavailable_line	= repCon( 'hourUnavailable', $contents, TRUE );
			$hourSetup_line			= repCon( 'hourSetup', $contents, TRUE );
			$hourLast_line			= repCon( 'hourLast', $contents, TRUE );
			
			$timeInfo = getdate( $timestamp );
			$baseIncrement = get_option( 'bookaroom_baseIncrement' );
			
			$openTime = strtotime( date( 'Y-m-d '.$branchList[$branchID]["branchOpen_{$dayOfWeek}"], $timestamp ) );
			$closeTime = strtotime( date( 'Y-m-d '.$branchList[$branchID]["branchClose_{$dayOfWeek}"], $timestamp ) );

			$increments = ( ( $closeTime - $openTime ) / 60 ) / $baseIncrement;
			
			$temp = NULL;
			$final = array();

			## if this date is before the reserve buffer, make normal days setups
			#$reserveBuffer = get_option( 'bookaroom_reserveBuffer' );
			
			#$tonightInfo = getdate( current_time('timestamp') );
			#$tonight = mktime( 23, 59, 59, $tonightInfo['mon'], $tonightInfo['mday'] + $reserveBuffer, $tonightInfo['year'] );
			#
			#if( $timestamp <= $tonight ):
			#	$hourReg_line = $hourUnavailable_line;
			#endif;

			# if this date is after the allowed buffer, make normal days setups
			#$allowedBuffer = get_option( 'bookaroom_reserveAllowed' );
			
			#$tonightInfo = getdate( current_time('timestamp') );
			#$tonight = mktime( 23, 59, 59, $tonightInfo['mon'], $tonightInfo['mday'] + $allowedBuffer, $tonightInfo['year'] );
			
			#if( $timestamp >= $tonight ):
			#	$hourReg_line = $hourUnavailable_line;
			#endif;
			
			# get closings
			$closings = bookaroom_public::getClosings( $roomID, $timestamp, $roomContList );
			if( $closings !== false ):
				$hourReg_line = $hourReserved_line;
			endif;
			
			# get reservations			
			$reservations = bookaroom_public::getReservations( $roomID, $timestamp, NULL, $res_id );
			$incrementList = array();
			$cleanupIncrements = get_option( 'bookaroom_cleanupIncrement' );
			$setupIncrements = get_option( 'bookaroom_setupIncrement' );
			
			for( $i = 0; $i < $increments; $i++):
				# find increment offset  from start
				$curStart = $openTime + (  $baseIncrement * 60 * $i);
				$curEnd = $openTime + (  $baseIncrement * 60 * ($i+1) );
			
				# is checked?
				if( @in_array( $curStart, $hours ) ):
				$incrementList[$i]['checked'] = ' checked="checked"';
				else:
					$incrementList[$i]['checked'] = NULL;
				endif;
				
				if( $curEnd > $closeTime ):
					$curEnd = $closeTime; 
				endif;
				# last line?

				if( $i + $cleanupIncrements >= $increments ):
					$incrementList[$i]['type'] = 'last';
				else:	
					if( empty( $reservations ) ):
						$incrementList[$i]['type'] = 'regular';
					else:
						foreach( $reservations as $resKey => $resVal ):
							$resVal['timestampStart'] = strtotime( $resVal['ti_startTime'] );
							$resVal['timestampEnd'] = strtotime( $resVal['ti_endTime'] );
							
							# check if increment time is equal to or after start and before end
							if( $curStart >= $resVal['timestampStart'] && $curEnd <= $resVal['timestampEnd'] && $res_id !== $resVal['res_id'] ):
								$incrementList[$i]['type'] = 'reserved';
								$incrementList[$i]['desc'] =  ( !empty( $resVal['ev_title'] ) ) ? $resVal['ev_title'] : $resVal['me_eventName'];
								if( $curStart == $resVal['timestampStart'] ):
									# setup time
									for( $s = $i-1; $s > ( $i-1-$setupIncrements ); $s--):
										if( !empty( $incrementList[$s]['type'] ) && $incrementList[$s]['type'] !== 'reserved' ):
											$incrementList[$s]['type'] = 'setup';
										endif;
									endfor;
								endif;
								#cleanup time
								$count = 1;
								if( $curEnd == $resVal['timestampEnd'] ):
									for( $s = $i+1; $s < ( $i+1+$cleanupIncrements ); $s++):
									if( $count++ > 20 ) die();
											$incrementList[$s]['type'] = 'setup';
									endfor;
								endif;
							else:
								if( empty( $incrementList[$i]['type'] ) ):
									$incrementList[$i]['type'] = 'regular';
								endif;
							endif;
						endforeach;
					endif;
				endif;
			endfor;
			
			for( $i = 0; $i < $increments; $i++):
				# find increment offset  from start
				switch( $incrementList[$i]['type'] ):
					case 'setup':
						$temp = $hourSetup_line;
						break;
					
					case 'reserved':
						$temp = $hourReserved_line;
						break;
						
					case 'regular':
						$temp = $hourReg_line;
						break;
						
					case 'last':
						$temp = $hourLast_line;
						break;
						
					default:
						$temp = $hourSetup_line;
						break;
						
				endswitch;
				$curStart = $openTime + (  $baseIncrement * 60 * $i);
				$curEnd = $openTime + (  $baseIncrement * 60 * ($i+1) );
				
				if( $curEnd > $closeTime ):
					$curEnd = $closeTime; 
				endif;
				$timeDisp = date( 'g:i a', $curStart ) .' - '. date( 'g:i a', $curEnd );
				$temp = str_replace( '#timeDisp#', $timeDisp, $temp );

				$temp = str_replace( '#desc#', $incrementList[$i]['desc'], $temp );
				#$temp = str_replace( '#desc#', 'In Use', $temp );
		
		
				$temp = str_replace( '#hoursCount#', $i, $temp );
				$temp = str_replace( '#iterTimestamp#', $curStart, $temp );
				if( !empty( $closings ) ):
					$temp = str_replace( '#desc#', 'Closed: '.$closings, $temp );
				elseif( !empty( $incrementList[$i]['desc'] ) ):
					$temp = str_replace( '#desc#', $incrementList[$i]['desc'], $temp );					
				endif;
				$temp = str_replace( '#hoursChecked#', $incrementList[$i]['checked'], $temp );
				
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#hourReg_line#', implode( "\r\n", $final ), $contents );
			
			# background and font colors
			$contents = str_replace( '#setupColor#', get_option( 'bookaroom_setupColor' ), $contents );
			$contents = str_replace( '#setupFont#', get_option( 'bookaroom_setupFont' ), $contents );
			$contents = str_replace( '#reservedColor#', get_option( 'bookaroom_reservedColor' ), $contents );
			$contents = str_replace( '#reservedFont#', get_option( 'bookaroom_reservedFont' ), $contents );
		endif;
		
		$contents = str_replace( '#timestamp#', $timestamp, $contents );
		$contents = str_replace( '#roomID#', $roomID, $contents );
		$contents = str_replace( '#res_id#', $res_id, $contents );
		
		echo $contents;
	}

	public static function bookaroom_dailyRoomSigns()
	{
		
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-public.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$externals = self::getExternals();

		$filename = BOOKAROOM_PATH . 'templates/meetings/dailyRoomSigns.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );

		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# date
		if( empty( $externals['timestamp'] ) or strtotime( $externals['timestamp'] ) == false ):
			$timestamp = strtotime( 'today' );
		else:
			$timestamp = strtotime( $externals['timestamp'] );
		endif;
		
		$contents = str_replace( '#timestamp#', date('m/d/Y', $timestamp ), $contents );
		
		$contents = str_replace( '#date#', date('l, F jS, Y', $timestamp ), $contents );
		
		# branch
		$branch_line = repCon( 'branch', $contents );
		$temp = NULL;
		$final = array();
		
		if( empty( $externals['branchID'] ) or !array_key_exists( $externals['branchID'], $branchList ) ):
			$externals['branchID'] = key( $branchList );
		endif;
		
		foreach( $branchList as $key => $val ):
			$temp = $branch_line;
			$selected = ( $externals['branchID'] == $key ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#branch_desc#', $val['branchDesc'], $temp );
			$temp = str_replace( '#branch_val#', $key, $temp );
			$temp = str_replace( '#branch_selected#', $selected, $temp );
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#branch_line#', implode( "\r\n", $final ), $contents );
		
		# get meetings
		#$pendingList = self::getDaily( $timestamp );
		$pendingList = self::getPending(  $timestamp, array( 'approved' ), true, false, true );
		#$pendingList = self::getPending( $timestamp, array( 'approved' ), TRUE );
		
		if( empty( $pendingList['location'][$branchList[$externals['branchID']]['branchDesc']] ) ):
			$thisList = array();
		else:
			$thisList = $pendingList['location'][$branchList[$externals['branchID']]['branchDesc']];
			ksort( $thisList );
		endif;
		
		$room_line = repCon( 'room', $contents );
		$meeting_line = repCon( 'meeting', $room_line );
		
		$temp = NULL;
		$final = array();
		
		foreach( $thisList as $key => $val ):
			$temp = $room_line;
			
			$temp = str_replace( '#meetingRoom_name#', $key, $temp );
			
			$smallTemp = NULL;
			$smallFinal = array();
			foreach( $val as $smallKey => $smallVal ):
				$smallTemp = $meeting_line;	
				
				$startTime	= date( 'g:i a', strtotime( $smallVal['startTime'] ) );
				$endTime	= date( 'g:i a', strtotime( $smallVal['endTime'] ) );
				
				if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
					$times = 'All Day';
				else: 
					$times = $startTime.' - '.$endTime;
				endif;
				
				$smallTemp = str_replace( '#times#', $times, $smallTemp );
				$smallTemp = str_replace( '#meetingName#', htmlspecialchars_decode( $smallVal['eventName'] ), $smallTemp );
				
				$smallFinal[] = $smallTemp;
			endforeach;
			$temp = str_replace( '#meeting_line#', implode( "\r\n", $smallFinal ), $temp );
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#room_line#', implode( "\r\n", $final ), $contents );
		
		/*
		$contentsDaily = repCon( 'newDay', $contents );

		
		$temp = NULL;
		$final = array();
		
		$meeting_line = repCon( 'meeting', $contentsDaily );
		
		foreach( $thisList as $key => $val ):
			$temp = $contentsDaily;
			$temp = str_replace( '#meetingRoomName#', $key, $temp );
			
			$smallTemp = NULL;
			$smallFinal = array();
			
			foreach( $val as $smallKey => $smallVal ):
				$smallTemp = $meeting_line;
				
								$startTime	= date( 'g:i a', strtotime( $smallVal['startTime'] ) );
				$endTime	= date( 'g:i a', strtotime( $smallVal['endTime'] ) );
				
				if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
					$times = 'All Day';
				else: 
					$times = $startTime.' - '.$endTime;
				endif;
				
				$smallTemp = str_replace( '#times#', $times, $smallTemp );
				$smallTemp = str_replace( '#meetingName#', $smallVal['eventName'], $smallTemp );
				
				$smallFinal[] = $smallTemp;	
			endforeach;
			
			$temp = str_replace( '#meeting_line#', implode( "\r\n", $smallFinal ), $temp );
			
			$final[] = $temp;

		endforeach;
		
		$contents = str_replace( '#newDay_line#', implode( "\r\n", $final ), $contents );
		*/
		
		echo $contents;
	}
		
	protected static function showError( $errorType, $extVal )
	{
		$filename = BOOKAROOM_PATH . 'templates/meetings/pending_error.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$errorArr = array( 'res_id' );
		
		foreach( $errorArr as $val ):
			$varName = "{$val}_line";
			$$varName = repCon( $val, $contents );
			
			if( $errorType == $val ):
				$$varName = str_replace( '#extVal#', $extVal, $$varName );
				$contents = str_replace( "#$varName#", $$varName, $contents );
			else:
				$contents = str_replace( "#$varNamr#", NULL, $contents );
			endif;
		endforeach;
		
		echo $contents;
	}
	
	protected static function showForm_updateRequest( $externals, $branchList, $roomContList, $roomList, $amenityList, $res_id )
	{
		global $wpdb;
		
		$nonProfit = ( empty( $externals['nonProfit'] ) ) ? FALSE : TRUE;
		
		if( empty( $externals['amenity'] ) ):
			$amenity = NULL;
		else:
			$amenity = serialize( $externals['amenity'] );
		endif;
		
		$table_name = $wpdb->prefix . "bookaroom_times";
		
		$wpdb->update(	$table_name, 
						array( 
						
			'ti_startTime'				=> date( 'Y-m-d H:i:s', $externals['startTime'] ),
			'ti_endTime'				=> date( 'Y-m-d H:i:s', $externals['endTime'] ),
			'ti_roomID'					=> $externals['roomID'] ), 
						array( 'ti_extID' => $res_id ) );
						
		
		$social = ( empty( $externals['isSocial'] ) ) ? false : true;
		
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		
		$wpdb->update(	$table_name, 
						array( 
						
			'me_numAttend'				=> $externals['numAttend'],
			'me_eventName'				=> esc_textarea( $externals['eventName'] ),
			'me_desc'					=> esc_textarea( $externals['desc'] ),
			'me_contactName'			=> esc_textarea( $externals['contactName'] ),
			'me_contactPhonePrimary'	=> $externals['contactPhonePrimary'],
			'me_contactPhoneSecondary'	=> $externals['contactPhoneSecondary'],
			'me_contactAddress1'		=> esc_textarea( $externals['contactAddress1'] ),
			'me_contactAddress2'		=> esc_textarea( $externals['contactAddress2'] ),
			'me_contactCity'			=> esc_textarea( $externals['contactCity'] ),
			'me_contactState'			=> $externals['contactState'],
			'me_contactZip'				=> $externals['contactZip'],
			'me_contactEmail'			=> esc_textarea( $externals['contactEmail'] ),
			'me_contactWebsite'			=> esc_textarea( $externals['contactWebsite'] ),
			'me_notes'					=> esc_textarea( $externals['notes'] ),
			'me_nonProfit'				=> $nonProfit,
			'me_libcardNum'				=> esc_textarea( $externals['libcardNum'] ),
			'me_social'					=> $social,
			'me_amenity'				=> $amenity  ), 
						array( 'res_id' => $res_id ) );

		echo '<META HTTP-EQUIV="Refresh" Content="0; URL=?page=bookaroom_meetings&action=editCheckShow&res_id='.$res_id.'">';
				
		return TRUE;
	}
	
	protected static function showForm_updateRequestShow( $res_id, $search_settings )
	{
		$filename = BOOKAROOM_PATH . 'templates/meetings/pending_success.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#res_id#', $res_id, $contents );
				
		echo $contents;
	}

	public static function showGenericError( $header, $title, $message, $linkName, $linkURL )
	{
		$filename = BOOKAROOM_PATH . 'templates/meetings/genericError.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$goodArr = array( 'header', 'title', 'message', 'linkName', 'linkURL' );
		
		foreach( $goodArr as $val ):
			$contents = str_replace( "#{$val}#", $$val, $contents );
		endforeach;
		
		echo $contents;
	}
	
	public static function showPending( $pendingList, $roomContList, $roomList, $branchList, $amenityList, $pendingType = 'pending' )
	{
		$option['bookaroom_profitDeposit']				= get_option( 'bookaroom_profitDeposit' );
		$option['bookaroom_nonProfitDeposit']			= get_option( 'bookaroom_nonProfitDeposit' );
		$option['bookaroom_profitIncrementPrice']		= get_option( 'bookaroom_profitIncrementPrice' );
		$option['bookaroom_nonProfitIncrementPrice']	= get_option( 'bookaroom_nonProfitIncrementPrice' );
		$option['bookaroom_baseIncrement']				= get_option( 'bookaroom_baseIncrement' );
		
		$filename = BOOKAROOM_PATH . 'templates/meetings/pending.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		switch( $pendingType ):
			case '501C3':
				$title = "Pending 501(c)3";
				$link = "?page=bookaroom_meetings_pending501C3";
				break;
				
			case 'pendPayment':
				$title = "Pending Payment";
				$link = "?page=bookaroom_meetings_pendingPayment";
				break;
				
			case 'approved':
				$title = "Approved";
				$link = "?page=bookaroom_meetings_approvedRequests";
				break;
				
			case 'denied':
				$title = "Denied";
				$link = "?page=bookaroom_meetings_deniedRequests";
				break;
				
			case 'archived':
				$title = "Archived";
				$link = "?page=bookaroom_meetings_archivedRequests";
				break;

			case 'all':
				$title = "All";
				$link = "?page=bookaroom_meetings";
				break;
								
			default:
				$title = "Pending";
				$link = "?page=bookaroom_meetings";
				break;
				
		endswitch;
		
		$contents = str_replace( '#title#', $title, $contents );
		
		$some_line = repCon( 'some', $contents );
		$none_line = repCon( 'none', $contents, TRUE );
		
		if( empty( $pendingList['status'][$pendingType] ) ):
			$contents = str_replace( '#some_line#', $none_line, $contents );
			echo $contents;
			return true;
		endif;
		
		$contents = str_replace( '#some_line#', $some_line, $contents );
		
		$line_line = repCon( 'line', $contents );
		
		$count = 0;
		$final = array();
		
		$typeArr = array( 'pending' => 'New Pend.', 'pendPayment' => 'Pend. Payment', '501C3' => 'Pend 501(c)3', 'approved' => 'Approved', 'denied' => 'Denied', 'archived' => 'Archived' );
		
		foreach( $pendingList['status'][$pendingType] as $key => $mainKey):
			$val = $pendingList['id'][$mainKey];
			$temp = $line_line;
			$bg = ($count++%2) ? 'even_row_no' : 'odd_row_no';	
			$temp = str_replace( '#bg_class#', $bg, $temp );
			
			$temp = str_replace( '#roomName#', $roomContList['id'][$val['roomID']]['desc'], $temp );			
			$temp = str_replace( '#branchName#', $branchList[$roomContList['id'][$val['roomID']]['branchID']]['branchDesc'], $temp );
			$temp = str_replace( '#date#', date( 'M. jS, Y', strtotime( $val['startTime'] ) ), $temp );
			$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $val['startTime'] ) ), $temp );
			$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $val['endTime'] ) ), $temp );
			$temp = str_replace( '#eventName#', htmlspecialchars_decode( $val['eventName'] ), $temp );

			$notes = 0;			
			$temp = str_replace( '#noteInformation#', self::noteInformation( $val['contactName'], $notes ), $temp );
			$temp = str_replace( '#contactName#', $val['contactName'], $temp );
			$temp = str_replace( '#notesNum#', $notes, $temp );
			
			
			$temp = str_replace( '#itemCount#', $count, $temp );
			
			$temp = str_replace( '#email#', $val['contactEmail'], $temp );
			$temp = str_replace( '#createdDate#', date( 'M. jS, Y', strtotime( $val['created'] ) ), $temp );
			$temp = str_replace( '#createdTime#', date( 'g:i a', strtotime( $val['created'] ) ), $temp );
			$temp = str_replace( '#status#', $typeArr[$val['status']], $temp );
			$temp = str_replace( '#phone#', self::prettyPhone( $val['contactPhonePrimary'] ), $temp );
			$temp = str_replace( '#res_id#', $val['res_id'], $temp );
			
			# date 1 - two weeks from now
			$timeArr = getdate(  strtotime( $val['created'] ) );
			$date1 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+14, $timeArr['year'] );
			$date3 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+1, $timeArr['year'] );
			
			# two weeks before event		
			$timeArr = getdate( strtotime( $val['startTime'] ) );
			$date2 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']-14, $timeArr['year'] );
			
			
			# check dates
			$mainDate = min( $date1, $date2 );
			
			if( $mainDate < $date3 ):
				$mainDate = $date3;
			endif;
			
			$temp = str_replace( '#paymentDate#', date( 'm-d-Y', $mainDate ), $temp );
			
			# 
			$roomCount = count( $roomContList['id'][$val['roomID']]['rooms'] );
			if( empty( $val['nonProfit'] ) ):
				$nonProfit = 'No';
				# find how many increments
				$minutes = ( ( strtotime( $val['endTime'] ) - strtotime( $val['startTime'] ) ) / 60 ) / $option['bookaroom_baseIncrement'] ;

				$roomPrice = $minutes * $option['bookaroom_profitIncrementPrice'] * $roomCount;
				$deposit = 	intval( $option['bookaroom_profitDeposit'] );
			else:
				$nonProfit = 'Yes';
				# find how many increments
				$minutes = ( ( strtotime( $val['endTime'] ) - strtotime( $val['startTime'] ) ) / 60 ) / $option['bookaroom_baseIncrement'];
				
				$roomPrice = $minutes * $option['bookaroom_nonProfitIncrementPrice'] * $roomCount;
				$deposit = 	intval( $option['bookaroom_nonProfitDeposit'] );	
			endif;
			
			$temp = str_replace( '#nonprofit#', $nonProfit, $temp );
			$temp = str_replace( '#deposit#', $deposit, $temp );
			$temp = str_replace( '#roomPrice#', $roomPrice, $temp );
			
			
			
			$final[] = $temp;
		
		endforeach;
		
		$contents = str_replace( '#line_line#', implode( "\r\n", $final ), $contents );
		
		$bg = ($count++%2) ? 'even_row_no' : 'odd_row_no';	
		$contents = str_replace( '#bg_classSubmit#', $bg, $contents );
		
		echo $contents;
	}
	
	public static function showList( $type, $pendingList, $roomContList, $roomList, $branchList, $amenityList )
	{
		switch( $type ):
			case 'pending':
				$title = 'Pending';				
				break;
				
			default:
				die( 'Error, wrong action type for showList' );
				break;
		endswitch;
		$filename = BOOKAROOM_PATH . 'templates/templateMeetingsList.html';
		# $filename = BOOKAROOM_PATH . 'templates/templateMeetingsPending.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
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
		
		foreach( $pendingList as $key => $val):
			$temp = $line_line;
			$bg = ($count++%2) ? 'even_row_no' : 'odd_row_no';	
			$temp = str_replace( '#bg_class#', $bg, $temp );
			
			$temp = str_replace( '#roomName#', $roomContList['id'][$val['roomID']]['desc'], $temp );			
			$temp = str_replace( '#branchName#', $branchList[$roomContList['id'][$val['roomID']]['branchID']]['branchDesc'], $temp );
			$temp = str_replace( '#date#', date( 'M. jS, Y', strtotime( $val['startTime'] ) ), $temp );
			$temp = str_replace( '#startTime#', date( 'g:i a', strtotime( $val['startTime'] ) ), $temp );
			$temp = str_replace( '#endTime#', date( 'g:i a', strtotime( $val['endTime'] ) ), $temp );
			$temp = str_replace( '#eventName#', htmlspecialchars_decode( $val['eventName'] ), $temp );
			$temp = str_replace( '#contactName#', $val['contactName'], $temp );
			$temp = str_replace( '#email#', $val['contactEmail'], $temp );
			$temp = str_replace( '#phone#', self::prettyPhone( $val['contactPhonePrimary'] ), $temp );
			$nonProfit = ( empty( $val['nonProfit'] ) ) ? 'No' : 'Yes';
			$temp = str_replace( '#nonprofit#', $nonProfit, $temp );
			$temp = str_replace( '#res_id#', $val['res_id'], $temp );
			
			$final[] = $temp;
		
		endforeach;
		
		$contents = str_replace( '#line_line#', implode( "\r\n", $final ), $contents );
		
		$bg = ($count++%2) ? 'even_row_no' : 'odd_row_no';	
		$contents = str_replace( '#bg_classSubmit#', $bg, $contents );
		
		echo $contents;
	}
	
	protected static function showView( $res_id, $pendingList, $roomContList, $roomList, $branchList, $amenityList )
	{
		$filename = BOOKAROOM_PATH . 'templates/meetings/pending_view.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# status drop down
		$drop_line =repCon( 'drop', $contents );
		
		$dropArr = array( 'pending' => 'New Pending', 'pendPayment' => 'Pending Payment/501(c)3', 'approved' => 'Accepted with Payment/501(c)3', 'denied' => 'Denied', 'archived' => 'Archived' );
	  		
		$temp = NULL;
		$final = array();
		
		foreach( $dropArr as $key => $val ):
			$selected = ( $pendingList['id'][$res_id]['status'] == $key ) ? 'selected="selected" ' : NULL;
			$temp = $drop_line;
			$temp = str_replace( '#dropDesc#', $val, $temp );
			$temp = str_replace( '#dropVal#', $key, $temp );
			$temp = str_replace( '#dropSelected#', $selected, $temp );
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#drop_line#', implode( "\r\n", $final ), $contents );
		
		
		# amenityVal
		
		if( empty( $pendingList['id'][$res_id]['amenity'] ) ):
			$amenityArr = array();
		else:
			$amenityArr = unserialize( $pendingList['id'][$res_id]['amenity'] );
		endif;
			
		$temp = array();
				
		if( !empty( $amenityArr ) ):
			foreach( $amenityArr as $val ):
				$temp[] = $amenityList[$val];
			endforeach;
		endif;

		if( count( $temp ) > 0 ):
			$pendingList['id'][$res_id]['amenityVal'] = implode( ', ', $temp );			
		else:
			$pendingList['id'][$res_id]['amenityVal'] = 'None';
		endif;

		# branchName
		$pendingList['id'][$res_id]['branchName'] = $branchList[$roomContList['id'][$pendingList['id'][$res_id]['roomID']]['branchID']]['branchDesc'];

		# endTimeDisp
		$pendingList['id'][$res_id]['endTimeDisp'] = date( 'g:i a', strtotime( $pendingList['id'][$res_id]['endTime'] ) );
		
		# formDate
		$pendingList['id'][$res_id]['formDate'] = date( 'D., M. jS, Y', strtotime( $pendingList['id'][$res_id]['startTime'] ) );
		
		# nonProfitDisp
		$pendingList['id'][$res_id]['nonProfitDisp'] = (true == $pendingList['id'][$res_id]['nonProfit'] ) ? 'Yes' : 'No';
		
		# roomName
		$pendingList['id'][$res_id]['roomName'] = $roomContList['id'][$pendingList['id'][$res_id]['roomID']]['desc'];

		# startTimeDisp
		$pendingList['id'][$res_id]['startTimeDisp'] = date( 'g:i a', strtotime( $pendingList['id'][$res_id]['startTime'] ) );		
		
		# is social
		$pendingList['id'][$res_id]['socialDisp'] = ( true == $pendingList['id'][$res_id]['isSocial'] ) ? 'Yes' : 'No';

		$goodArr = array( 'amenityVal', 'branchName', 'contactAddress1', 'contactAddress2', 'contactCity', 'contactEmail', 'contactName', 'contactState', 'contactWebsite', 'contactZip', 'desc', 'endTimeDisp', 'eventName', 'formDate', 'nonProfitDisp', 'numAttend', 'roomName', 'startTimeDisp', 'notes', 'libcardNum', 'socialDisp' );
		
		foreach( $goodArr as $val ):
			$contents = str_replace( "#$val#", nl2br( htmlspecialchars_decode( $pendingList['id'][$res_id][$val] ) ), $contents );
		endforeach;
		
		$contents =str_replace( '#contactPhonePrimary#', book_a_room_meetings::prettyPhone( $pendingList['id'][$res_id]['contactPhonePrimary'] ), $contents );
		$contents =str_replace( '#contactPhoneSecondary#', book_a_room_meetings::prettyPhone( $pendingList['id'][$res_id]['contactPhoneSecondary'] ), $contents );
		
		$contents = str_replace( '#res_id#', $res_id, $contents );
		
		# cost

		$option['bookaroom_profitDeposit']				= get_option( 'bookaroom_profitDeposit' );
		$option['bookaroom_nonProfitDeposit']			= get_option( 'bookaroom_nonProfitDeposit' );
		$option['bookaroom_profitIncrementPrice']		= get_option( 'bookaroom_profitIncrementPrice' );
		$option['bookaroom_nonProfitIncrementPrice']	= get_option( 'bookaroom_nonProfitIncrementPrice' );
		$option['bookaroom_baseIncrement']				= get_option( 'bookaroom_baseIncrement' );
				

		$roomCount = count( $roomContList['id'][$pendingList['id'][$res_id]['roomID']]['rooms'] );
		if( empty( $pendingList['id'][$res_id]['nonProfit'] ) ):
			# find how many increments
			$minutes = ( ( strtotime( $pendingList['id'][$res_id]['endTime'] ) - strtotime( $pendingList['id'][$res_id]['startTime'] ) ) / 60 ) / $option['bookaroom_baseIncrement'] ;
			$roomPrice = $minutes * $option['bookaroom_profitIncrementPrice'] * $roomCount;
			$deposit = 	intval( $option['bookaroom_profitDeposit'] );
		else:
			# find how many increments
			$minutes = ( ( strtotime( $pendingList['id'][$res_id]['endTime'] ) - strtotime( $pendingList['id'][$res_id]['startTime'] ) ) / 60 ) / $option['bookaroom_baseIncrement'];
			$roomPrice = $minutes * $option['bookaroom_nonProfitIncrementPrice'] * $roomCount;
			$deposit = 	intval( $option['bookaroom_nonProfitDeposit'] );	
		endif;
		
		$contents = str_replace( '#deposit#', $deposit, $contents );
		$contents = str_replace( '#roomPrice#', $roomPrice, $contents );
		$contents = str_replace( '#totalPrice#', $roomPrice+$deposit, $contents );
		
		# date 1 - two weeks from now
			$timeArr = getdate( strtotime( $pendingList['id'][$res_id]['created'] ) );
			$date1 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+14, $timeArr['year'] );
			$date3 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']+1, $timeArr['year'] );
			
			# two weeks before event		
			$timeArr = getdate( strtotime( $pendingList['id'][$res_id]['startTime'] ) );
			$date2 = mktime( 0,0,0, $timeArr['mon'], $timeArr['mday']-14, $timeArr['year'] );
			
			
			# check dates
			$mainDate = min( $date1, $date2 );
			
			if( $mainDate < $date3 ):
				$mainDate = $date3;
			endif;
			
			$contents = str_replace( '#paymentDate#', date( 'm-d-Y', $mainDate ), $contents );
		
		
		# replace values
		if( get_option( 'bookaroom_addressType' ) !== 'usa' ):
			$address1_name 	= get_option( 'bookaroom_address1_name');
			$address2_name 	= get_option( 'bookaroom_address2_name');
			$city_name 		= get_option( 'bookaroom_city_name');
			$state_name 	= get_option( 'bookaroom_state_name');
			$zip_name 		= get_option( 'bookaroom_zip_name');
		else:
			$address1_name 	= 'Street Address';
			$address2_name 	= 'Address';
			$city_name 		= 'City';			
			$state_name 	= 'State';
			$zip_name 		= 'Zip Code';		
		endif;	
			
		$varNames = array( 'address1_name', 'address2_name', 'city_name', 'state_name', 'defaultState_name', 'zip_name' );
		
		foreach( $varNames as $val ):
			$replace_value = ( !empty( $$val ) ) ? $$val : NULL;
			$contents = str_replace( "#{$val}#", $replace_value, $contents );
		endforeach;

		echo $contents;
	}
	
	public static function makePaymentLink( $totalPrice, $res_id )
	{
		$paymentLink = get_option( 'bookaroom_paymentLink' );
		
		if( empty( $paymentLink ) ):
			return NULL;
		endif;
		
		if( empty( $totalPrice) || !is_numeric( $totalPrice ) ):
			return 'No payment required';
		endif;

		global $wpdb;
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		
		$isSaltRaw = $wpdb->get_row( "SELECT `me_salt` FROM `{$table_name}` WHERE `res_id` = '{$res_id}'", ARRAY_A );
		
		if( empty( $isSaltRaw['me_salt'] ) ):
			$isSalt = NULL;
		else:
			$isSalt = $isSaltRaw['me_salt'];
		endif;

		if( empty( $isSalt ) || strlen( $isSalt ) !== 32 ):
			$salt = uniqid(mt_rand(), true);
			$wpdb->update( $table_name, array( 'me_salt' => $salt ), array( 'res_id' => $res_id ) );
		else:
			$salt = $isSalt;
		endif;
	
		$hash = md5( $salt.$res_id );
		
		return "<a href=\"{$paymentLink}?hash={$hash}&res_id={$res_id}\">Click here to pay online with a credit card</a>.";
	}
	
	public static function noteInformation( $contactName, &$notesNumber )
	{
		$final = array();
		
		global $wpdb;

	
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		
		# get row count
		$sql = "SELECT re.res_id, re.me_notes, re.me_eventName, ti.ti_startTime 
					FROM {$wpdb->prefix}bookaroom_reservations re 
					LEFT JOIN {$wpdb->prefix}bookaroom_times ti on re.res_id = ti.ti_extID 
					WHERE  re.me_contactName LIKE  '%{$contactName}%'
					ORDER BY ti.ti_startTime DESC";

		$newOrderArr = $wpdb->get_results( $sql, ARRAY_A );
		
		foreach( $newOrderArr as $val ):
			if( empty( $val['me_notes'] ) ) continue;
			$niceDate = date( 'l, F jS, Y g:i a', strtotime( $val['ti_startTime'] ) );
			$notes = nl2br( htmlspecialchars_decode( $val['me_notes'] ) );
			$final[] = "<p><strong>{$val['me_eventName']} - <a href=\"?page=bookaroom_meetings&action=edit&res_id={$val['res_id']}\"  target=\"_blank\">Edit</a><br><em>{$niceDate}</em></strong><br>{$notes}</p>";
		endforeach;
		
		$notesNumber = count( $final );
		
		if( $notesNumber == 0 ):
			return "No notes found for {$contactName}.";	
		else:
			return implode( "<hr>\r\n", $final );
		endif;
		
		return $final;
	}
	
	public function prettyPhone( $val )
	{
		$phone = preg_replace("/[^0-9,.]/", "", $val);
		return '(' . substr( $phone, 0, 3) . ') ' . substr( $phone, 3, 3 ) . '-' . substr( $phone, 6 );	
	}

}
?>