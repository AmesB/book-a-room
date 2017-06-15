<?PHP
class bookaroom_events
{
	public static function bookaroom_adminEvents()
	{
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-amenities.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-rooms.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
		require_once( BOOKAROOM_PATH . 'bookaroom-meetings-closings.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-cityManagement.php' );
		
		# vaiables from includes
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		$cityList = bookaroom_settings_cityManagement::getCityList( );
		
		$changeRoom = false;
		# first, is there an action? 
		$externals = self::getExternals();
		
		if( empty( $_SESSION['bookaroom_meetings_externalVals'] ) and in_array( $externals['action'], array( 'checkBad', 'checkConflicts' ) ) ): 
			$externals['action'] = NULL;
		endif;
		
		$res_id = $externals['res_id'];
		$instance = false;
		
		switch( $externals['action'] ):	
			case 'copy':
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, array(), true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				$_SESSION['bookaroom_meetings_hash'] = NULL;
				$_SESSION['bookaroom_meetings_externalVals'] = NULL;
				$externals['eventStart'] = false;
				$externals['endTime'] = false;
				$externals['startTime'] = false;
				$externals['regDate'] = false;
				
				
				self::showEventForm_times( $externals );

				break;
								
			case 'edit_attendance':
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, array(), true, 'That ID is invalid. Please try again.' );
					break;
				endif;

				if( false != ( $errorMSG = self::checkAttendance( $externals ) ) ):
					self::showAttendance( $eventInfo, $externals, $externals['attCount'], $externals['attNotes'], $errorMSG );
				endif;
		
				self::editAttendance( $externals );
				self::showAttendanceSuccess();
				
				break;
				
			case 'manage_attendance':
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				self::showAttendance( $eventInfo, $externals, $eventInfo['ti_attendance'], $eventInfo['ti_attNotes'] );
				break;
				
			case 'edit_registration_final':
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				# check hash
				if( empty( $externals['hash'] ) or empty( $externals['hashTime'] ) or empty( $externals['regID'] ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That was a problem deleting that registration. Please try again.' );
					break;
				endif;
				
				$newHash = md5( $externals['hashTime'].$externals['regID'].$externals['eventID'] );
				if( $newHash !== $externals['hash'] ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That was a problem deleting that registration. Please try again.' );
				endif;
				
				if( ( $errorMSG = self::checkReg( $externals ) ) !== false ):
					self::showEditRegistrations( $eventInfo, $externals, $branchList, $roomContList, $errorMSG );
					#self::viewEvent( $eventInfo, $externals, $errorMSG );
					break;
				endif;
				
				self::editRegistration( $externals );
				self::manageRegistrations( $eventInfo, $branchList, $roomContList );
				/*
				$delResult = self::deleteReg( $externals, $eventInfo );
						
				if( $delResult['status'] !== true ):
					$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );
					bookaroom_events_manage::manageEvents( $externals, $results, true, $delResult['errorMSG'] );
					break;
				endif;
				
				if( !empty( $delResult['alertID'] ) ):
					# phone or email
					self::alertReg( $delResult['alertID'], $eventInfo );
				else:
					self::delReg_showSuccess( $delResult['alertID'] );
				endif;
				*/

				break;
				
			case 'edit_registration':
				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				
				
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				self::showEditRegistrations( $eventInfo, $externals, $branchList, $roomContList );
				break;

			case 'delete_registration_final':
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				# check hash
				if( empty( $externals['hash'] ) or empty( $externals['hashTime'] ) or empty( $externals['regID'] ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That was a problem deleting that registration. Please try again.' );
					break;
				endif;
				
				$newHash = md5( $externals['hashTime'].$externals['regID'].$externals['eventID'] );
				if( $newHash !== $externals['hash'] ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That was a problem deleting that registration. Please try again.' );
				endif;
				
				$delResult = self::deleteReg( $externals, $eventInfo );
						
				if( $delResult['status'] !== true ):
					$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );
					bookaroom_events_manage::manageEvents( $externals, $results, true, $delResult['errorMSG'] );
					break;
				endif;
				
				if( !empty( $delResult['alertID'] ) ):
					# phone or email
					self::alertReg( $delResult['alertID'], $eventInfo );
				else:
					self::delReg_showSuccess( $delResult['alertID'] );
				endif;
				

				break;
				
			case 'delete_registration':
				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				
				
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				self::showDeleteRegistrations( $eventInfo, $externals['regID'], $branchList, $roomContList );
				break;
				
			case 'manage_registrations':
				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				
								
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;

				self::manageRegistrations( $eventInfo, $branchList, $roomContList );
				break;
				
			case 'checkDeleteMulti':
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;
				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				
				
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				
				$instances = $instanceKeys = self::getInstances( $eventInfo['res_id'] );
				
				array_walk($instanceKeys, function(&$value, $index){
				 	$value = $value['ti_id'];
				});
				
				if( empty( $externals['instance'] ) or !is_array( $externals['instance'] ) ):
					$externals['instance'] = array();
				endif;
				
				$finalDelete = array_intersect( $instanceKeys, $externals['instance'] );
				if( count( $finalDelete ) == 0):
					self::showDeleteMulti( $externals, $instances, 'You haven\'t selected any instances to delete.'  );
				endif;
				
				self::deleteMulti( $eventInfo, $externals, $finalDelete, $instanceKeys );
				
				unset( $_SESSION['bookaroom_meetings_externalVals'] );
				unset( $_SESSION['bookaroom_meetings_hash'] );
				unset( $_POST );
				unset( $_GET );
				unset( $externals );
				self::deleteSuccess();
				break;
				
			case 'delete_multi':
				# get search settings
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;
				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				
				
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				
				$instances = self::getInstances( $eventInfo['res_id'] );
				
				self::showDeleteMulti( $externals, $instances );

				break;
				
				case 'delete_instance':
					$instance = true;
				case 'delete':
				# get search settings
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;
				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				

				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				#if( $eventInfo['tiCount'] > 1 ):
				#	bookaroom_events_manage::manageEvents( $externals, $results, true, 'That event has multiple dates and cannot be deleted as a single date event.' );
				#	break;
				#endif;
				
				$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				
				self::showDeleteSingle( $externals, $instance );

				break;
								
			case 'deleteCheckInstance':
				$instance = true;
			case 'deleteCheck':
				# check hash
				if( self::checkHash( $externals ) == false ):
					$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'An error has occured. Please try again.' );
					break;
				endif;
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, array(), true, 'That ID is invalid. Please try again.' );
					break;
				endif;
								
				self::deleteSingle( $eventInfo, $instance );
				
				self::deleteSuccess();
				
				break;
				
				case 'delete_instance':
					$instance = true;
				case 'delete':
				# get search settings
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;
				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				

				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				#if( $eventInfo['tiCount'] > 1 ):
			#		bookaroom_events_manage::manageEvents( $externals, $results, true, 'That event has multiple dates and cannot be deleted as a single date event.' );
		#			break;
	#			endif;
				
				$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				
				self::showDeleteSingle( $externals, $instance );

				break;
								
			case 'checkEvent':
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				if( true == ( $result = self::checkFormEvent( $externals, $roomContList, $branchList, $amenityList ) ) ):
					self::showEventForm_event( $externals, $result, $eventInfo );
					break;	
				endif;		
								
				$_SESSION['bookaroom_meetings_hash'] = md5( serialize( $externals ) );
				$_SESSION['bookaroom_meetings_externalVals'] = $externals;

				self::editEventReccuring( $externals );
				unset( $_SESSION['bookaroom_meetings_externalVals'] );
				unset( $_SESSION['bookaroom_meetings_hash'] );
				unset( $_POST );
				unset( $_GET );
				unset( $externals );
				self::editEventSuccess( );
				break;

			case 'edit_event_changeRoom':
				$changeRoom = true;
			case 'edit_event':
				# get search settings
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;

				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );
				
				
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;

				if( $changeRoom == false ):
					#$externals['startTime'] = $eventInfo['ti_startTime'];
					#$externals['endTime'] = $eventInfo['ti_endTime'];
					#$externals['roomID'] = $eventInfo['ti_roomID'];
					
					$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				endif;
				# check if single
				if( $eventInfo['tiCount'] == 1 ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That event has a single date and cannot be edited as a multiple date date event.' );
					break;
				endif;
				
				self::showEventForm_event( $externals, NULL, $eventInfo );
				
				break;

			case 'checkInstanceEdit':
				# first check for errors	
					
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, NULL, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				if( $eventInfo['tiCount'] ==  1 ):
					bookaroom_events_manage::manageEvents( $externals, NULL, true, 'That event has a single date and cannot be edited as a multiple date event.' );
					break;
				endif;
				
				if( true == ( $result = self::checkFormInstance( $externals, $roomContList, $branchList, $amenityList ) ) ):
					self::showEventForm_instance( $externals, $result, $eventInfo );
					break;	
				endif;				
				

				$externalsTemp = self::fixSavedEventInfo( $eventInfo, $externals );
				$externalsTemp['recurrence'] = 'single';

				$externalsTemp['startTime'] = $externals['startTime'];
				$externalsTemp['endTime'] = $externals['endTime'];
				$externalsTemp['roomID'] = $externals['roomID'];
				$externalsTemp['eventStart'] = $externals['eventStart'];
				$externalsTemp['eventID'] = $externals['eventID'];
				$externalsTemp['allDay'] = $externals['allDay'];
				
				$_SESSION['bookaroom_meetings_hash'] = md5( serialize( $externalsTemp ) );
				$_SESSION['bookaroom_meetings_externalVals'] = $externalsTemp;

				if( self::checkConflicts( $externalsTemp, $externalsTemp['roomID'], $dateList, array(), array(), $externalsTemp['eventID'] ) ):
					self::showConflicts( $_SESSION['bookaroom_meetings_externalVals'], $dateList, $externals['delete'] );
					self::showEventForm_instance( $externals, NULL, $eventInfo );
					
				else:
					self::editEventInstance( $externals );
					unset( $_SESSION['bookaroom_meetings_externalVals'] );
					unset( $_SESSION['bookaroom_meetings_hash'] );
					unset( $_POST );
					unset( $_GET );
					unset( $externals );
					self::editEventSuccess( );
				endif;

				break;

			case 'edit_instance_changeRoom':
				$changeRoom = true;
			case 'edit_instance':
				# get search settings
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;
				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );
				
				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;

				if( $changeRoom == false ):
					$externals['startTime'] = $eventInfo['ti_startTime'];
					$externals['endTime'] = $eventInfo['ti_endTime'];
					$externals['roomID'] = $eventInfo['ti_roomID'];
					$externals['eventStart'] = $eventInfo['ti_startTime'];
					$externals['extraInfo'] = $eventInfo['ti_extraInfo'];

				endif;
				# check if single

				if( $eventInfo['tiCount'] == 1 ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That event has a single date and cannot be edited as a multiple date date event.' );
					break;
				endif;
				
				if( $changeRoom == false ):
					#$externals[''] 
				endif;
				
				self::showEventForm_instance( $externals, NULL, $eventInfo );
				
				break;

			
			case 'checkSingleEdit':
				# first check for errors	
					
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				if( $eventInfo['tiCount'] > 1 ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That event has multiple dates and cannot be edited as a single date event.' );
					break;
				endif;
				
				
				if( true == ( $result = self::checkForm( $externals, $roomContList, $branchList, $amenityList ) ) ):
					self::showEventForm_times( $externals, $result, 'Edit', 'checkSingleEdit', 'edit_single_changeRoom' );
					break;	
				endif;
				
				$_SESSION['bookaroom_meetings_hash'] = md5( serialize( $externals ) );
				$_SESSION['bookaroom_meetings_externalVals'] = $externals;

				if( self::checkConflicts( $externals, $_SESSION['bookaroom_meetings_externalVals']['roomID'], $dateList, array(), array(), $externals['eventID'] ) ):
					self::showEventForm_times( $externals, array('errorMSG' => 'There is a conflict at this time. Please try another time or room.'), 'Edit', 'checkSingleEdit', 'edit_single_changeRoom' );
					#self::showConflicts( $_SESSION['bookaroom_meetings_externalVals'], $dateList );
				else:
					self::editEvent( $dateList );
					unset( $_SESSION['bookaroom_meetings_externalVals'] );
					unset( $_SESSION['bookaroom_meetings_hash'] );
					unset( $_POST );
					unset( $_GET );
					unset( $externals );
					self::editEventSuccess( );
				endif;

				break;
			
			case 'edit_single_changeRoom':
				$changeRoom = true;				
			case 'edit_single':
				# get search settings
				if( !empty( $_SESSION['bookaroom_temp_search_settings'] ) ):
					foreach( array( 'startDate', 'endDate', 'published', 'searchTerms', 'branchID' ) as $val ):
						if( !empty( $_SESSION['bookaroom_temp_search_settings'][$val] ) ):
							$externals[$val] = $_SESSION['bookaroom_temp_search_settings'][$val];
						else:
							$externals[$val] = NULL;
						endif;
						
					endforeach;
				endif;

				$results = bookaroom_events_manage::getEventList( $externals, $amenityList, $branchList, $roomContList, $roomList );				

				#check id	
				if( false == ( $eventInfo = self::checkID( $externals['eventID'] ) ) ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That ID is invalid. Please try again.' );
					break;
				endif;
				
				# check if single
				
				if( $eventInfo['tiCount'] > 1 ):
					bookaroom_events_manage::manageEvents( $externals, $results, true, 'That event has multiple dates and cannot be edited as a single date event.' );
					break;
				endif;

				if( $changeRoom == false ):
					$externals = self::fixSavedEventInfo( $eventInfo, $externals );
				endif;
				
				self::showEventForm_times( $externals, NULL, 'Edit', 'checkSingleEdit', 'edit_single_changeRoom');
				
				break;
				
			case 'checkBad':
				if( self::checkConflicts( $externals, $_SESSION['bookaroom_meetings_externalVals']['roomID'], $dateList ) ):
					self::showConflicts( $_SESSION['bookaroom_meetings_externalVals'], $dateList );
				else:
					self::addEvent( $dateList );
					unset( $_SESSION['bookaroom_meetings_externalVals'] );
					unset( $_SESSION['bookaroom_meetings_hash'] );
					unset( $_POST );
					unset( $_GET );
					unset( $externals );
					self::addEventSuccess( );
				endif;
				
				break;
			
			case 'checkConflicts':
				if( self::checkConflicts( $_SESSION['bookaroom_meetings_externalVals'], $_SESSION['bookaroom_meetings_externalVals']['roomID'], $dateList, $externals['delete'], $externals['newVal'] ) ):
					self::showConflicts( $_SESSION['bookaroom_meetings_externalVals'], $dateList, $externals['delete'] );
					self::showEventForm_times( $externals  );
				else:
					self::addEvent( $dateList, $externals['delete'] );
					unset( $_SESSION['bookaroom_meetings_externalVals'] );
					unset( $_SESSION['bookaroom_meetings_hash'] );
					unset( $_POST );
					unset( $_GET );
					unset( $externals );
					self::addEventSuccess( );					
				endif;
				break;
				
			case 'checkLocation':
				if( empty( $externals['roomID'] ) or !array_key_exists( $externals['roomID'], $roomContList['id'] ) ):
					self::showEventForm( $externals, $roomContList, $branchList, 'Please choose a valid room.' );
					break;
				endif;
				$externals['recurrence'] = 'none';
				$externals['registration'] = 'false';
				$externals['waitingList'] = get_option( 'bookaroom_waitingListDefault' );
				self::showEventForm_times( $externals, $roomContList, $branchList );
				break;

			case 'checkInformation':
				# first check for errors
				if( true == ( $result = self::checkForm( $externals, $roomContList, $branchList, $amenityList ) ) ):
					self::showEventForm_times( $externals, $result );
					break;	
				endif;

				$_SESSION['bookaroom_meetings_hash'] = md5( serialize( $externals ) );
				$_SESSION['bookaroom_meetings_externalVals'] = $externals;

				if( self::checkConflicts( $externals, $_SESSION['bookaroom_meetings_externalVals']['roomID'], $dateList ) ):
					self::showConflicts( $_SESSION['bookaroom_meetings_externalVals'], $dateList );
					self::showEventForm_times( $externals );
				else:
					self::addEvent( $dateList );
					unset( $_SESSION['bookaroom_meetings_externalVals'] );
					unset( $_SESSION['bookaroom_meetings_hash'] );
					unset( $_POST );
					unset( $_GET );
					unset( $externals );
					self::addEventSuccess( );
				endif;
				
				break;
			
			case 'makeReservation':
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

				if( ( $errorMSG = bookaroom_public::showForm_checkHoursError( $externals['startTime'], $externals['endTime'], $externals['roomID'], $roomContList, $branchList, $externals['res_id'], TRUE ) ) == TRUE ):
					
					self::showEventForm_times( $externals );
					break;
				endif;

				$requestInfo['startTime'] = $externals['startTime'];
				$requestInfo['endTime'] = $externals['endTime'];
				$requestInfo['amenity'] = unserialize( $externals['amenity'] );

				bookaroom_public::showForm_publicRequest( $externals['roomID'], $branchList, $roomContList, $roomList, $amenityList, $cityList, $requestInfo, array(), TRUE );
				break;
					
			default:
				$externals['waitingList'] = get_option( 'bookaroom_waitingListDefault' );
				$_SESSION['bookaroom_meetings_hash'] = NULL;
				$_SESSION['bookaroom_meetings_externalVals'] = NULL;
				self::showEventForm_times( $externals );
				break;
				
			case 'changeRoom':
				self::showEventForm_times( $externals );
				#self::showEventForm( $externals, $roomContList, $branchList );
				break;
		
		
		endswitch;
	}

	protected static function editEventInstance( $externals )
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_times";


		if( !empty( $externals['allDay'] ) and $externals['allDay'] == 'true' ):
			$startTime = date('Y-m-d H:i:s', strtotime( $externals['eventStart'] . ' 00:00:00' ) );
			$endTime = date('Y-m-d H:i:s', strtotime( $externals['eventStart'] . ' 23:59:59' ) );
		
		else:
		
			$startTime = date('Y-m-d H:i:s', strtotime( $externals['eventStart'] . ' ' . $externals['startTime'] ) );
			$endTime = date('Y-m-d H:i:s', strtotime( $externals['eventStart'] . ' ' . $externals['endTime'] ) );
		endif;

		if( substr( $externals['roomID'], 0, 6 ) == 'noloc-' ):
			$roomInfo = explode( '-', $externals['roomID'] );
			
			$branchID = $roomInfo[1];
			$roomSQL = " `ti_roomID` = '0', `ti_noLocation_branch` = '{$branchID}'";
		else:
			$roomSQL = " `ti_roomID` = '{$externals['roomID']}'";
		endif;
		
		$sql = "UPDATE `$table_name` SET `ti_extraInfo` = '{$externals['extraInfo']}', `ti_startTime` = '{$startTime}', `ti_endTime` = '{$endTime}',{$roomSQL}
		WHERE `ti_id` = '{$externals['eventID']}'";

		$wpdb->query( $sql );
				
	}
	
	protected static function delReg_showPhone( $regInfo )
	{
		$filename = BOOKAROOM_PATH . 'templates/events/delReg_showPhone.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#regName#', $regInfo['reg_fullName'], $contents );
		$contents = str_replace( '#regPhone#', $regInfo['reg_phone'], $contents );
		$contents = str_replace( '#regNotes#', $regInfo['reg_notes'], $contents );
		$contents = str_replace( '#regDate#', date( 'l, F jS, Y [g:i a]', strtotime( $regInfo['reg_dateReg'] ) ), $contents );
		
		echo $contents;
	}
	
	protected static function delReg_showSuccess( $regInfo )
	{
		$filename = BOOKAROOM_PATH . 'templates/events/delReg_showSuccess.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	protected static function delReg_showMail( $regInfo )
	{
		$filename = BOOKAROOM_PATH . 'templates/events/delReg_showEmail.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#regName#', $regInfo['reg_fullName'], $contents );
		$contents = str_replace( '#regEmail#', $regInfo['reg_email'], $contents );
		$contents = str_replace( '#regNotes#', $regInfo['reg_notes'], $contents );
		$contents = str_replace( '#regDate#', date( 'l, F jS, Y [g:i a]', strtotime( $regInfo['reg_dateReg'] ) ), $contents );
		
		echo $contents;
	}
	
	protected static function alertReg( $regID, $eventInfo )
	{
		
		$registrations = self::getRegistrations( $eventInfo['ti_id'] );
		
		if( empty( $registrations[$regID]['reg_email'] ) ):
			self::delReg_showPhone( $registrations[$regID] );
			return false;
		endif;
		
		$fromName	= get_option( 'bookaroom_alertEmailFromName' );	
		$fromEmail	= get_option( 'bookaroom_alertEmailFromEmail' );
		
		$replyToOnly	= ( true == get_option( 'bookaroom_emailReplyToOnly' ) ) ? "From: {$fromName}\r\nReply-To: {$fromName} <$fromEmail>\r\n" : "From: {$fromName} <$fromEmail>\r\n";
		
		
		$subject	= get_option( 'bookaroom_regChange_subject' );
		$body		= nl2br( get_option( 'bookaroom_regChange_body' ) );
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		
		$body = str_replace( '{eventName}', $eventInfo['ev_title'], $body );
		$body = str_replace( '{date}', date( 'l, F jS, Y', strtotime( $eventInfo['ti_startTime'] ) ), $body );
		$body = str_replace( '{startTime}', date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) ), $body );
		
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$branch = $branchList[$eventInfo['ti_noLocation_branch']]['branchDesc'];
		else:
			$branch = $branchList[$roomContList['id'][$eventInfo['ti_roomID']]['branchID']]['branchDesc'];
		endif;
		
		$body = str_replace( '{branchName}', $branch, $body );
		
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
					$replyToOnly . 
					'X-Mailer: PHP/' . phpversion();
		
				
		mail( $registrations[$regID]['reg_email'], $subject, $body, $headers );
		
		self::delReg_showMail( $registrations[$regID] );
		
		return true;
	}
	
	protected static function editEventReccuring( )
	{
		global $wpdb;
		
		if( $_SESSION['bookaroom_meetings_externalVals']['doNotPublish'] == 'true' ) :
			$doNotPublish = '1';
		else:
			$doNotPublish = '0';
		endif;
		
		# get event main info
		$eventInfo = self::checkID( $_SESSION['bookaroom_meetings_externalVals']['eventID'] );
		
		# insert event
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		
		if( !empty( $_SESSION['bookaroom_meetings_externalVals']['publicPhone'] ) ):
			$cleanPhone = preg_replace( "/[^0-9]/", '', $_SESSION['bookaroom_meetings_externalVals']['publicPhone'] );
			if ( strlen( $cleanPhone ) == 11 ):
				$cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
			endif;
		else:
			$cleanPhone = NULL;
		endif;
		
		# amenity
		$amenity = ( empty( $_SESSION['bookaroom_meetings_externalVals']['amenity'] ) ) ? NULL : serialize( $_SESSION['bookaroom_meetings_externalVals']['amenity'] );
		
		$regDate = date('Y-m-d H:i:s', strtotime( $_SESSION['bookaroom_meetings_externalVals']['regDate'] ) );
		
		$sql = "UPDATE `$table_name` SET 
`ev_desc` = '".$_SESSION['bookaroom_meetings_externalVals']['eventDesc']."', 
`ev_maxReg` = '".$_SESSION['bookaroom_meetings_externalVals']['maxReg']."', 
`ev_amenity` = '{$amenity}', 
`ev_waitingList` = '".$_SESSION['bookaroom_meetings_externalVals']['waitingList']."', 
`ev_presenter` = '".$_SESSION['bookaroom_meetings_externalVals']['presenter']."', 
`ev_privateNotes` = '".$_SESSION['bookaroom_meetings_externalVals']['privateNotes']."', 
`ev_publicEmail` = '".$_SESSION['bookaroom_meetings_externalVals']['publicEmail']."', 
`ev_publicName` = '".$_SESSION['bookaroom_meetings_externalVals']['publicName']."', 
`ev_publicPhone` = '{$cleanPhone}', 
`ev_noPublish` = '{$doNotPublish}', 
`ev_regStartDate` = '{$regDate}', 
`ev_regType` = '".$_SESSION['bookaroom_meetings_externalVals']['registration']."',  
`ev_submitter` = '".$_SESSION['bookaroom_meetings_externalVals']['yourName']."', 
`ev_title` = '".$_SESSION['bookaroom_meetings_externalVals']['eventTitle']."', 
`ev_website` = '".$_SESSION['bookaroom_meetings_externalVals']['website']."', 
`ev_webText` = '".$_SESSION['bookaroom_meetings_externalVals']['websiteText']."' 
WHERE `res_id` = '{$eventInfo['res_id']}'";

		$wpdb->query( $sql );

		
		# ages
		
		$agesArr = array();
		foreach( $_SESSION['bookaroom_meetings_externalVals']['ageGroup'] as $key => $age ):
			$agesArr[] = "('{$eventInfo['res_id']}', '{$age}')";
		endforeach;

		$table_name = $wpdb->prefix . "bookaroom_eventAges";
		
		$finalAges = implode( ', ', $agesArr );
		$sql = "DELETE FROM `{$table_name}` WHERE `ea_eventID` = '{$eventInfo['res_id']}'";
		
		$wpdb->query( $sql );
		
		$sql = "INSERT INTO `{$table_name}` (`ea_eventID`, `ea_ageID`) VALUES {$finalAges}";
		
		$wpdb->query( $sql );

		# categories

		$table_name = $wpdb->prefix . "bookaroom_eventCats";
		
		$catsArr = array();
		foreach( $_SESSION['bookaroom_meetings_externalVals']['category'] as $key => $cat ):
			$catsArr[] = "('{$eventInfo['res_id']}', '{$cat}')";
		endforeach;
		
		$table_name = $wpdb->prefix . "bookaroom_eventCats";
		
		$finalCats = implode( ', ', $catsArr );
		
		$sql = "DELETE FROM `{$table_name}` WHERE `ec_eventID` = '{$eventInfo['res_id']}'";
		$wpdb->query( $sql );
		
		$sql = "INSERT INTO `$table_name` (`ec_eventID`, `ec_catID`) VALUES {$finalCats}";
		$wpdb->query( $sql );
	
		
	}
	
	protected static function editEvent( $dateList, $delete = array() )
	{
		global $wpdb;
		
		if( $_SESSION['bookaroom_meetings_externalVals']['doNotPublish'] == 'true' ) :
			$doNotPublish = '1';
		else:
			$doNotPublish = '0';
		endif;
		
		# get event main info
		$eventInfo = self::checkID( $_SESSION['bookaroom_meetings_externalVals']['eventID'] );
		
		# insert event
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		
		if( !empty( $_SESSION['bookaroom_meetings_externalVals']['publicPhone'] ) ):
			$cleanPhone = preg_replace( "/[^0-9]/", '', $_SESSION['bookaroom_meetings_externalVals']['publicPhone'] );
			if ( strlen( $cleanPhone ) == 11 ):
				$cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
			endif;
		else:
			$cleanPhone = NULL;
		endif;
		
		# amenity
		$amenity = ( empty( $_SESSION['bookaroom_meetings_externalVals']['amenity'] ) ) ? NULL : serialize( $_SESSION['bookaroom_meetings_externalVals']['amenity'] );
		
		$regDate = date('Y-m-d H:i:s', strtotime( $_SESSION['bookaroom_meetings_externalVals']['regDate'] ) );
		
		$sql = "UPDATE `$table_name` SET 
`ev_desc` = '".$_SESSION['bookaroom_meetings_externalVals']['eventDesc']."', 
`ev_maxReg` = '".$_SESSION['bookaroom_meetings_externalVals']['maxReg']."', 
`ev_amenity` = '{$amenity}', 
`ev_waitingList` = '".$_SESSION['bookaroom_meetings_externalVals']['waitingList']."', 
`ev_presenter` = '".$_SESSION['bookaroom_meetings_externalVals']['presenter']."', 
`ev_privateNotes` = '".$_SESSION['bookaroom_meetings_externalVals']['privateNotes']."', 
`ev_publicEmail` = '".$_SESSION['bookaroom_meetings_externalVals']['publicEmail']."', 
`ev_publicName` = '".$_SESSION['bookaroom_meetings_externalVals']['publicName']."', 
`ev_publicPhone` = '{$cleanPhone}', 
`ev_noPublish` = '{$doNotPublish}', 
`ev_regStartDate` = '{$regDate}', 
`ev_regType` = '".$_SESSION['bookaroom_meetings_externalVals']['registration']."',  
`ev_submitter` = '".$_SESSION['bookaroom_meetings_externalVals']['yourName']."', 
`ev_title` = '".$_SESSION['bookaroom_meetings_externalVals']['eventTitle']."', 
`ev_website` = '".$_SESSION['bookaroom_meetings_externalVals']['website']."', 
`ev_webText` = '".$_SESSION['bookaroom_meetings_externalVals']['websiteText']."' 
WHERE `res_id` = '{$eventInfo['res_id']}'";

		$wpdb->query( $sql );

		
		$table_name = $wpdb->prefix . "bookaroom_times";
		
		foreach( $dateList as $val ):
			if( array_search( date( 'm/d/y', $val['start'] ), $delete ) ):
				continue;
			endif;
			$startTime = date('Y-m-d H:i:s', $val['start'] );
			$endTime = date('Y-m-d H:i:s', $val['end'] );
			$roomID = $_SESSION['bookaroom_meetings_externalVals']['roomID'];
			
			if( substr( $roomID, 0, 6 ) == 'noloc-' ):
				$roomInfo = explode( '-', $roomID );
				
				$branchID = $roomInfo[1];
				$roomID = NULL;
			else:
				$goodRoomID = $roomID;
				$branchID = NULL;
			endif;
			$sql = "UPDATE `{$table_name}` SET 
`ti_type` = 'event', 
`ti_extID` = '{$eventInfo['res_id']}', 
`ti_startTime` = '{$startTime}', 
`ti_endTime` = '{$endTime}', 
`ti_roomID` = '{$roomID}', 
`ti_noLocation_branch` = '{$branchID}' 
WHERE `ti_id` = '{$_SESSION['bookaroom_meetings_externalVals']['eventID']}'";


			$wpdb->query( $sql );
	
		endforeach;	
		# ages
		
		$agesArr = array();
		foreach( $_SESSION['bookaroom_meetings_externalVals']['ageGroup'] as $key => $age ):
			$agesArr[] = "('{$eventInfo['res_id']}', '{$age}')";
		endforeach;

		$table_name = $wpdb->prefix . "bookaroom_eventAges";
		
		$finalAges = implode( ', ', $agesArr );
		$sql = "DELETE FROM `{$table_name}` WHERE `ea_eventID` = '{$eventInfo['res_id']}'";
		
		$wpdb->query( $sql );
		
		$sql = "INSERT INTO `{$table_name}` (`ea_eventID`, `ea_ageID`) VALUES {$finalAges}";
		
		$wpdb->query( $sql );

		# categories

		$table_name = $wpdb->prefix . "bookaroom_eventCats";
		
		$catsArr = array();
		foreach( $_SESSION['bookaroom_meetings_externalVals']['category'] as $key => $cat ):
			$catsArr[] = "('{$eventInfo['res_id']}', '{$cat}')";
		endforeach;
		
		$table_name = $wpdb->prefix . "bookaroom_eventCats";
		
		$finalCats = implode( ', ', $catsArr );
		
		$sql = "DELETE FROM `{$table_name}` WHERE `ec_eventID` = '{$eventInfo['res_id']}'";
		$wpdb->query( $sql );
		
		$sql = "INSERT INTO `$table_name` (`ec_eventID`, `ec_catID`) VALUES {$finalCats}";
		$wpdb->query( $sql );
	
		
	}
		
	protected static function addEvent( $dateList, $delete = array() )
	{
		global $wpdb;
		
		if( $_SESSION['bookaroom_meetings_externalVals']['doNotPublish'] == 'true' ) :
			$doNotPublish = '1';
		else:
			$doNotPublish = '0';
		endif;
		# insert event
		$table_name = $wpdb->prefix . "bookaroom_reservations";
		
		if( !empty( $_SESSION['bookaroom_meetings_externalVals']['publicPhone'] ) ):
			$cleanPhone = preg_replace( "/[^0-9]/", '', $_SESSION['bookaroom_meetings_externalVals']['publicPhone'] );
			if ( strlen( $cleanPhone ) == 11 ):
				$cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
			endif;
		else:
			$cleanPhone = NULL;
		endif;
		
		# amenity
		$amenity = ( empty( $_SESSION['bookaroom_meetings_externalVals']['amenity'] ) ) ? NULL : serialize( $_SESSION['bookaroom_meetings_externalVals']['amenity'] );
		
		if( !empty( $_SESSION['bookaroom_meetings_externalVals']['regDate'] ) ):
			$regDate = date('Y-m-d H:i:s', strtotime( $_SESSION['bookaroom_meetings_externalVals']['regDate'] ) );
		else:
			$regDate = NULL;
		endif;
		
		$sql = "INSERT INTO `$table_name` ( `ev_desc`, `ev_maxReg`, `ev_amenity`, `ev_waitingList`, `ev_presenter`, `ev_privateNotes`, `ev_publicEmail`, `ev_publicName`, `ev_publicPhone`, `ev_noPublish`, `ev_regStartDate`, `ev_regType`, `ev_submitter`, `ev_title`, `ev_website`, `ev_webText` ) 
VALUES(
'".$_SESSION['bookaroom_meetings_externalVals']['eventDesc']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['maxReg']."', 
'{$amenity}', 
'".$_SESSION['bookaroom_meetings_externalVals']['waitingList']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['presenter']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['privateNotes']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['publicEmail']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['publicName']."', 
'{$cleanPhone}', 
'{$doNotPublish}', 
'{$regDate}', 
'".$_SESSION['bookaroom_meetings_externalVals']['registration']."',  
'".$_SESSION['bookaroom_meetings_externalVals']['yourName']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['eventTitle']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['website']."', 
'".$_SESSION['bookaroom_meetings_externalVals']['websiteText']."' )";

		$wpdb->query( $sql );

		$insertID = $wpdb->insert_id;
		if( empty( $insertID ) ):
			die( 'Mysql Error' );
		endif;
		
		$table_name = $wpdb->prefix . "bookaroom_times";
		

		foreach( $dateList as $val ):
			if( array_search( date( 'm/d/y', $val['start'] ), $delete ) ):
				continue;
			endif;
			$startTime = date('Y-m-d H:i:s', $val['start'] );
			$endTime = date('Y-m-d H:i:s', $val['end'] );
			$roomID = $_SESSION['bookaroom_meetings_externalVals']['roomID'];
			
			if( substr( $roomID, 0, 6 ) == 'noloc-' ):
				$roomInfo = explode( '-', $roomID );
				
				$branchID = $roomInfo[1];
				$roomID = NULL;
			else:
				$goodRoomID = $roomID;
				$branchID = NULL;
			endif;
			$sql = "INSERT INTO `{$table_name}` ( `ti_type`, `ti_extID`, `ti_startTime`, `ti_endTime`, `ti_roomID`, `ti_noLocation_branch` ) VALUES( 'event', '{$insertID}', '{$startTime}', '{$endTime}', '{$roomID}', '{$branchID}' )";
			
			$wpdb->query( $sql );

			$t_insertID = $wpdb->insert_id;
			if( empty( $t_insertID ) ):
				die( 'Mysql Error' );
			endif;			
	
		endforeach;	
		# ages
		
		$agesArr = array();
		foreach( $_SESSION['bookaroom_meetings_externalVals']['ageGroup'] as $key => $age ):
			$agesArr[] = "('{$insertID}', '{$age}')";
		endforeach;
		
		$table_name = $wpdb->prefix . "bookaroom_eventAges";
		
		$finalAges = implode( ', ', $agesArr );
		
		$sql = "INSERT INTO `$table_name` (`ea_eventID`, `ea_ageID`) VALUES {$finalAges}";
		$wpdb->query( $sql );
		
		# categories

		$table_name = $wpdb->prefix . "bookaroom_eventCats";
		
		$catsArr = array();
		foreach( $_SESSION['bookaroom_meetings_externalVals']['category'] as $key => $cat ):
			$catsArr[] = "('{$insertID}', '{$cat}')";
		endforeach;
		
		$table_name = $wpdb->prefix . "bookaroom_eventCats";
		
		$finalCats = implode( ', ', $catsArr );
		
		$sql = "INSERT INTO `$table_name` (`ec_eventID`, `ec_catID`) VALUES {$finalCats}";
		
		$wpdb->query( $sql );
	
		
	}
	
	protected static function editEventSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/events/eventForm_edit_success.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		echo $contents;
		
	}
		
	protected static function addEventSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/events/eventForm_success.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		echo $contents;
		
	}
	
	public static function branch_and_room_id( &$roomID, $branchList, $roomContList )
	{
		
		if( !empty( $roomID ) and array_key_exists( $roomID, $roomContList['id'] ) ):
			$branchID = $roomContList['id'][$roomID]['branchID'];
		# else check branchID
		else:
			$branchID = NULL;
		endif;
		
		if( !array_key_exists( $roomID, $roomContList['id'] ) ):
			$roomID = NULL;
		endif;
		
		return $branchID;		
	}

	protected static function checkAttendance( $externals )
	{
		$errorMSG = false;
		
		if( empty( $externals['attCount'] ) and empty( $externals['attNotes'] ) ):
			$errorMSG = 'You must enter either an attendance amount or a note.';
		elseif( !empty( $externals['attCount'] ) and !is_numeric( $externals['attCount'] ) ):
			$errorMSG = 'You must enter a valid number for attendance amount.';
		endif;
		
		return $errorMSG;
	}
	protected static function checkConflicts( $externals, $roomID, &$dateList, $delete = array(), $newVal = array(), $editID = NULL )
	{
		# find which kind of recurrence it is
		global $wpdb;
		
		$usedRooms = array();
		
		switch( $externals['recurrence'] ):
			case 'single':
				$dateList = self::getDates_single();
				break;

			case 'daily':
				$dateList = self::getDates_daily();
				break;
				
			case 'weekly':
				$dateList = self::getDates_weekly();
				break;

			case 'addDates':
				$dateList = self::getDates_addDates();
				break;				
				
			default:
				echo 'error: wrong recurrance';
				die();
				break;
		endswitch;
		
		
		# vaiables from includes
		$realRoomContList = bookaroom_settings_roomConts::getRoomContList();
		$allRoomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		
		# get room list from container ID
		
		$roomInfo = self::getRoomContListByRoomID( $roomID, $roomContList, $allRoomList );

		$allRooms = array_keys( $allRoomList['id'] );
		
		$closings = bookaroom_settings_closings::getClosingsList();
		
		$conflicts = array();

 		# check for closing
		if( empty( $closings['live'] ) or !is_array( $closings['live'] ) ):
			$closing['live'] = array();
		else:
		
			foreach( $closings['live'] as $val ):
				$closingRooms = unserialize( $val['roomsClosed'] );
				
				switch( $val['type'] ):
				
					case 'range':
						$startTime = $val['startTime'];
						$endTime = $val['endTime'];
						$range = true;
						break;
					
					case 'date':
						$startTime = $val['startTime'];
						$endTime = $val['startTime'] + 86399;
						$range = false;
						break;
				endswitch;
									
				if( !is_array( $dateList ) ):
					$dateList = array();
				endif;
				
				foreach( $dateList as $key => $dates):
	
					# check for deletes
					if( $range == true ):
						# range stuff
					else:
						$quickStart = date( 'm/d/y', $dates['start'] );
						
						if( is_array( $delete ) and in_array( $quickStart, $delete ) ):
							continue;
						endif;
	
					endif;
					# check for new vals

					if( $val['allClosed'] == true or array_intersect( $roomInfo['finalRoomConts'], $closingRooms ) ):
					# if the room is valid, lets check the date
	
						$temp = array();
						if( $dates['start'] >= $startTime and $dates['end'] <= $endTime ):								
							$temp['desc'] = $val['closingName'];
							$temp['type'] = 'Closing';
							$temp['startTime'] = date('Y-m-d H:i:s', $startTime );
							$temp['endTime'] = date('Y-m-d H:i:s', $endTime );
							$temp['allDay'] = true;
							
							# all rooms closed
							if( $val['allClosed'] == true ):
								$temp['roomID'] = array();
								$temp['allClosed'] = true;
								$closingRoomsTemp = $allRooms;
							# non numeric - find partial closes
							else:
								$temp['roomID'] = unserialize( $val['roomsClosed'] );
								$temp['allClosed'] = false;
								$closingRoomsTemp = $closingRooms;
							endif;
							
							$dateList[$key]['conflicts'][] = $temp;
							$dateList[$key]['usedRoomsClosings'] = $closingRoomsTemp;
							
							if ( !empty( $cval['ti_roomID'] ) ):
								$conflicts[]= $cval['ti_roomID'];
							endif;
	
							#$dateList[$key]['conflicts'][] = 'All Day Closing: '.$val['closingName'];
							#$conflicts++;
						endif;
					endif;
				endforeach;
	
			endforeach;
		endif;
		
		if( substr( $externals['roomID'], 0, 6 ) == 'noloc-' ):
			return $conflicts;
		endif;
		foreach( $dateList as $key => $val ):
			$quickStart = date( 'm/d/y', $val['start'] );
			
			if( is_array( $delete ) and in_array( $quickStart, $delete ) ):
				continue;
			endif;
			
			if( !empty( $editID ) ):
				$where = " and `ti`.`ti_id` != '{$editID}'";
			else:
				$where = NULL;
			endif;
			
			if( empty( $roomInfo['roomContList'] ) ):
				$roomInfo['roomContList'] = "''";
			endif;
			
			$sql = "SELECT `ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_type`, `res`.`me_eventName`, `res`.`ev_desc`, `ti`.`ti_roomID` 
					FROM `{$wpdb->prefix}bookaroom_times` AS `ti`
					LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` AS `res` ON `ti`.`ti_extID` = `res`.`res_id` 
					WHERE ( ( `res`.me_status != 'archived' and `res`.`me_status` != 'denied') AND `ti`.`ti_roomID` in ( {$roomInfo['roomContList']}) ) AND (`ti`.`ti_startTime` < '".date('Y-m-d H:i:s', $val['end'])."' && `ti`.`ti_endTime` > '".date('Y-m-d H:i:s', $val['start'])."' ){$where}";
			$cooked = $wpdb->get_results( $sql, ARRAY_A );

			# find all bAd
			$sql = "SELECT `ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_type`, `res`.`me_eventName`, `res`.`ev_desc`, `ti`.`ti_roomID` 
					FROM `{$wpdb->prefix}bookaroom_times` AS `ti`
					LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` AS `res` ON `ti`.`ti_extID` = `res`.`res_id` 
					WHERE ( ( `res`.me_status != 'archived' and `res`.`me_status` != 'denied') AND `ti`.`ti_startTime` < '".date('Y-m-d H:i:s', $val['end'])."' && `ti`.`ti_endTime` > '".date('Y-m-d H:i:s', $val['start'])."' )";

			$usedRooms = array();
			
			foreach( $wpdb->get_results( $sql, ARRAY_A ) as $badKey => $badVal ):
				$usedRooms[] = $badVal['ti_roomID'];
			endforeach;
			

			$dateList[$key]['usedRooms'] = @array_unique( $usedRooms );
			
			if( count( $cooked ) !== 0 ):
				foreach( $cooked as $ckey => $cval ):
					$temp = array();

					switch( $cval['ti_type'] ):
						case 'meeting':
							$temp['desc'] = $cval['me_eventName'];
							$temp['type'] = 'Meeting';
							break;
							
						case 'event':
							$temp['desc'] = $cval['ev_desc'];
							$temp['type'] = 'Event';
							break;
					endswitch;
					
					$temp['startTime'] = $cval['ti_startTime'];
					$temp['endTime'] = $cval['ti_endTime'];
					$temp['roomID'] = $cval['ti_roomID'];
					$dateList[$key]['conflicts'][] = $temp;
					
										
					$conflicts[]= $cval['ti_roomID'];

				

				endforeach;
			endif;

		endforeach;

		# find unused rooms.
		return $conflicts;	
		

	}
	
	protected static function checkDate( $date, $name )
	{
		if( empty( $date ) ):
			return "You must enter an {$name}.";
		endif;
		
		$dateArr = date_parse( $date );
		if( checkdate( $dateArr["month"], $dateArr["day"], $dateArr["year"] ) ):
			return NULL;
		else:
		    return "You must enter a valid date in the {$name} field.";
		endif;
	}
	
	protected static function checkFormInstance( &$externals, $roomContList, $branchList, $amenityList )
	{
		$final = array();
		$errorBG = array();
		
		# Follow Form Flow
		#
		##############################################
		
		# check event date
		###############################################
		if( true == ( $error = self::checkDate( $externals['eventStart'], 'event date' ) ) ):
			$final[] = $error;
			$errorBG['eventStart'] = true;
			unset( $error );
		endif;
		
	
		# time settings
		########################################
		# not all day 
		$goodStart = true;
		$goodEnd = true;
		
		if( !(!empty( $externals['allDay'] ) && $externals['allDay'] == 'true') ):
			
			# check start and end times

			if( true == ( $error = self::checkTime( $externals['startTime'], 'start time' ) ) ):
				$final[] = $error;
				$errorBG['startTime'] = true;
				$goodStart = false;
				unset( $error );
			endif;
			
			if( true == ( $error = self::checkTime( $externals['endTime'], 'end time' ) ) ):
				$final[] = $error;
				$errorBG['endTime'] = true;
				$goodEnd = false;
				unset( $error );
			endif;
		

			if( ( $goodStart and $goodEnd ) and ( ( strtotime( $externals['startTime'] ) >= strtotime( $externals['endTime'] ) ) ) ):
				$final[] = 'Your end time must come after your start time.';
				$errorBG['startTime'] = $errorBG['endTime'] = true;
			endif;
		endif;
		
		# errors?
		if( count( $final ) == 0 ):
			return false;
		else:
			return array( 'errorMSG' => implode( '<br />', $final ), 'errorBG' => $errorBG );
		endif;
	}	

	protected static function checkFormEvent( &$externals, $roomContList, $branchList, $amenityList )
	{
		$final = array();
		$errorBG = array();
		
		# event title
		if( empty( $externals['eventTitle'] ) ):
			$final[] = 'You must enter an event title.';
			$errorBG['eventTitle'] = true;
		endif;
		
		# event desc
		if( empty( $externals['eventDesc'] ) ):
			$final[] = 'You must enter an event description.';
			$errorBG['eventDesc'] = true;
		endif;		
		
		# registration
		if( empty( $externals['registration'] ) or !in_array( $externals['registration'], array( 'yes', 'no', 'staff' ) ) ):
			$final[] = 'You must choose if this event requires registration.';
			$errorBG['registration'] = true;
		endif;		
		
		# registration options
		if( $externals['registration'] !== 'no' ):
			# max reg number?
			if( empty( $externals['maxReg'] ) ):
				$final[] = 'You must enter a number for maximum registration.';
				$errorBG['maxReg'] = true;	
			elseif( self::NaN( $externals['maxReg'] ) ):
				$final[] = 'You must enter a valid number for maximum registration.';
				$errorBG['maxReg'] = true;	
					# Registration lower than total number
#			elseif( empty( $val['ti_noLocation_branch'] ) and $externals['maxReg'] > $roomContList['id'][$externals['roomID']]['occupancy'] ):
				#$final[] = 'You entered a maximum registration ('.$externals['maxReg'].') that is larger than the maximum occupancy of the room ('.$roomContList['id'][$externals['roomID']]['occupancy'].').';
				#$errorBG['maxReg'] = true;
			endif;
			
			# waiting list
			if( !empty( $externals['maxReg'] ) and self::NaN( $externals['maxReg'] ) ):
				$final[] = 'You must enter a valid number for your waiting list.';
				$errorBG['waitingList'] = true;	
			endif;
						
			# reg date
			if( empty( $externals['regDate'] ) ):
				$final[] = 'You must enter a registration begin date.';
				$errorBG['regDate'] = true;	
			elseif( true == ( $error = self::checkDate( $externals['regDate'], 'registration date' ) ) ):
				$final[] = $error;
				$errorBG['regDate'] = true;
				unset( $error );
			endif;
		endif;		
		
		# url for contact website
		if( !empty( $externals['website'] ) and !filter_var( $externals['website'], FILTER_VALIDATE_URL ) ):
			$final[] = 'You must enter a valid URL.';
			$errorBG['website'] = true;
		endif;

		# categories
		if( count( $externals['category'] ) < 1 ):
			$final[] = 'You must choose at least one category.';
			$errorBG['category'] = true;
		endif;
		
		# age groups
		if( count( $externals['ageGroup'] ) < 1 ):
			$final[] = 'You must choose at least one age group.';
			$errorBG['ageGroup'] = true;
		endif;
		
		# your name
		if( empty( $externals['yourName'] ) ):
			$final[] = 'You must enter your name.';
			$errorBG['yourName'] = true;
		endif;
		
		if( !empty( $externals['publicPhone'] ) ):
			$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['publicPhone'] );
			if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
			
			if( !is_numeric( $cleanPhone ) || strlen( $cleanPhone ) !== 10 ):
				$final[] = 'You must enter a valid contact phone number.';
				$errorBG['publicPhone'] = true;
			endif;
		endif;
		
		# errors?
		if( count( $final ) == 0 ):
			return false;
		else:
			return array( 'errorMSG' => implode( '<br />', $final ), 'errorBG' => $errorBG );
		endif;
	}
	
	protected static function checkForm( &$externals, $roomContList, $branchList, $amenityList )
	{
		$final = array();
		$errorBG = array();
		
		if( empty( $externals['roomID'] ) && empty( $externals['branchID'] ) ):
			$final[] = 'You must choose a location.';
			$errorBG['location'] = true;
		endif;
		
		# Follow Form Flow
		#
		##############################################
		
		# check event date
		###############################################
		if( true == ( $error = self::checkDate( $externals['eventStart'], 'event date' ) ) ):
			$final[] = $error;
			$errorBG['eventStart'] = true;
			unset( $error );
		endif;
		
		# check recurrence
		self::checkRecurrence( $externals, $final, $errorBG );
		
		
		# time settings
		########################################
		# not all day 
		$goodStart = true;
		$goodEnd = true;
		
		if( !(!empty( $externals['allDay'] ) && $externals['allDay'] == 'true') ):
			
			# check start and end times

			if( true == ( $error = self::checkTime( $externals['startTime'], 'start time' ) ) ):
				$final[] = $error;
				$errorBG['startTime'] = true;
				$goodStart = false;
				unset( $error );
			endif;
			
			if( true == ( $error = self::checkTime( $externals['endTime'], 'end time' ) ) ):
				$final[] = $error;
				$errorBG['endTime'] = true;
				$goodEnd = false;
				unset( $error );
			endif;
		

			if( ( $goodStart and $goodEnd ) and ( ( strtotime( $externals['startTime'] ) >= strtotime( $externals['endTime'] ) ) ) ):
				$final[] = 'Your end time must come after your start time.';
				$errorBG['startTime'] = $errorBG['endTime'] = true;
			endif;
		endif;
		
		# event title
		if( empty( $externals['eventTitle'] ) ):
			$final[] = 'You must enter an event title.';
			$errorBG['eventTitle'] = true;
		endif;
		
		# event desc
		if( empty( $externals['eventDesc'] ) ):
			$final[] = 'You must enter an event description.';
			$errorBG['eventDesc'] = true;
		endif;		
		
		# registration
		if( empty( $externals['registration'] ) or !in_array( $externals['registration'], array( 'yes', 'no', 'staff' ) ) ):
			$final[] = 'You must choose if this event requires registration.';
			$errorBG['registration'] = true;
		endif;		
		
		# registration options
		if( $externals['registration'] !== 'no' ):
			# max reg number?
			if( empty( $externals['maxReg'] ) ):
				$final[] = 'You must enter a number for maximum registration.';
				$errorBG['maxReg'] = true;	
			elseif( self::NaN( $externals['maxReg'] ) ):
				$final[] = 'You must enter a valid number for maximum registration.';
				$errorBG['maxReg'] = true;	
					# Registration lower than total number
			elseif( substr( $externals['roomID'], 0, 6 ) !== 'noloc-' and ( $externals['maxReg'] > $roomContList['id'][$externals['roomID']]['occupancy'] ) ):
				$final[] = 'You entered a maximum registration ('.$externals['maxReg'].') that is larger than the maximum occupancy of the room ('.$roomContList['id'][$externals['roomID']]['occupancy'].').';
				$errorBG['maxReg'] = true;
			endif;
			
			# waiting list
			if( !empty( $externals['maxReg'] ) and self::NaN( $externals['maxReg'] ) ):
				$final[] = 'You must enter a valid number for your waiting list.';
				$errorBG['waitingList'] = true;	
			endif;
						
			# reg date
			if( empty( $externals['regDate'] ) ):
				$final[] = 'You must enter a registration begin date.';
				$errorBG['regDate'] = true;	
			elseif( true == ( $error = self::checkDate( $externals['regDate'], 'registration date' ) ) ):
				$final[] = $error;
				$errorBG['regDate'] = true;
				unset( $error );
			endif;
		endif;		
		
		# url for contact website
		if( !empty( $externals['website'] ) and !filter_var( $externals['website'], FILTER_VALIDATE_URL ) ):
			$final[] = 'You must enter a valid URL.';
			$errorBG['website'] = true;
		endif;

		# categories
		if( count( $externals['category'] ) < 1 ):
			$final[] = 'You must choose at least one category.';
			$errorBG['category'] = true;
		endif;
		
		# age groups
		if( count( $externals['ageGroup'] ) < 1 ):
			$final[] = 'You must choose at least one age group.';
			$errorBG['ageGroup'] = true;
		endif;
		
		# your name
		if( empty( $externals['yourName'] ) ):
			$final[] = 'You must enter your name.';
			$errorBG['yourName'] = true;
		endif;
		
		if( !empty( $externals['publicPhone'] ) ):
			$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['publicPhone'] );
			if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
			
			if( !is_numeric( $cleanPhone ) || strlen( $cleanPhone ) !== 10 ):
				$final[] = 'You must enter a valid contact phone number.';
				$errorBG['publicPhone'] = true;
			endif;
		endif;


				
		# errors?
		if( count( $final ) == 0 ):
			return false;
		else:
			return array( 'errorMSG' => implode( '<br />', $final ), 'errorBG' => $errorBG );
		endif;
	}
	
	protected static function checkHash( $externals )
	{
		# is hash or time empty	
		if( empty( $externals['time'] ) or empty( $externals['hash'] ) or empty( $externals['eventID'] ) ):
			return false;
		endif;
		
		if( md5( $externals['time'].$externals['eventID'] ) !== $externals['hash'] ):
			return false;
		endif;
		
		return true;
	}
	
	protected static function checkID( $eventID )
	{
		global $wpdb;		
		
		$sql = "SELECT 
`ti`.`ti_id`, `ti`.`ti_type`, `ti`.`ti_extID`, `ti`.`ti_created`, `ti`.`ti_extraInfo`, 
`ti`.`ti_startTime`, `ti`.`ti_endTime`, `ti`.`ti_roomID`, 
`ti`.`ti_noLocation_branch`,`ev`.`res_id`, `ev`.`res_created`, 
`ev`.`ev_desc`, `ev`.`ev_maxReg`, `ev`.`ev_amenity`, `ev`.`ev_waitingList`, 
`ev`.`ev_presenter`, `ev`.`ev_privateNotes`, `ev`.`ev_publicEmail`, `ev`.`ev_publicName`, 
`ev`.`ev_publicPhone`, `ev`.`ev_noPublish`, `ev`.`ev_regStartDate`, `ev`.`ev_regType`, 
`ev`.`ev_submitter`, `ev`.`ev_title`, `ev`.`ev_website`, `ev`.`ev_webText`, `ti`.`ti_attendance`, 
`ti`.`ti_attNotes`, 

COUNT( DISTINCT `tiCheck`.`ti_id` ) as `tiCount`, 

GROUP_CONCAT( DISTINCT `ages`.`ea_ageID` SEPARATOR  ',' ) as 'ageGroup', 
GROUP_CONCAT( DISTINCT `cats`.`ec_catID` SEPARATOR  ',' ) as 'category' 

FROM `{$wpdb->prefix}bookaroom_times` as `ti` 
LEFT JOIN `{$wpdb->prefix}bookaroom_reservations` as `ev` ON `ev`.`res_id` = `ti`.`ti_extID` 

LEFT JOIN `{$wpdb->prefix}bookaroom_times` AS `tiCheck` ON `tiCheck`.`ti_extID` = `ev`.`res_id` 

LEFT JOIN `{$wpdb->prefix}bookaroom_eventAges` AS `ages` ON  `ages`.`ea_eventID` = `ev`.`res_id`
LEFT JOIN `{$wpdb->prefix}bookaroom_eventCats` AS `cats` ON  `cats`.`ec_eventID` = `ev`.`res_id`
WHERE `ti`.`ti_id` = '{$eventID}' 
GROUP BY `ti`.`ti_id`";
		
		$cooked = ( $wpdb->get_row( $sql, ARRAY_A ) );
		
		if( $cooked == NULL ) return false;
		
		$cooked['ageGroup'] = explode( ',', $cooked['ageGroup'] );

		$cooked['category'] = explode( ',', $cooked['category'] );
		
		switch( $cooked['ev_regType'] ):
			case 'yes':
				$cooked['registration'] = 'true';
				break;
			case 'no':
				$cooked['registration'] = 'false';
				break;
				
		endswitch;
		
		return $cooked;
	}
	
	protected static function checkRecurrence_addDates( &$externals, &$final, &$errorBG )
	{
		$errorDate = false;
		
		# count dates to see if empty
		if( !is_array( $externals['addDateVals'] ) ):
			$externals['addDateVals'] = array();
		endif;
		
		$externals['addDateVals'] = array_filter( array_unique( $externals['addDateVals'] ) );
		if( false == count( $externals['addDateVals'] ) ):
			$final[] = 'You must add at least one <em>valid date</em> to the Add Date recurrence entry.';
			$errorBG['addDates'] = true;
		endif;
		
		$count = 0;
		foreach( $externals['addDateVals'] as $key => $val ):
			if( true == self::checkDate( $val, NULL ) ):
				$errorDate = true;
				$errorBG['addDateVals'][$count] = true;
			endif;
			$count++;
		endforeach;
		
		if( $errorDate ):
			$final[] = 'At least one of your dates in the Add Dates option is invalid. Please check your dates.';
		endif;
		
	}
	
	protected static function checkRecurrence_daily( &$externals, &$final, &$errorBG )
	{
		# first, check that there is an option (valid) checked.
		$validOptions = array( 'everyNDays', 'weekends', 'weekdays' );
		
		if( empty( $externals['dailyType'] ) or !in_array( $externals['dailyType'], $validOptions ) ):
			$final[] = 'You must choose a Daily Recurrence option.';
			$errorBG['dailyType_everyNDays'] = $errorBG['dailyType_weekends'] = $errorBG['dailyType_weekdays'] = true;
		endif;
		
		# if Every N Days is selected, make sure the dailyEveryNDaysVal isn't empty or non numeric
		if( !empty( $externals['dailyType'] ) and $externals['dailyType'] == 'everyNDays' ):
			if( empty( $externals['dailyEveryNDaysVal'] ) or self::NaN( $externals['dailyEveryNDaysVal'] ) ):
				$final[] = 'You must enter a valid number for the <em>every n days</em> setting in Daily Recurrence.';
				$errorBG['dailyType_everyNDays'] = true;
			endif;
		endif;
		
		# check that there is a valid option for end choice checked
		# first, check that there is an option (valid) checked.
		$validOptions = array( 'Occurrences', 'endBy' );
		
		if( empty( $externals['dailyEndType'] ) or !in_array( $externals['dailyEndType'], $validOptions ) ):
			$final[] = 'You must choose a Daily Recurrence <em>end choice</em> option.';
			$errorBG['dailyEndType_Occurrences'] = $errorBG['dailyEndType_endBy'] = true;
		else:
			if( $externals['dailyEndType'] == 'Occurrences' && ( empty( $externals['daily_Occurrence'] ) || self::NaN( $externals['daily_Occurrence'] ) ) ):
				$final[] = 'You must enter a valid number in the Daily Recurrence <em>Occurrences</em> option.';
				$errorBG['dailyEndType_Occurrences_form'] = true;
			endif;
			
			if( $externals['dailyEndType'] == 'endBy' ):
				if( empty( $externals['daily_endBy'] ) ):
					$final[] = 'You must enter a date for the <em>end by</em> setting .';
					$errorBG['dailyEndType_endBy_form'] = true;
				elseif( !is_null( $errorTemp = self::checkDate( $externals['daily_endBy'], 'Daily Recurrence <em>end by</em>' ) ) ):
					$final[] = $errorTemp;
					$errorBG['dailyEndType_endBy_form'] = true;
				else:
					if( is_null( $errorTemp = self::checkDate( $externals['eventStart'], NULL ) ) ):
						$eventStart = strtotime( $externals['eventStart'] );
						$reocStart = strtotime( $externals['daily_endBy'] );
						if( $reocStart <= $eventStart ):
							$final[] = 'Your Daily Recurrence <em>end by</em> date must come after your <em>event date</em>.';
							$errorBG['dailyEndType_endBy_form'] = true;
							$errorBG['eventStart'] = true;
						endif;
					endif;
				endif;
			endif;
				
		endif;
		
		# check that end by Occurrences are filled in
		
	}
	
	protected static function checkRecurrence_weekly( &$externals, &$final, &$errorBG )
	{
		# check for every n weeks value
		if( empty( $externals['everyNWeeks'] ) or self::NaN( $externals['everyNWeeks'] ) ):
			$final[] = 'You must enter a valid number in the <em>Every N Weeks</em> option in Weekly Recurrence.';
			$errorBG['everyNWeeks'] = true;
		endif;
		
		# check that days are selected
		if( count( $externals['weeklyDay'] ) == 0 ):
			$final[] = 'You must choose some days in Weekly Recurrence.';
			$errorBG['weeklyDay'] = true;
		endif;
		
		# check that there is a valid option for end choice checked
		# first, check that there is an option (valid) checked.
		$validOptions = array( 'Occurrences', 'endBy' );
		
		if( empty( $externals['weeklyEndType'] ) or !in_array( $externals['weeklyEndType'], $validOptions ) ):
			$final[] = 'You must choose a Weekly Recurrence <em>end choice</em> option.';
			$errorBG['weeklyEndType_Occurrences'] = $errorBG['weeklyEndType_endBy'] = true;
		else:
			if( $externals['weeklyEndType'] == 'Occurrences' && ( empty( $externals['weekly_Occurrence'] ) || self::NaN( $externals['weekly_Occurrence'] ) ) ):
				$final[] = 'You must enter a valid number in the Weekly Recurrence <em>Occurrences</em> option.';
				$errorBG['weeklyEndType_Occurrences_form'] = true;
			endif;
			
			if( $externals['weeklyEndType'] == 'endBy' ):
				if( empty( $externals['weekly_endBy'] ) ):
					$final[] = 'You must enter a date for the <em>end by</em> setting .';
					$errorBG['weeklyEndType_endBy_form'] = true;
				elseif( !is_null( $errorTemp = self::checkDate( $externals['weekly_endBy'], 'Weekly Recurrence <em>end by</em>' ) ) ):
					$final[] = $errorTemp;
					$errorBG['weeklyEndType_endBy_form'] = true;
				else:
					if( is_null( $errorTemp = self::checkDate( $externals['eventStart'], NULL ) ) ):
						$eventStart = strtotime( $externals['eventStart'] );
						$reocStart = strtotime( $externals['weekly_endBy'] );
						if( $reocStart <= $eventStart ):
							$final[] = 'Your Weekly Recurrence <em>end by</em> date must come after your <em>event date</em>.';
							$errorBG['weeklyEndType_endBy_form'] = true;
							$errorBG['eventStart'] = true;
						endif;
					endif;
				endif;
			endif;
				
		endif;
	}
	
	protected static function checkRecurrence( &$externals, &$final, &$errorBG )
	{
		# recurrence options
		# recurrence drop down
		$reocArray = array( 'single', 'daily', 'weekly', 'addDates' );
		
		# check that it's in the list of good options
		if( empty( $externals['recurrence'] ) or !in_array( $externals['recurrence'], $reocArray ) ):
			$final[] = 'You must choose a valid recurrence option.';
			$errorBG['recurrence'] = true;
		endif;
		
		switch( $externals['recurrence'] ):
			case 'daily':
				self::checkRecurrence_daily( $externals, $final, $errorBG );

				break;
			
			case 'weekly':
				self::checkRecurrence_weekly( $externals, $final, $errorBG );
				break;

			case 'addDates':
				self::checkRecurrence_addDates( $externals, $final, $errorBG );
				break;
		endswitch;
				
		return true;
			
	}
	
	protected static function checkReg( $externals )
	{
		$error = array();
		
		# check for empty values
		if( empty( $externals['regName'] ) ):
			$error[] = 'You must enter the full name of the person who is registering.';
		endif;
		
		if( empty( $externals['regPhone'] ) and empty( $externals['regEmail'] ) ):
			$error[] = 'You must enter contact information; either a phone number or email address where you can be reached.';			
		else:
			if( !empty( $externals['regPhone'] ) ):
				$cleanPhone = preg_replace( "/[^0-9]/", '', $externals['regPhone'] );
				if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );
				if( !is_numeric( $cleanPhone ) || strlen( $cleanPhone ) !== 10 ):
					$error[] = 'You must enter a valid phone number.';
				endif;
				
			endif;
			
			if( !empty( $externals['regEmail'] ) and !filter_var( $externals['regEmail'], FILTER_VALIDATE_EMAIL ) ):
				$error[] = "Please enter a valid email address.";
			endif;
		endif;
		
		if( count( $error )!== 0 ):
			return implode( "<br />", $error );
		else:
			return false;
		endif;	
		
		
	}
	protected static function checkTime( &$timeVal, $name )
	{
		if( empty( $timeVal ) ):
			return "You must enter a time in the {$name} field.";
		endif;
		
		if( !preg_match( '/^(0?\d|1[0-2]):[0-5]\d\s(am|pm)$/i', $timeVal ) ):
            return 'You must enter a valid time (hh:mm am/pm) in the '.$name.' field.';
        endif;
		
		$timeVal = ltrim( $timeVal, '0' );
				
		# check against increment
		$baseIncrement = get_option( 'bookaroom_baseIncrement' );
		
		$timer = strtotime( $timeVal );
		
		# hours
		$minutes = date( 'H', $timer ) * 60;
		# minutes
		$minutes += date( 'i', $timer );

		# round to increment
		$cleanTime = $baseIncrement * ( round( $minutes/$baseIncrement ) );
		$hours = floor($cleanTime/60);
    	$cleanMinutes = str_pad( $cleanTime%60, 2, '0', STR_PAD_LEFT );
		
		if( $hours == 0 ):
			$hours = '12'; $ampm = 'am';
		elseif( $hours == 12 ):
			$ampm = 'pm';
		elseif( $hours > 12 ):
			$hours -= 12; $ampm = 'pm';
		else:
			$ampm = 'am';
		endif;
		
		$newTimeVal = "{$hours}:{$cleanMinutes} {$ampm}";
		
		if( $newTimeVal !== $timeVal ):
			return "Times are scheduled in {$baseIncrement} minute intervals. Your {$name} time has been changed to reflect that. Please double check yout times and submit the form again if they are okay.";
		endif;
		
		return false;
	}
	
	protected static function deleteMulti( $eventInfo, $externals, $finalDelete, $instanceKeys )
	{
		global $wpdb;
		# first, deltete instances
		$deleteKeys = implode( ',', $finalDelete );


		$sql = "INSERT INTO `{$wpdb->prefix}bookaroom_times_deleted` ( `ti_id`, `ti_type`, `ti_extID`, `ti_created`, `ti_startTime`, `ti_endTime`, `ti_roomID`, `ti_noLocation_branch`, `ti_extraInfo` )
				SELECT `ti_id`, `ti_type`, `ti_extID`, `ti_created`, `ti_startTime`, `ti_endTime`, `ti_roomID`, `ti_noLocation_branch`, `ti_extraInfo` 
				FROM `{$wpdb->prefix}bookaroom_times` WHERE `ti_id` IN ({$deleteKeys})";
				
		$wpdb->query( $sql );

		$sql = "DELETE FROM `{$wpdb->prefix}bookaroom_times` WHERE `ti_id` IN ({$deleteKeys})";
		
		$wpdb->query( $sql );
		
		if( count( $instanceKeys ) - count( $finalDelete ) == 0 ):

			$sql = "INSERT INTO `{$wpdb->prefix}bookaroom_reservations_deleted` ( `res_id`, `res_created`, `ev_desc`, `ev_maxReg`, `ev_amenity`, `ev_waitingList`, `ev_presenter`, `ev_privateNotes`, `ev_publicEmail`, `ev_publicName`, `ev_publicPhone`, `ev_noPublish`, `ev_regStartDate`, `ev_regType`, `ev_submitter`, `ev_title`, `ev_website`, `ev_webText`, `me_amenity`, `me_contactAddress1`, `me_contactAddress2`, `me_contactCity`, `me_contactEmail`, `me_contactName`, `me_contactPhonePrimary`, `me_contactPhoneSecondary`, `me_contactState`, `me_contactWebsite`, `me_contactZip`, `me_desc`, `me_eventName`, `me_nonProfit`, `me_numAttend`, `me_notes`, `me_status` )
				SELECT `res_id`, `res_created`, `ev_desc`, `ev_maxReg`, `ev_amenity`, `ev_waitingList`, `ev_presenter`, `ev_privateNotes`, `ev_publicEmail`, `ev_publicName`, `ev_publicPhone`, `ev_noPublish`, `ev_regStartDate`, `ev_regType`, `ev_submitter`, `ev_title`, `ev_website`, `ev_webText`, `me_amenity`, `me_contactAddress1`, `me_contactAddress2`, `me_contactCity`, `me_contactEmail`, `me_contactName`, `me_contactPhonePrimary`, `me_contactPhoneSecondary`, `me_contactState`, `me_contactWebsite`, `me_contactZip`, `me_desc`, `me_eventName`, `me_nonProfit`, `me_numAttend`, `me_notes`, `me_status` 
				FROM `{$wpdb->prefix}bookaroom_reservations` WHERE `res_id` = '{$eventInfo['ti_extID']}'";
		
			
			$wpdb->query( $sql );
			
			$sql = "DELETE FROM `{$wpdb->prefix}bookaroom_reservations` WHERE `res_id` = '{$eventInfo['ti_extID']}'";	
			$wpdb->query( $sql );
			
			
		endif;

		
		
		
		
	}
	
	protected static function showDeleteRegistrations( $eventInfo, $reg_id, $branchList, $roomContList )
	{
		global $wpdb;
				
		$filename = BOOKAROOM_PATH . 'templates/events/deleteRegistration.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# event info
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		$contents = str_replace( '#title#', $eventInfo['ev_title'], $contents );
		$contents = str_replace( '#desc#', $eventInfo['ev_desc'], $contents );
		$contents = str_replace( '#date#', date('l, F jS, Y', strtotime( $eventInfo['ti_startTime'] ) ), $contents );
		
		# branch and room
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$contents = str_replace( '#branch#', $branchList[$eventInfo['ti_noLocation_branch']]['branchDesc'], $contents );
			$contents = str_replace( '#room#', 'No location required', $contents );
		else:
			$contents = str_replace( '#room#', $roomContList['id'][$eventInfo['ti_roomID']]['desc'], $contents );
			$contents = str_replace( '#branch#', $branchList[$roomContList['id'][$eventInfo['ti_roomID']]['branchID']]['branchDesc'], $contents );
		endif;
		# all day?
		$startTime = date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) );
		$endTime = date( 'g:i a', strtotime( $eventInfo['ti_endTime'] ) );
		
		if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
			$contents = str_replace( '#time#', 'All Day', $contents );		
		else:		
			$contents = str_replace( '#time#', date('g:i a', strtotime( $eventInfo['ti_startTime'] ) ).' -'.date('g:i a', strtotime( $eventInfo['ti_endTime'] ) ), $contents );
		endif;
		
		$registrations = self::getRegistrations( $eventInfo['ti_id'] );
		
		$contents = str_replace( '#registered#', count( $registrations ), $contents );
		$contents = str_replace( '#registeredTotal#', $eventInfo['ev_maxReg'], $contents );
		$contents = str_replace( '#reg_id#', $reg_id, $contents );
		
		$time = time();
		
		$contents = str_replace( '#hashTime#', $time, $contents );
		$hash = md5( $time.$reg_id.$eventInfo['ti_id'] );
		$contents = str_replace( '#hash#', $hash, $contents );
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		
		
		$contents = str_replace( '#regName#', $registrations[$reg_id]['reg_fullName'], $contents );
		
		$regPhone = ( !empty( $registrations[$reg_id]['reg_phone'] ) ) ? $registrations[$reg_id]['reg_phone'] : NULL;
		$contents = str_replace( '#regPhone#', $regPhone, $contents );
		
		$contents = str_replace( '#regNotes#', $registrations[$reg_id]['reg_notes'], $contents );
		$contents = str_replace( '#regDate#', date( 'm/d/Y', strtotime( $registrations[$reg_id]['reg_dateReg'] ) ), $contents );
		$contents = str_replace( '#regTime#', date( 'g:i a', strtotime( $registrations[$reg_id]['reg_dateReg'] ) ), $contents );
		
		$regEmail_line = repCon( 'regEmail', $contents );
		if( empty( $registrations[$reg_id]['reg_email'] ) ):
			$contents = str_replace( '#regEmail_line#', NULL, $contents );
		else:
			$contents = str_replace( '#regEmail_line#', $regEmail_line, $contents );
			$contents = str_replace( '#regEmail#', $registrations[$reg_id]['reg_email'], $contents );
		endif;
		
		echo $contents;
	}

	protected static function showEditRegistrations( $eventInfo, $externals, $branchList, $roomContList, $errorMSG = NULL )
	{
		global $wpdb;
		
		$filename = BOOKAROOM_PATH . 'templates/events/editRegistration.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );

		$reg_id = $externals['regID'];
		
		# event info
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		$contents = str_replace( '#title#', $eventInfo['ev_title'], $contents );
		$contents = str_replace( '#desc#', make_brief( $eventInfo['ev_desc'], 50 ), $contents );
		$contents = str_replace( '#date#', date('l, F jS, Y', strtotime( $eventInfo['ti_startTime'] ) ), $contents );
		
		# branch and room
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$contents = str_replace( '#branch#', $branchList[$eventInfo['ti_noLocation_branch']]['branchDesc'], $contents );
			$contents = str_replace( '#room#', 'No location required', $contents );
		else:
			$contents = str_replace( '#room#', $roomContList['id'][$eventInfo['ti_roomID']]['desc'], $contents );
			$contents = str_replace( '#branch#', $branchList[$roomContList['id'][$eventInfo['ti_roomID']]['branchID']]['branchDesc'], $contents );
		endif;
		# all day?
		$startTime = date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) );
		$endTime = date( 'g:i a', strtotime( $eventInfo['ti_endTime'] ) );
		
		if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
			$contents = str_replace( '#time#', 'All Day', $contents );		
		else:		
			$contents = str_replace( '#time#', date('g:i a', strtotime( $eventInfo['ti_startTime'] ) ).' -'.date('g:i a', strtotime( $eventInfo['ti_endTime'] ) ), $contents );
		endif;
		
		$registrations = self::getRegistrations( $eventInfo['ti_id'] );
		
		$contents = str_replace( '#registered#', count( $registrations ), $contents );
		$contents = str_replace( '#registeredTotal#', $eventInfo['ev_maxReg'], $contents );
		$contents = str_replace( '#reg_id#', $reg_id, $contents );
		
		$time = time();
		
		$contents = str_replace( '#hashTime#', $time, $contents );
		$hash = md5( $time.$reg_id.$eventInfo['ti_id'] );
		$contents = str_replace( '#hash#', $hash, $contents );
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		
		
		# error message
		$errorMSG_line = repCon( 'errorMSG', $contents );
		if( !empty( $errorMSG ) ):
			$contents = str_replace( '#errorMSG_line#', $errorMSG_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
			$regName	= $externals['regName'];
			$regPhone	= $externals['regPhone'];
			$regEmail	= $externals['regEmail'];
			$regNotes	= $externals['regNotes'];
		else:
			$contents = str_replace( '#errorMSG_line#', NULL, $contents );
			$regName	= $registrations[$reg_id]['reg_fullName'];
			$regPhone	= $registrations[$reg_id]['reg_phone'];
			$regEmail	= $registrations[$reg_id]['reg_email'];
			$regNotes	= $registrations[$reg_id]['reg_notes'];
		endif;
		
		$contents = str_replace( '#regName#', $regName, $contents );
		
		$regPhone = ( !empty( $regPhone ) ) ? $regPhone : NULL;
		$contents = str_replace( '#regPhone#', $regPhone, $contents );
		
		$contents = str_replace( '#regNotes#', $regNotes, $contents );
		$contents = str_replace( '#regDate#', date( 'm/d/Y', strtotime( $registrations[$reg_id]['reg_dateReg'] ) ), $contents );
		$contents = str_replace( '#regTime#', date( 'g:i a', strtotime( $registrations[$reg_id]['reg_dateReg'] ) ), $contents );
		
		$contents = str_replace( '#regEmail#', $regEmail, $contents );
		
		echo $contents;
	}

	protected static function deleteReg( $externals, $eventInfo )
	{
		
		$registrations = self::getRegistrations( $externals['eventID'] );
		
		
		# make sure reg exists
		if( !array_key_exists( $externals['regID'], $registrations ) ):
			$final['status'] = false;
			$final['errorMSG'] = 'There was a problem deleting that registration. The ID doesn\'t exist. Please try again.';
			return $final;			
		endif;
		
		$arrKeys = array_keys( $registrations );
		
		# if under max reg, find replacement
		$alertID = NULL;

		if( array_search( $externals['regID'], $arrKeys ) + 1 <= $eventInfo['ev_maxReg'] ):
			if( !empty( $arrKeys[$eventInfo['ev_maxReg']] ) ):
				$alertID = ( $arrKeys[$eventInfo['ev_maxReg']] );
			endif;
		endif;
		
		global $wpdb;
						 
		$sql = "DELETE FROM `{$wpdb->prefix}bookaroom_registrations` WHERE `reg_id` = '{$externals['regID']}'";
		
		$wpdb->query( $sql );
		
		$final['status'] = true;
		$final['alertID'] = $alertID;
		
		
		return $final;
		
		
	}
	
	protected static function deleteSingle( $eventInfo, $instance )
	{
		
		global $wpdb;
		

		$sql = "INSERT INTO `{$wpdb->prefix}bookaroom_times_deleted` ( `ti_id`, `ti_type`, `ti_extID`, `ti_created`, `ti_startTime`, `ti_endTime`, `ti_roomID`, `ti_noLocation_branch` )
				SELECT `ti_id`, `ti_type`, `ti_extID`, `ti_created`, `ti_startTime`, `ti_endTime`, `ti_roomID`, `ti_noLocation_branch` 
				FROM `{$wpdb->prefix}bookaroom_times` WHERE `ti_id` = '{$eventInfo['ti_id']}'";
		
		$wpdb->query( $sql );
		
		$sql = "DELETE FROM `{$wpdb->prefix}bookaroom_times` WHERE `ti_id` = '{$eventInfo['ti_id']}'";		
		
		$wpdb->query( $sql );
		
		
		if( $instance == false or $eventInfo['tiCount'] == 1 ):
			$sql = "INSERT INTO `{$wpdb->prefix}bookaroom_reservations_deleted` ( `res_id`, `res_created`, `ev_desc`, `ev_maxReg`, `ev_amenity`, `ev_waitingList`, `ev_presenter`, `ev_privateNotes`, `ev_publicEmail`, `ev_publicName`, `ev_publicPhone`, `ev_noPublish`, `ev_regStartDate`, `ev_regType`, `ev_submitter`, `ev_title`, `ev_website`, `ev_webText`, `me_amenity`, `me_contactAddress1`, `me_contactAddress2`, `me_contactCity`, `me_contactEmail`, `me_contactName`, `me_contactPhonePrimary`, `me_contactPhoneSecondary`, `me_contactState`, `me_contactWebsite`, `me_contactZip`, `me_desc`, `me_eventName`, `me_nonProfit`, `me_numAttend`, `me_notes`, `me_status` )
				SELECT `res_id`, `res_created`, `ev_desc`, `ev_maxReg`, `ev_amenity`, `ev_waitingList`, `ev_presenter`, `ev_privateNotes`, `ev_publicEmail`, `ev_publicName`, `ev_publicPhone`, `ev_noPublish`, `ev_regStartDate`, `ev_regType`, `ev_submitter`, `ev_title`, `ev_website`, `ev_webText`, `me_amenity`, `me_contactAddress1`, `me_contactAddress2`, `me_contactCity`, `me_contactEmail`, `me_contactName`, `me_contactPhonePrimary`, `me_contactPhoneSecondary`, `me_contactState`, `me_contactWebsite`, `me_contactZip`, `me_desc`, `me_eventName`, `me_nonProfit`, `me_numAttend`, `me_notes`, `me_status` 
				FROM `{$wpdb->prefix}bookaroom_reservations` WHERE `res_id` = '{$eventInfo['ti_extID']}'";
		
			$wpdb->query( $sql );
	
			$sql = "DELETE FROM `{$wpdb->prefix}bookaroom_reservations` WHERE `res_id` = '{$eventInfo['ti_extID']}'";		
		
			$wpdb->query( $sql );
						
		endif;
		return TRUE;		
				
	}
	
	protected static function deleteSuccess()
	{
		$filename = BOOKAROOM_PATH . 'templates/events/delete_success.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		echo $contents;
		
	}
	public static function editRegistration( $externals )
	{
		global $wpdb;

		$regID		= $externals['regID'];
		$regName	= $externals['regName'];
	    $regPhone	= $externals['regPhone'];
    	$regNotes	= $externals['regNotes'];
    	$regEmail	= $externals['regEmail'];
		
		if( empty( $regPhone ) ):
			$cleanPhone = NULL;
		else:
			$cleanPhone = preg_replace( "/[^0-9]/", '', $regPhone );
			if ( strlen( $cleanPhone ) == 11 ) $cleanPhone = preg_replace( "/^1/", '', $cleanPhone );

			$cleanPhone = "(" . substr($cleanPhone, 0, 3) . ") " . substr($cleanPhone, 3, 3) . "-" . substr($cleanPhone, 6);	
		endif;		
		
		$sql = "UPDATE `{$wpdb->prefix}bookaroom_registrations` SET 
				`reg_fullName` = '{$externals['regName']}', 
				`reg_phone` = '{$cleanPhone}', 
				`reg_email` = '{$externals['regEmail']}', 
				`reg_notes` = '{$externals['regNotes']}' 
				WHERE `reg_id` = '{$externals['regID']}' LIMIT 1";
		$wpdb->query( $sql );		
		
	}
	
	public static function event_choose_branch_AJAX()
	{

		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		
		$branchID = ( !empty( $_REQUEST['branchID'] ) ) ? $_REQUEST['branchID'] : NULL;
		
		# make top
		$temp = array();
		foreach( $branchList as $key => $val ):
			if( $key == $branchID ):
				$temp[] = "<h3>{$val[branchDesc]}</h3>";
			else:
				$temp[] = '<p><a class="event_choose_branch_link" branchID="'.$key.'" href="admin-ajax.php">'.$val['branchDesc'].'</a></p>';
			endif;
		endforeach;
		$final['top'] = implode( "\r\n", $temp );
		
		# make room list
		$temp = array();
		if( empty( $branchID ) ):
			$final['room_list'] = 'Choose a branch to view rooms. test';
		else:
			foreach( $roomContList['branch'][$branchID] as $key => $val ):
				$temp[] = '<p><a class="event_choose_roomCont_link" roomContID="'.$key.'" href="admin-ajax.php">'.$roomContList['id'][$val]['desc'].'111</a></p>';
				#$temp[] = $roomContList['id'][$val]['desc'];
			endforeach; 

		endif;
		
		if( count( $temp ) == 0 ):
			$final['room_list'] = 'No rooms for this branch';
		else:
			$final['room_list'] = implode( "\r\n", $temp );
		endif;
		
		
		
		
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		{
			echo json_encode( $final );
		} else {
			echo "Error!";
		}
		die();
	}
	
	public static function event_choose_room_AJAX()
	{

		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-branches.php' );
		require_once( BOOKAROOM_PATH . '/bookaroom-meetings-roomConts.php' );
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		
		$roomContID = ( !empty( $_REQUEST['roomContID'] ) ) ? $_REQUEST['roomContID'] : NULL;
		

		
		# make room list
		$temp = array();
		foreach( $roomContList['branch'][$branchID] as $key => $val ):
			if( $val == $roomContID ):
				$temp[] = '<h3>'.$roomContList['id'][$val]['desc'].'</h3>';
			else:
				$temp[] = '<p><a class="event_choose_roomCont_link" roomContID="'.$key.'" href="admin-ajax.php">['.$val.'] ['.$roomContID.']'.$roomContList['id'][$val]['desc'].'222</a></p>';
				
			endif;
			#$temp[] = $roomContList['id'][$val]['desc'];
		endforeach; 
		
		if( count( $temp ) == 0 ):
			$final['room_list'] = 'No rooms for this branch';
		else:
			$final['room_list'] = implode( "\r\n", $temp );
		endif;
		
		
		
		
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		{
			echo json_encode( $final );
		} else {
			echo "Error!";
		}
		die();
	}
	
	protected static function editAttendance( $externals )
	{
		global $wpdb;
		
		if( empty( $externals['attCount'] ) ):
			$attCount = 'NULL';
		else:
			$attCount = "'{$externals['attCount']}'";
		endif;
		
		$sql = "UPDATE `{$wpdb->prefix}bookaroom_times` SET `ti_attendance` = {$attCount}, `ti_attNotes` = '{$externals['attNotes']}'
				WHERE `ti_id` = '{$externals['eventID']}' LIMIT 1";
		$wpdb->query( $sql );
		
		return true;
	}
	
	protected static function fixSavedEventInfo( $eventInfo, $externals )
	{
		$goodArr = array( 

'roomID' => 'ti_roomID', 
'res_id' => 'ti_extID',  
'amenity' => 'ev_amenity',  
'endTime' => 'ti_endTime',  
'eventDesc' => 'ev_desc',  
'eventTitle' => 'ev_title',  
'maxReg' => 'ev_maxReg',  
'presenter' => 'ev_presenter',  
'privateNotes' => 'ev_privateNotes',  
'publicName' => 'ev_publicName',  
'publicPhone' => 'ev_publicPhone',  
'publicEmail' => 'ev_publicEmail',  
'doNotPublish' => 'ev_noPublish',  
'yourName' => 'ev_submitter',  
'recurrence' => '',  
'waitingList' => 'ev_waitingList',  
'website' => 'ev_website',  
'websiteText' => 'ev_webText', 
'registration' => 'ev_regType', 
'ageGroup' => 'ageGroup', 
'category' => 'category', 
'noLocation_branch' => 'ti_noLocation_branch', 
 );
		foreach( $goodArr as $key => $val ):

			if( !empty( $eventInfo[$val] ) ):
				$externals[$key] = $eventInfo[$val];
			endif;
		endforeach;
		
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$externals['roomID'] =  'noloc-'.$eventInfo['ti_noLocation_branch'];
		endif;
		#***


		$startTime	= date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) );
		$endTime	= date( 'g:i a', strtotime( $eventInfo['ti_endTime'] ) );
		
		if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
			$externals['allDay'] = true;
			$externals['startTime'] = false;
			$externals['endTime'] = false;
		else:
			$externals['allDay'] = false;
			$externals['startTime'] = $startTime;
			$externals['endTime'] = $endTime;
		endif;
		
		
		$externals['eventStart'] =  date( 'm/d/Y', strtotime( $eventInfo['ti_startTime'] ) );
		switch( $eventInfo['ev_regType'] ):
			case 'staff':
				$regType = 'staff';
				break;
			case 'yes':
				$regType = 'yes';
				break;
			default:
				$regType = 'no';
				break;
		endswitch;
	
		$externals['registration'] =  $regType;
		$externals['regDate'] =  date( 'm/d/Y', strtotime( $eventInfo['ev_regStartDate'] ) );
		
		if( !empty( $eventInfo['ev_amenity'] ) ):
			$externals['amenity'] = unserialize( $eventInfo['ev_amenity'] );
		endif;
		
		return $externals;	
	}
	protected static function getDates_addDates()
	{
		if( $_SESSION['bookaroom_meetings_externalVals']['allDay'] == 'true' ):
			$startTime = '12:00 am';
			$endTime = '11:59:59 pm';
		else:
			$startTime = $_SESSION['bookaroom_meetings_externalVals']['startTime'];
			$endTime = $_SESSION['bookaroom_meetings_externalVals']['endTime'];
		endif;		
		$startDate = $_SESSION['bookaroom_meetings_externalVals']['eventStart'];			
		$addDateVals = $_SESSION['bookaroom_meetings_externalVals']['addDateVals'];
		
		array_unshift( $addDateVals, $startDate );
		
		
		foreach( $addDateVals as $val ):

			$mainStart = strtotime( $val.' '.$startTime );
			$mainEnd = strtotime( $val.' '.$endTime );
			
			$final[] = array( 'start' => $mainStart, 'end' => $mainEnd );
		endforeach;
		
		return $final;
	}
	
	protected static function getDates_daily()
	{
		# find Occurrence type
		
		$startDate = $_SESSION['bookaroom_meetings_externalVals']['eventStart'];
		$offset = $_SESSION['bookaroom_meetings_externalVals']['dailyEveryNDaysVal'];
		$Occurrence = $_SESSION['bookaroom_meetings_externalVals']['daily_Occurrence'];
		
		if( $_SESSION['bookaroom_meetings_externalVals']['allDay'] == 'true' ):
			$startTime = '12:00 am';
			$endTime = '11:59:59 pm';
		else:
			$startTime = $_SESSION['bookaroom_meetings_externalVals']['startTime'];
			$endTime = $_SESSION['bookaroom_meetings_externalVals']['endTime'];
		endif;
		
		$mainStart = getdate( strtotime( $startDate.' '.$startTime ) );
		$mainEnd = getdate( strtotime( $startDate.' '.$endTime ) );
		
		$final = array();
		$niceFinal = array();

		switch( $_SESSION['bookaroom_meetings_externalVals']['dailyType'] ):
			case 'everyNDays':
				# get start date
				switch( $_SESSION['bookaroom_meetings_externalVals']['dailyEndType']):
				
					case 'Occurrences':
						for( $t=0; $t < $Occurrence; $t++):
							$start = mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + ( $t * $offset ), $mainStart['year'] );
							$end = mktime( $mainEnd['hours'], $mainEnd['minutes'], $mainEnd['seconds'], $mainEnd['mon'], $mainEnd['mday'] + ( $t * $offset ), $mainEnd['year'] );
							$final[] = array( 'start' => $start, 'end' => $end );
							
							$niceStart = date( 'l, m/d/y g:i a', mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + ( $t * $offset ), $mainStart['year'] ) );
							$niceEnd = date( 'l, m/d/y g:i a', mktime( $mainEnd['hours'], $mainEnd['minutes'], $mainEnd['seconds'], $mainEnd['mon'], $mainEnd['mday'] + ( $t * $offset ), $mainEnd['year'] ) );
							$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
						endfor;
						break;
					case 'endBy':
						$curDate = strtotime( $startDate );
						
						$endDate =  strtotime( $_SESSION['bookaroom_meetings_externalVals']['daily_endBy'] );
					
						$check = 1;
						while( $curDate <= $endDate ):

							$curOffset = $offset * $check;
							
							$curDateNice = date( 'm/d/y', $curDate );
							
							$start = strtotime( $curDateNice.' '.$startTime );

							$end = strtotime( $curDateNice.' '.$endTime );
							$final[] = array( 'start' => $start, 'end' => $end );
							
							$niceStart = date( 'l, m/d/y g:i a', strtotime( $curDateNice.' '.$startTime ) );
							$niceEnd = date( 'l, m/d/y g:i a', strtotime( $curDateNice.' '.$endTime ) );
							$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
														
							
							$curDate = mktime( 0, 0, 0, $mainStart['mon'], $mainStart['mday'] + $curOffset, $mainStart['year'] );
							

							if( $check++ > 600) die('error: daily endby loop');
						endwhile;
						
						break;
					default:
						die( 'error: wrong daily end by' );
						break;
					endswitch;
				break;
			case 'weekdays':
				switch( $_SESSION['bookaroom_meetings_externalVals']['dailyEndType']):
				
					case 'Occurrences':
						$count = 0;
						$check = 0;
						while( $count < $Occurrence ):
			
							#$curDate
							$curDate =  mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );
							$curDOW = date( 'w', $curDate );
		
							if( $curDOW > 0 and $curDOW < 6):
								$start = mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );
								$end = mktime( $mainEnd['hours'], $mainEnd['minutes'], $mainEnd['seconds'], $mainEnd['mon'], $mainEnd['mday'] + $check, $mainEnd['year'] );
								$final[] = array( 'start' => $start, 'end' => $end );
								
								$niceStart = date( 'l, m/d/y g:i a', mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] ) );
								$niceEnd = date( 'l, m/d/y g:i a', mktime( $mainEnd['hours'], $mainEnd['minutes'], $mainEnd['seconds'], $mainEnd['mon'], $mainEnd['mday'] + $check, $mainEnd['year'] ) );
								$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
								$count++;
							endif;
							
							if( $check++ > 100) die( 'error: too many loops' );
						endwhile;
						break;
					case 'endBy':
						$curDate = strtotime( $startDate );						
						$endDate =  strtotime( $_SESSION['bookaroom_meetings_externalVals']['daily_endBy'] );
					
						$check = 1;
						
						while( $curDate <= $endDate ):
							$curDateNice = date( 'm/d/y', $curDate );
							
							$curDOW = date( 'w', $curDate );
							
							if( $curDOW > 0 and $curDOW < 6 ):
								$start = strtotime( $curDateNice.' '.$startTime );
	
								$end = strtotime( $curDateNice.' '.$endTime );
								$final[] = array( 'start' => $start, 'end' => $end );
								
								$niceStart = date( 'l, m/d/y g:i a', strtotime( $curDateNice.' '.$startTime ) );
								$niceEnd = date( 'l, m/d/y g:i a', strtotime( $curDateNice.' '.$endTime ) );
								$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
							endif;							
							
							$curDate = mktime( 0, 0, 0, $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );
							if( $check++ > 100) die('error: daily endby loop');
						endwhile;

						break;
					endswitch;
				break;
			case 'weekends':
				switch( $_SESSION['bookaroom_meetings_externalVals']['dailyEndType']):
				
					case 'Occurrences':
						$count = 0;
						$check = 0;
						while( $count < $Occurrence ):
			
							#$curDate
							$curDate =  mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );
							$curDOW = date( 'w', $curDate );
		
							if( $curDOW == 0 or $curDOW == 6):
								$start = mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );
								$end = mktime( $mainEnd['hours'], $mainEnd['minutes'], $mainEnd['seconds'], $mainEnd['mon'], $mainEnd['mday'] + $check, $mainEnd['year'] );
								$final[] = array( 'start' => $start, 'end' => $end );
								
								$niceStart = date( 'l, m/d/y g:i a', mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] ) );
								$niceEnd = date( 'l, m/d/y g:i a', mktime( $mainEnd['hours'], $mainEnd['minutes'], $mainEnd['seconds'], $mainEnd['mon'], $mainEnd['mday'] + $check, $mainEnd['year'] ) );
								$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
								$count++;
							endif;
							
							if( $check++ > 100) die( 'error: too many loops' );
						endwhile;
						break;
					case 'endBy':
						$curDate = strtotime( $startDate );						
						$endDate =  strtotime( $_SESSION['bookaroom_meetings_externalVals']['daily_endBy'] );
					
						$check = 1;
						
						while( $curDate <= $endDate ):
							$curDateNice = date( 'm/d/y', $curDate );
							
							$curDOW = date( 'w', $curDate );
							
							if( $curDOW == 0 or $curDOW == 6 ):
								$start = strtotime( $curDateNice.' '.$startTime );
	
								$end = strtotime( $curDateNice.' '.$endTime );
								$final[] = array( 'start' => $start, 'end' => $end );
								
								$niceStart = date( 'l, m/d/y g:i a', strtotime( $curDateNice.' '.$startTime ) );
								$niceEnd = date( 'l, m/d/y g:i a', strtotime( $curDateNice.' '.$endTime ) );
								$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
							endif;							
							
							$curDate = mktime( 0, 0, 0, $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );
							if( $check++ > 100) die('error: daily endby loop');
						endwhile;

						break;
					endswitch;
				break;
			default:
				die( 'error: wrong daily type' );
				break;
		endswitch;
		
		return $final;
	}
	
	protected static function getDates_single()
	{
		$startDate = $_SESSION['bookaroom_meetings_externalVals']['eventStart'];
		if( $_SESSION['bookaroom_meetings_externalVals']['allDay'] == 'true' ):
			$startTime = '12:00 am';
			$endTime = '11:59:59 pm';
		else:
			$startTime = $_SESSION['bookaroom_meetings_externalVals']['startTime'];
			$endTime = $_SESSION['bookaroom_meetings_externalVals']['endTime'];
		endif;
		
		$mainStart = strtotime( $startDate.' '.$startTime );
		$mainEnd = strtotime( $startDate.' '.$endTime );
		
		$final[] = array( 'start' => $mainStart, 'end' => $mainEnd );
		
		return $final;
	}
	
	protected static function getDates_weekly()
	{
		# find Occurrence type
		$startDate = $_SESSION['bookaroom_meetings_externalVals']['eventStart'];
		$offset = $_SESSION['bookaroom_meetings_externalVals']['everyNWeeks'];
		$Occurrence = $_SESSION['bookaroom_meetings_externalVals']['weekly_Occurrence'];
		$weeklyDayArr = $_SESSION['bookaroom_meetings_externalVals']['weeklyDay'];
		
		if( $_SESSION['bookaroom_meetings_externalVals']['allDay'] == 'true' ):
			$startTime = '12:00 am';
			$endTime = '11:59:59 pm';
		else:
			$startTime = $_SESSION['bookaroom_meetings_externalVals']['startTime'];
			$endTime = $_SESSION['bookaroom_meetings_externalVals']['endTime'];
		endif;
		
		$mainStart = getdate( strtotime( $startDate.' '.$startTime ) );
		$mainEnd = getdate( strtotime( $startDate.' '.$endTime ) );
		
		$daysArr = array( 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		
		$final = array();
		$niceFinal = array();

		switch( $_SESSION['bookaroom_meetings_externalVals']['weeklyEndType'] ):
			case 'Occurrences':
				$check = 0;
				$count = 0;
				
				# make decent date array
				
				
				while( $count <= $Occurrence ):
					$curDate = mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );

					if( in_array( $daysArr[date( 'w', $curDate )], $_SESSION['bookaroom_meetings_externalVals']['weeklyDay'] ) ):

						$start = strtotime( date( 'm/d/y', $curDate ).' '.$startTime );
						$end = strtotime( date( 'm/d/y', $curDate ).' '.$endTime );
						
						$niceStart = date( 'm/d/h g:i a', $start );
						$niceEnd = date( 'm/d/h g:i a', $end );
						
						$final[] = array( 'start' => $start, 'end' => $end );
						$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
						$count++;
					endif;
					
					if( !empty( $offset ) and date( 'w', $curDate ) == 6 ):
						$check += 7 * ($offset-1);
					endif;
					
					if( $check++ > 500 ) die( 'weekly Occurrence loop' );
				endwhile;
				
				break;
				
			case 'endBy':
				$curDate = strtotime( $startDate );						
				$endDate =  strtotime( $_SESSION['bookaroom_meetings_externalVals']['weekly_endBy'] );
				
				$check = 0;
		
			
				while( $curDate <= $endDate ):
					$curDateNice = date( 'm/d/y', $curDate );
					$curDOW = date( 'w', $curDate );
					
					
					$curDate = mktime( $mainStart['hours'], $mainStart['minutes'], $mainStart['seconds'], $mainStart['mon'], $mainStart['mday'] + $check, $mainStart['year'] );

					if( in_array( $daysArr[date( 'w', $curDate )], $_SESSION['bookaroom_meetings_externalVals']['weeklyDay'] ) ):
						$start = strtotime( date( 'm/d/y', $curDate ).' '.$startTime );
						$end = strtotime( date( 'm/d/y', $curDate ).' '.$endTime );
						
						$niceStart = date( 'm/d/h g:i a', $start );
						$niceEnd = date( 'm/d/h g:i a', $end );
						
						$final[] = array( 'start' => $start, 'end' => $end );
						$niceFinal[] = array( 'start' => $niceStart, 'end' => $niceEnd );
					endif;
					
					if( !empty( $offset ) and date( 'w', $curDate ) == 6 ):
						$check += 7 * ($offset-1);
					endif;
					if( $check++ > 500 ) die( 'weekly Occurrence loop' );
				endwhile;
				break;
					
			default: 
				die( 'error: unknown weeklyEndType' );
				break;
		endswitch;
		
		return $final;
		
	}

	public static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'					=> FILTER_SANITIZE_STRING, 
							'eventID'					=> FILTER_SANITIZE_STRING, 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'branchID'					=> FILTER_SANITIZE_STRING,
							'res_id'					=> FILTER_SANITIZE_STRING,
							
							'endDate'					=> FILTER_SANITIZE_STRING, 
							'published'					=> FILTER_SANITIZE_STRING, 
							'searchTerms'				=> FILTER_SANITIZE_STRING, 
							'startDate'					=> FILTER_SANITIZE_STRING,
							'time'						=> FILTER_SANITIZE_STRING,
							'hash'						=> FILTER_SANITIZE_STRING,
							'regID'						=> FILTER_SANITIZE_STRING,
							);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final = array_merge( $final, $getTemp );
		endif;
		
		# setup POST variables
		$postArr = array(	
							'action'					=> FILTER_SANITIZE_STRING,
							'allDay'					=> FILTER_SANITIZE_STRING, 
							'addDateVals'				=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'ageGroup'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'amenity'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'attCount'					=> FILTER_SANITIZE_STRING, 
							'attNotes'					=> FILTER_SANITIZE_STRING, 
							'branchID'					=> FILTER_SANITIZE_STRING, 
							'category'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'daily_endBy'				=> FILTER_SANITIZE_STRING, 
							'daily_Occurrence'			=> FILTER_SANITIZE_STRING,							
							'dailyEndType'				=> FILTER_SANITIZE_STRING, 
							'dailyEveryNDaysVal'		=> FILTER_SANITIZE_STRING, 
							'dailyType'					=> FILTER_SANITIZE_STRING, 
							'delete'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'endTime'					=> FILTER_SANITIZE_STRING, 
							'eventID'					=> FILTER_SANITIZE_STRING, 
							'eventDesc'					=> FILTER_SANITIZE_STRING, 
							'eventStart'				=> FILTER_SANITIZE_STRING, 
							'eventTitle'				=> FILTER_SANITIZE_STRING, 
							'everyNWeeks'				=> FILTER_SANITIZE_STRING, 
							'extraInfo'					=> FILTER_SANITIZE_STRING, 
							'instance'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'hash'						=> FILTER_SANITIZE_STRING, 
							'maxReg'					=> FILTER_SANITIZE_STRING, 
							'newVal'					=> array(	'filter'    => FILTER_SANITIZE_STRING,
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'presenter'					=> FILTER_SANITIZE_STRING, 
							'privateNotes'				=> FILTER_SANITIZE_STRING, 
							'publicName'				=> FILTER_SANITIZE_STRING, 
							'publicPhone'				=> FILTER_SANITIZE_STRING, 
							'publicEmail'				=> FILTER_SANITIZE_STRING, 
							'regDate'					=> FILTER_SANITIZE_STRING, 
							'regID'						=> FILTER_SANITIZE_STRING, 
							'registration'				=> FILTER_SANITIZE_STRING, 
							'doNotPublish'				=> FILTER_SANITIZE_STRING, 
							'yourName'					=> FILTER_SANITIZE_STRING, 
							'recurrence'				=> FILTER_SANITIZE_STRING, 
							'roomID'					=> FILTER_SANITIZE_STRING, 
							'startTime'					=> FILTER_SANITIZE_STRING, 
							'hashTime'					=> FILTER_SANITIZE_STRING, 
							'submit'					=> FILTER_SANITIZE_STRING, 
							'waitingList'				=> FILTER_SANITIZE_STRING, 
							'website'					=> FILTER_SANITIZE_STRING, 
							'websiteText'				=> FILTER_SANITIZE_STRING, 
							'weekly_endBy'				=> FILTER_SANITIZE_STRING, 
							'weekly_Occurrence'			=> FILTER_SANITIZE_STRING,
							'weeklyDay'					=> array(	'filter'    => FILTER_SANITIZE_STRING, 
																	'flags'     => FILTER_REQUIRE_ARRAY ), 
							'weeklyEndType'				=> FILTER_SANITIZE_STRING,
							
							'regName'					=> FILTER_SANITIZE_STRING,
							'regPhone'					=> FILTER_SANITIZE_STRING,
							'regNotes'					=> FILTER_SANITIZE_STRING,
							'regEmail'					=> FILTER_SANITIZE_STRING, 
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
		
		return $final;
	}
	
	protected static function getInstances( $res_id )
	{
		global $wpdb;

		$table_name = $wpdb->prefix . "bookaroom_times";
				
		$sql = "SELECT `ti_id`, `ti_extID`, `ti_startTime`, `ti_endTime`, `ti_roomID`, `ti_noLocation_branch` 
				FROM `{$table_name}` WHERE `ti_extID` = '{$res_id}'
				ORDER BY `ti_startTime`";

		$cooked = ( $wpdb->get_results( $sql, ARRAY_A ) );
		
		return $cooked;
	}
	
	protected static function getRegistrations( $eventID )
	{
		global $wpdb;
		
		$sql = "SELECT `reg_id`, `reg_fullName`, `reg_phone`, `reg_email`, `reg_notes`, `reg_dateReg` 
		FROM `{$wpdb->prefix}bookaroom_registrations` 
		WHERE `reg_eventID` = '{$eventID}' 
		ORDER BY `reg_dateReg` ASC";
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		$final = array();
		if( !is_array( $cooked ) ) return $final;
		
		foreach( $cooked as $key => $val ):
			$final[$val['reg_id']] = $val;
		endforeach;
		
		return $final;
	}
	
	protected static function getRoomContListByRoomID( $roomID, $roomContList, $allRoomList )
	{		
		if( substr( $roomID, 0, 6 ) == 'noloc-' ):
			return array( 'finalRoomConts' => array(), 'roomContList' => array() );
		endif;
		
		global $wpdb;		
		
		
		# Error correcting for missing rooms in containers.
		$finalRoomConts = array();
				
		if( empty( $roomContList['id'][$roomID]['rooms'] ) ):
			$roomArr = array();
		else:
			$roomArr = $roomContList['id'][$roomID]['rooms'];
			$roomList = implode( ',', $roomArr );		

			$table_name = $wpdb->prefix . "bookaroom_roomConts_members";

			$sql = "SELECT `rcm_roomContID` as `roomID` FROM `{$table_name}` WHERE `rcm_roomID` in ({$roomList})";
		
			$cooked = ( $wpdb->get_results( $sql, ARRAY_A ) );

			foreach( $cooked as $val ):
				$finalRoomConts[] = $val['roomID'];
			endforeach;
		endif;

				

		
		$finalRoomConts = array_unique( $finalRoomConts );
		$roomContList = implode( ',', $finalRoomConts );
		
		return array( 'finalRoomConts' => $finalRoomConts, 'roomContList' => $roomContList );
	}
	
	protected static function makeBranchList( &$branchID, &$roomID, &$contents, $branchList, $roomContList )
	{

		$branchID = self::branch_and_room_id( $roomID, $branchList, $roomContList );
		
		# branch lines
		$branch_line		= repCon( 'branch', $contents );
		$branchSel_line		= repCon( 'branchSel', $contents, true );
		$branchNone_line	= repCon( 'branchNone', $contents, true );

		# branch list
		if( count( $branchList ) == 0 ):
			$contents = str_replace( '#branch_line#', $branchNone_line, $contents );
		else:
			$temp = NULL;
			$final = array();
			foreach( $branchList as $key => $val ):
				if( $key == $branchID ):
					$temp = $branchSel_line;
				else:
					$temp = $branch_line;
				endif;
				
				$temp = str_replace( '#branchID#', $key, $temp );
				$temp = str_replace( '#branchDesc#', $val['branchDesc'], $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#branch_line#', implode( "\r\n", $final ), $contents );
		endif;	
			
		# room lines
		$room_line			= repCon( 'room', $contents );
		$roomSel_line		= repCon( 'roomSel', $contents, true );
		$roomNone_line		= repCon( 'roomNone', $contents, true );
		$roomBranch_line	= repCon( 'roomBranch', $contents, true );
		# room list
		$temp = NULL;
		$final = array();
		
		# no branch selected
		if( empty( $branchID ) ):
			$contents = str_replace( '#room_line#', $roomBranch_line, $contents );
		elseif( empty( $roomContList['branch'][$branchID] ) ):
			$contents = str_replace( '#room_line#', $roomNone_line, $contents );
		else:

			
			# regular rooms
						
			foreach( $roomContList['branch'][$branchID] as $key => $val ):
				if( $val == $roomID ):
					$temp = $roomSel_line;
				else:
					$temp = $room_line;
				endif;
			
				$temp = str_replace( '#roomID#', $val, $temp );
				$temp = str_replace( '#roomDesc#', $roomContList['id'][$val]['desc'], $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#room_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		$contents = str_replace( '#branchID#', $branchID, $contents );
		$contents = str_replace( '#roomID#', $roomID, $contents );
		
	}
	
	protected static function makeClosingRoomList( $closedRooms, $allRooms, $branchList, $roomContList, $roomList )
	{
		if( $allRooms == true ):
			return 'All branches, all rooms.';
		endif;
		
		$display = NULL;
		
		# cycle each branch, find if an entire branch is closed.
		$display = array();
		
		foreach( $branchList as $bKey => $bVal ):
			
			$branchRooms = array();
					
			if( empty( $roomContList['branch'][$bKey] ) or count( $roomContList['branch'][$bKey] ) == 0 ):
				continue;
			endif;
			
			foreach( $roomContList['branch'][$bKey] as $rKey => $rVal ):
				$branchRooms = array_merge( $branchRooms, $roomContList['id'][$rVal]['rooms'] );
			endforeach;
			$branchRooms = array_unique( $branchRooms );

			if( false == array_diff( $branchRooms, $closedRooms ) ):
				$display[] = '<strong>'.$bVal['branchDesc'].'</strong><br />Branch closed';

			elseif( count( $roomsClosedList = array_intersect( $branchRooms, $closedRooms ) ) !== 0 ):
				$header = '<strong>'.$bVal['branchDesc'].'</strong><br />';
				
				$roomsFinal = array();
				foreach( $roomsClosedList as $rcKey ):
					$roomsFinal[] = $roomList['id'][$rcKey]['desc'];
				endforeach;
				$dispTemp = implode( ', ', $roomsFinal );
				$display[] = $header.$dispTemp;
			endif;
			# TODO empty roomContList for that branch
		endforeach;
		
		$final = implode( "<br /><br />", $display );

		return $final;		
	}

	protected static function makeConflictDropDown( $conflictList, $branchList, $roomContList, $roomList )
	{
		$final = array();
		
		foreach( $branchList as $bKey => $branchVal ):
			$temp = array();
			$temp['display'] = $branchVal['branchDesc'];
			$temp['class'] = 'dropHeader';
			$temp['disabled'] = true;
			$temp['roomID'] = NULL;
			$final[] = $temp;
			
			$temp = array();
			# if no rooms
			if( empty( $roomContList['branch'][$bKey] ) ):
				$temp['display'] = 'No rooms in this branch.';
				$temp['class'] = 'nudgeRight';
				$temp['disabled'] = true;
				$temp['roomID'] = NULL;
				$final[] = $temp;
			else:			
				foreach( $roomContList['branch'][$bKey] as $rKey):
					$temp = array();
					$temp['display'] = $roomContList['id'][$rKey]['desc'];
					$temp['class'] = 'nudgeRight';
					$temp['roomID'] = $rKey;
					if( array_intersect( $roomContList['id'][$rKey]['rooms'], $conflictList ) ):
						$temp['disabled'] = true;
					else:
						$temp['disabled'] = false;					
					endif;
					$final[] = $temp;
				endforeach;
			endif;

		endforeach;
		
		return $final;
	}
	
	protected static function manageRegistrations( $eventInfo, $branchList, $roomContList )
	{
		global $wpdb;
				
		$filename = BOOKAROOM_PATH . 'templates/events/showRegistrations.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# event info
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		$contents = str_replace( '#title#', $eventInfo['ev_title'], $contents );
		$contents = str_replace( '#desc#', $eventInfo['ev_desc'], $contents );
		$contents = str_replace( '#date#', date('l, F jS, Y', strtotime( $eventInfo['ti_startTime'] ) ), $contents );
		
		# branch and room
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$contents = str_replace( '#branch#', $branchList[$eventInfo['ti_noLocation_branch']]['branchDesc'], $contents );
			$contents = str_replace( '#room#', 'No location required', $contents );
		else:
			$contents = str_replace( '#room#', $roomContList['id'][$eventInfo['ti_roomID']]['desc'], $contents );
			$contents = str_replace( '#branch#', $branchList[$roomContList['id'][$eventInfo['ti_roomID']]['branchID']]['branchDesc'], $contents );
		endif;
		# all day?
		$startTime = date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) );
		$endTime = date( 'g:i a', strtotime( $eventInfo['ti_endTime'] ) );
		
		if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
			$contents = str_replace( '#time#', 'All Day', $contents );		
		else:		
			$contents = str_replace( '#time#', date('g:i a', strtotime( $eventInfo['ti_startTime'] ) ).' -'.date('g:i a', strtotime( $eventInfo['ti_endTime'] ) ), $contents );
		endif;
		
		$registrations = self::getRegistrations( $eventInfo['ti_id'] );
		
		$reg_line		= repCon( 'reg', $contents );
		$regEmail_line	= repCon( 'regEmail', $reg_line );
		$noReg_line		= repCon( 'noReg', $contents, true );
		
		if( count( $registrations ) == 0 ):
			$contents = str_replace( '#reg_line#', $noReg_line, $contents );			
		else:
			$final = array();
			
			foreach( array_slice( $registrations, 0, $eventInfo['ev_maxReg'] ) as $key => $val ):
				$temp = $reg_line;
				
				$temp = str_replace( '#regName#', $val['reg_fullName'], $temp );
				$temp = str_replace( '#regID#', $val['reg_id'], $temp );
				
				$temp = str_replace( '#regPhone#', $val['reg_phone'], $temp );
				$temp = str_replace( '#regNotes#', $val['reg_notes'], $temp );
								
				$temp = str_replace( '#regTime#', date('g:i:s a', strtotime( $val['reg_dateReg'] ) ), $temp );
				$temp = str_replace( '#regDate#', date('m/d/Y', strtotime( $val['reg_dateReg'] ) ), $temp );
				
				
				if( empty( $val['reg_email'] ) ):
					$temp = str_replace( '#regEmail_line#', NULL, $temp );				
				else:
					$temp = str_replace( '#regEmail_line#', $regEmail_line, $temp );
					$temp = str_replace( '#regEmail#', $val['reg_email'], $temp );
					
				endif;
				
				$final[] = $temp;
			endforeach;
			
			$contents  = str_replace( '#reg_line#', implode( "\r\n", $final), $contents );
			
		endif;
		

		# waiting List
		$waitList_line			= repCon( 'waitList', $contents );
		$waitListEmail_line		= repCon( 'waitListEmail', $waitList_line );
		$noWaitList_line		= repCon( 'noWaitList', $contents, true );
		
		if( count( array_slice( $registrations, $eventInfo['ev_maxReg'] ) ) == 0 ):
			$contents = str_replace( '#waitList_line#', $noWaitList_line, $contents );			
		else:
			$final = array();
			
			foreach( array_slice( $registrations, $eventInfo['ev_maxReg'] ) as $key => $val ):
				$temp = $waitList_line;
				
				$temp = str_replace( '#waitListName#', $val['reg_fullName'], $temp );
				$temp = str_replace( '#waitListID#', $val['reg_id'], $temp );
				
				$temp = str_replace( '#waitListPhone#', $val['reg_phone'], $temp );
				$temp = str_replace( '#waitListNotes#', $val['reg_notes'], $temp );
				

				if( empty( $val['reg_email'] ) ):
					$temp = str_replace( '#waitListEmail_line#', NULL, $temp );				
				else:
					$temp = str_replace( '#waitListEmail_line#', $waitListEmail_line, $temp );
					$temp = str_replace( '#waitListEmail#', $val['reg_email'], $temp );
					
				endif;
								
				$temp = str_replace( '#waitListTime#', date('g:i:s a', strtotime( $val['reg_dateReg'] ) ), $temp );
				$temp = str_replace( '#waitListDate#', date('m/d/Y', strtotime( $val['reg_dateReg'] ) ), $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents  = str_replace( '#waitList_line#', implode( "\r\n", $final), $contents );
			
		endif;
		
		
				
		echo $contents;
	}
	
	protected static function NaN( $val )
	{
		
		if( !is_numeric( $val ) || $val < 1 || !filter_var($val , FILTER_VALIDATE_INT) ):
			return true;
		else:
			return false;
		endif;
	}
	
	protected static function showAttendance( $eventInfo, $externals, $attCount = NULL, $attNotes = NULL, $errorMSG = NULL )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
				
		$filename = BOOKAROOM_PATH . 'templates/events/showAttendance.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		$contents = str_replace( '#attCount#', $attCount, $contents );
		$contents = str_replace( '#attNotes#', $attNotes, $contents );
		
		# error
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );	
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		endif;
		
		# all day?
		$startTime = date( 'g:i a', strtotime( $eventInfo['ti_startTime'] ) );
		$endTime = date( 'g:i a', strtotime( $eventInfo['ti_endTime'] ) );
		
		if( $startTime == '12:00 am' and $endTime == '11:59 pm' ):
			$contents = str_replace( '#time#', 'All Day', $contents );		
		else:		
			$contents = str_replace( '#time#', date('g:i a', strtotime( $eventInfo['ti_startTime'] ) ).' -'.date('g:i a', strtotime( $eventInfo['ti_endTime'] ) ), $contents );
		endif;

		$contents = str_replace( '#eventID#', $eventInfo['ti_id'], $contents );
		$contents = str_replace( '#title#', $eventInfo['ev_title'], $contents );
		$contents = str_replace( '#desc#', $eventInfo['ev_desc'], $contents );
		$contents = str_replace( '#date#', date('l, F jS, Y', strtotime( $eventInfo['ti_startTime'] ) ), $contents );

		$registrations = self::getRegistrations( $eventInfo['ti_id'] );
		
		$contents = str_replace( '#registered#', count( $registrations ), $contents );
		$contents = str_replace( '#registeredTotal#', $eventInfo['ev_maxReg'], $contents );
		
		# branch and room
		if( !empty( $eventInfo['ti_noLocation_branch'] ) ):
			$contents = str_replace( '#branch#', $branchList[$eventInfo['ti_noLocation_branch']]['branchDesc'], $contents );
			$contents = str_replace( '#room#', 'No location required', $contents );
		else:
			$contents = str_replace( '#room#', $roomContList['id'][$eventInfo['ti_roomID']]['desc'], $contents );
			$contents = str_replace( '#branch#', $branchList[$roomContList['id'][$eventInfo['ti_roomID']]['branchID']]['branchDesc'], $contents );
		endif;
				
		echo $contents;
	}

	protected static function showConflicts( $sessionVars, $dateList, $delete = array() )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
				
		$filename = BOOKAROOM_PATH . 'templates/events/showConflicts.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		# get line
		$good_line = repCon( 'good', $contents );
		$conflict_line = repCon( 'conflict', $contents, TRUE );
		$rowSpan_line = repCon( 'rowSpan', $conflict_line );
		$extra_line = repCon( 'extra', $contents, TRUE );
		$deleted_line = repCon( 'deleted', $contents, TRUE );
		
		# drop down line
		$dropList_line = repCon( 'dropList', $conflict_line );
		
		$final = array();
		
		foreach( $dateList as $key => $val ):
			
			# is conflict
			if( !empty( $val['conflicts'] ) ):
				
				$rowCount = count( $val['conflicts'] );
				$firstRow = true;
				# find open rooms
				
				if( empty( $val['usedRoomsClosings'] ) ):
					$val['usedRoomsClosings'] = array();
				endif;
				
				$usedRoomsList = array_merge( $val['usedRooms'], $val['usedRoomsClosings'] );
				
				$dropList = self::makeConflictDropDown( $usedRoomsList, $branchList, $roomContList, $roomList );
				
				$dlTemp = NULL;
				$dlFinal = array();
				
				#pree( $dropList );
				
				foreach( $dropList as $dlKey => $dlVal ):
					$dlTemp = $dropList_line;

					$dlTemp = str_replace( '#dropList_desc#', $dlVal['display'], $dlTemp );
					
					if( $sessionVars['roomID'] == $dlVal['roomID'] ):
						$selected = ' selected="selected"';
					else:
						$selected = NULL;
					endif;
					
					$dlTemp = str_replace( '#dropList_selected#', $selected, $dlTemp );
					
					# disabled
					if( true == $dlVal['disabled'] ):
						$disabled = ' disabled';
					else:
						$disabled = NULL;
					endif;
					
					$dlTemp = str_replace( '#dropList_disabled#', $disabled, $dlTemp );

					$dlTemp = str_replace( '#dropList_val#', $dlVal['roomID'], $dlTemp );
					
					$dlTemp = str_replace( '#dropList_class#', $dlVal['class'], $dlTemp );
					
					
					$dlFinal[] = $dlTemp;
				endforeach;
				
				foreach( $val['conflicts'] as $cat => $mouse ):
					if( $firstRow ):
						$temp = $conflict_line;
						$firstRow = false;
						$temp = str_replace( '#rowSpan_line#', $rowSpan_line, $temp );
						$temp = str_replace( '#rowspan#', $rowCount, $temp );
						
						$temp = str_replace( '#dropList_line#', implode( "\r\n", $dlFinal ), $temp );
						
					else:
						$temp = $extra_line;
						$temp = str_replace( '#rowSpan_line#', NULL, $temp );
					endif;
					
					$timeStamp = strtotime( $mouse['startTime'] );
					
					$temp = str_replace( '#date#', date( 'm/d/y', $timeStamp), $temp );
					$temp = str_replace( '#type#', $mouse['type'], $temp );
					$temp = str_replace( '#desc#', $mouse['desc'], $temp );
					
					if( !empty( $mouse['allDay'] ) ):
						$temp = str_replace( '#time#', 'All Day', $temp );
					else:
						$temp = str_replace( '#time#', date( 'g:i a', $timeStamp ). ' - ' .date( 'g:i a', strtotime( $mouse['endTime'] ) ), $temp );
					endif;
					
					if( is_array( $mouse['roomID'] ) ):
						$temp = str_replace( '#location#', self::makeClosingRoomList( $mouse['roomID'], $mouse['allClosed'], $branchList, $roomContList, $roomList ), $temp );
					else:
						$room = $roomContList['id'][$mouse['roomID']]['desc'];
						$branch = $branchList[$roomContList['id'][$mouse['roomID']]['branchID']]['branchDesc'];
						$temp = str_replace( '#location#', "<strong>{$branch}</strong><br />{$room}", $temp );
						$temp = str_replace( '#room#', $room, $temp );
					endif;

					$final[] = $temp;


				endforeach;
			elseif( !empty( $delete ) and array_search( date( 'm/d/y', $val['start'] ), $delete )  ):
				$temp = $deleted_line;
				$temp = str_replace( '#date#', date( 'm/d/y', $val['start'] ), $temp );
				$temp = str_replace( '#date_checked#', ' checked="checked"', $temp );
				$final[] = $temp;
			else:
				#non conflict line
				$temp = $good_line;
				$temp = str_replace( '#date#', date( 'm/d/y', $val['start'] ), $temp );
				$final[] = $temp;
			endif;
			
		endforeach;
		
		$contents = str_replace( '#good_line#', implode( "\r\n", $final ), $contents );
		$contents = str_replace( '#recurrence#', $sessionVars['recurrence'], $contents );
		
		echo $contents;
	
	}
	
/*
	protected static function showDeleteInstance( $externals )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		$filename = BOOKAROOM_PATH . 'templates/events/delete_instance.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#eventID#', $externals['eventID'], $contents );
		
		# common values
		$goodArr = array( 'endTime','eventDesc','eventStart','eventTitle','everyNWeeks','maxReg','waitingList','presenter','privateNotes','publicEmail','publicName','publicPhone','regDate','startTime','website','websiteText', 'yourName' );

		foreach( $goodArr as $val ):
			$contents = str_replace( '#'.$val.'#', $externals[$val], $contents );
		endforeach;
		
		# room and branch
		if( !empty( $externals['noLocation_branch'] ) ):
			$contents = str_replace( '#roomName#', 'No location required', $contents );
			$contents = str_replace( '#branchName#', $branchList[$externals['noLocation_branch']]['branchDesc'], $contents );
		else:
			$contents = str_replace( '#roomName#', $roomContList['id'][$externals['roomID']]['desc'], $contents );
			$contents = str_replace( '#branchName#', $branchList[$roomContList['id'][$externals['roomID']]['branchID']]['branchDesc'], $contents );
			
		endif;
		
		$contents = str_replace( '#eventDate#', $externals['eventStart'], $contents );
		if( $externals['allDay'] == true ):
			$contents = str_replace( '#eventTimes#', 'All Day', $contents );
		else:
			$contents = str_replace( '#eventTimes#', $externals['startTime'].' -'.$externals['endTime'], $contents );
		endif;

		
		# registration
		switch( $externals['registration'] ):
			case 'staff':
				$registration = 'Staff';
				break;
			case 'true':
				$registration = 'Yes';
				break;
			default:
				$registration = 'No';
				break;
		endswitch; 
		
		$contents = str_replace( '#registration#', $registration, $contents );
		
		
# Categories
		$categoryList = bookaroom_settings_categories::getNameList();
		
		# add to page
		$category_line = repCon( 'category', $contents );
		$categoryNone_line = repCon( 'categoryNone', $contents, TRUE );
		$category_right_line = repCon( 'category_right', $category_line );
		
		if( count( $categoryList['active'] ) == 0 ):
			$contents = str_replace( '#category_line#', $categoryNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $categoryList['active'] );
			
			for( $t=0; $t < ceil( count( $categoryList['active'] )/2); $t++):
				$temp = $category_line;
				
				
				if( $t== 0 ):
					$curVal = current( $categoryList['active'] );
				else:
					$curVal = next( $categoryList['active'] );
				endif;
				
			
				if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#category#', $curVal['categories_desc'], $temp );
				$temp = str_replace( '#categoryVal#', $curVal['categories_id'], $temp );
				$temp = str_replace( '#category_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $categoryList['active'] );

				if( $nextItem ):
				
					$curVal = current( $categoryList['active'] );
					
					if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#category_right_line#', $category_right_line, $temp );				
					$temp = str_replace( '#category_right#', $curVal['categories_desc'], $temp );
					$temp = str_replace( '#categoryVal_right#', $curVal['categories_id'], $temp );
					$temp = str_replace( '#category_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#category_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#category_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			$contents = str_replace( '#category_line#', implode( "\r\n", $final ), $contents );
		endif;	
		
		
		# age groups
		$ageGroupList = bookaroom_settings_age::getNameList();
		
		# add to page
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$ageGroupNone_line = repCon( 'ageGroupNone', $contents, TRUE );
		$ageGroup_right_line = repCon( 'ageGroup_right', $ageGroup_line );
		
		if( count( $ageGroupList['active'] ) == 0 ):
			$contents = str_replace( '#ageGroup_line#', $ageGroupNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $ageGroupList['active'] );
			
			for( $t=0; $t < ceil( count( $ageGroupList['active'] )/2); $t++):
				$temp = $ageGroup_line;
				
				
				if( $t== 0 ):
					$curVal = current( $ageGroupList['active'] );
				else:
					$curVal = next( $ageGroupList['active'] );
				endif;
				
			
				if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#ageGroup#', $curVal['age_desc'], $temp );
				$temp = str_replace( '#ageGroupVal#', $curVal['age_id'], $temp );
				$temp = str_replace( '#ageGroup_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $ageGroupList['active'] );

				if( $nextItem ):
				
					$curVal = current( $ageGroupList['active'] );

					if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#ageGroup_right_line#', $ageGroup_right_line, $temp );				
					$temp = str_replace( '#ageGroup_right#', $curVal['age_desc'], $temp );
					$temp = str_replace( '#ageGroupVal_right#', $curVal['age_id'], $temp );
					$temp = str_replace( '#ageGroup_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#ageGroup_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#ageGroup_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		# other options
		if( !empty( $externals['doNotPublish'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#doNotPublish_checked#', $checked, $contents );

# amenities
		$final = array();
		if( empty( $roomContList['id'][$externals['roomID']]['rooms'] ) ):
			$roomContList['id'][$externals['roomID']]['rooms'] = array();
		endif;
			 
		foreach( $roomContList['id'][$externals['roomID']]['rooms'] as $val ):
			if( !empty( $roomList['id'][$val]['amenity'] ) && is_array( $roomList['id'][$val]['amenity'] ) ):
				$final = array_merge( $final, $roomList['id'][$val]['amenity'] );
			endif;
		endforeach;

		$final = array_unique( $final );
		
		$amenity = array();

		foreach( $final as $val ):
			$amenity[$val] = $amenityList[$val];
		endforeach;


		# add to page
		$amenity_line = repCon( 'amenity', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );

		if( count( $amenity ) == 0 ):
			$contents = str_replace( '#amenity_line#', $amenityNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
	
			foreach( $amenity as $key => $val ):
				$temp = $amenity_line;
				
				if( !empty( $externals['amenity'] ) && in_array( $key, $externals['amenity'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;
	
				$temp = str_replace( '#amenity#', $val, $temp );
				$temp = str_replace( '#amenityVal#', $key, $temp );
				$temp = str_replace( '#amenity_checked#', $checked, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#amenity_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		#make hash
		$time = time();
		$hash = md5( $time. $externals['eventID'] );
		$contents = str_replace( '#time#', $time, $contents );
		$contents = str_replace( '#hash#', $hash, $contents );
		
		echo $contents;
	}
*/
	
	protected static function showDeleteMulti( $externals, $instances, $errorMSG = NULL )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		
		$filename = BOOKAROOM_PATH . 'templates/events/delete_multi.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#eventID#', $externals['eventID'], $contents );
		
		
		# errors
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		endif;


		# instances
		$instance_line = repCon( 'instance', $contents );
		
		foreach( $instances as $key => $val ):
			$temp = $instance_line;
			
			$temp = str_replace( '#instanceDate#', date( 'm/d/Y', strtotime( $val['ti_startTime'] ) ), $temp );
			$temp = str_replace( '#instanceStartTime#', date( 'g:i a', strtotime( $val['ti_startTime'] ) ), $temp );
			$temp = str_replace( '#instanceEndTime#', date( 'g:i a', strtotime( $val['ti_endTime'] ) ), $temp );
			
			if( !empty( $val['ti_noLocation_branch'] ) ):
				$temp = str_replace( '#instanceBranch#', $branchList[$val['ti_noLocation_branch']]['branchDesc'], $temp );
				$temp = str_replace( '#instanceRoom#', 'No location required', $temp );
				
			else:
				$branchID = self::branch_and_room_id( $val['ti_roomID'], $branchList, $roomContList );
				$temp = str_replace( '#instanceBranch#', $branchList[$branchID]['branchDesc'], $temp );
				$temp = str_replace( '#instanceRoom#',$roomContList['id'][$val['ti_roomID']]['desc'], $temp );
			endif;
			
			$temp = str_replace( '#instanceID#', $val['ti_id'], $temp );
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#instance_line#', implode( "\r\n", $final ), $contents );
		
		# common values
		$goodArr = array( 'endTime','eventDesc','eventStart','eventTitle','everyNWeeks','maxReg','waitingList','presenter','privateNotes','publicEmail','publicName','publicPhone','regDate','startTime','website','websiteText', 'yourName' );

		foreach( $goodArr as $val ):
			$contents = str_replace( '#'.$val.'#', $externals[$val], $contents );
		endforeach;
		
		# room and branch
		if( !empty( $externals['noLocation_branch'] ) ):
			$contents = str_replace( '#roomName#', 'No location required', $contents );
			$contents = str_replace( '#branchName#', $branchList[$externals['noLocation_branch']]['branchDesc'], $contents );
		else:
			$contents = str_replace( '#roomName#', $roomContList['id'][$externals['roomID']]['desc'], $contents );
			$contents = str_replace( '#branchName#', $branchList[$roomContList['id'][$externals['roomID']]['branchID']]['branchDesc'], $contents );
			
		endif;
		
		$contents = str_replace( '#eventDate#', $externals['eventStart'], $contents );
		if( $externals['allDay'] == true ):
			$contents = str_replace( '#eventTimes#', 'All Day', $contents );
		else:
			$contents = str_replace( '#eventTimes#', $externals['startTime'].' -'.$externals['endTime'], $contents );
		endif;
		
		# registration
		switch( $externals['registration'] ):
			case 'staff':
				$registration = 'Staff';
				break;
			case 'true':
				$registration = 'Yes';
				break;
			default:
				$registration = 'No';
				break;
		endswitch; 
		
		$contents = str_replace( '#registration#', $registration, $contents );
		
		
# Categories
		$categoryList = bookaroom_settings_categories::getNameList();
		
		# add to page
		$category_line = repCon( 'category', $contents );
		$categoryNone_line = repCon( 'categoryNone', $contents, TRUE );
		$category_right_line = repCon( 'category_right', $category_line );
		
		if( count( $categoryList['active'] ) == 0 ):
			$contents = str_replace( '#category_line#', $categoryNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $categoryList['active'] );
			
			for( $t=0; $t < ceil( count( $categoryList['active'] )/2); $t++):
				$temp = $category_line;
				
				
				if( $t== 0 ):
					$curVal = current( $categoryList['active'] );
				else:
					$curVal = next( $categoryList['active'] );
				endif;
				
			
				if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#category#', $curVal['categories_desc'], $temp );
				$temp = str_replace( '#categoryVal#', $curVal['categories_id'], $temp );
				$temp = str_replace( '#category_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $categoryList['active'] );

				if( $nextItem ):
				
					$curVal = current( $categoryList['active'] );
					
					if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#category_right_line#', $category_right_line, $temp );				
					$temp = str_replace( '#category_right#', $curVal['categories_desc'], $temp );
					$temp = str_replace( '#categoryVal_right#', $curVal['categories_id'], $temp );
					$temp = str_replace( '#category_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#category_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#category_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			$contents = str_replace( '#category_line#', implode( "\r\n", $final ), $contents );
		endif;	
		
		
		# age groups
		$ageGroupList = bookaroom_settings_age::getNameList();
		
		# add to page
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$ageGroupNone_line = repCon( 'ageGroupNone', $contents, TRUE );
		$ageGroup_right_line = repCon( 'ageGroup_right', $ageGroup_line );
		
		if( count( $ageGroupList['active'] ) == 0 ):
			$contents = str_replace( '#ageGroup_line#', $ageGroupNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $ageGroupList['active'] );
			
			for( $t=0; $t < ceil( count( $ageGroupList['active'] )/2); $t++):
				$temp = $ageGroup_line;
				
				
				if( $t== 0 ):
					$curVal = current( $ageGroupList['active'] );
				else:
					$curVal = next( $ageGroupList['active'] );
				endif;
				
			
				if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#ageGroup#', $curVal['age_desc'], $temp );
				$temp = str_replace( '#ageGroupVal#', $curVal['age_id'], $temp );
				$temp = str_replace( '#ageGroup_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $ageGroupList['active'] );

				if( $nextItem ):
				
					$curVal = current( $ageGroupList['active'] );

					if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#ageGroup_right_line#', $ageGroup_right_line, $temp );				
					$temp = str_replace( '#ageGroup_right#', $curVal['age_desc'], $temp );
					$temp = str_replace( '#ageGroupVal_right#', $curVal['age_id'], $temp );
					$temp = str_replace( '#ageGroup_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#ageGroup_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#ageGroup_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		# other options
		if( !empty( $externals['doNotPublish'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#doNotPublish_checked#', $checked, $contents );

# amenities
		$final = array();
		if( empty( $roomContList['id'][$externals['roomID']]['rooms'] ) ):
			$roomContList['id'][$externals['roomID']]['rooms'] = array();
		endif;
			 
		foreach( $roomContList['id'][$externals['roomID']]['rooms'] as $val ):
			if( !empty( $roomList['id'][$val]['amenity'] ) && is_array( $roomList['id'][$val]['amenity'] ) ):
				$final = array_merge( $final, $roomList['id'][$val]['amenity'] );
			endif;
		endforeach;

		$final = array_unique( $final );
		
		$amenity = array();

		foreach( $final as $val ):
			$amenity[$val] = $amenityList[$val];
		endforeach;


		# add to page
		$amenity_line = repCon( 'amenity', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );

		if( count( $amenity ) == 0 ):
			$contents = str_replace( '#amenity_line#', $amenityNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
	
			foreach( $amenity as $key => $val ):
				$temp = $amenity_line;
				
				if( !empty( $externals['amenity'] ) && in_array( $key, $externals['amenity'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;
	
				$temp = str_replace( '#amenity#', $val, $temp );
				$temp = str_replace( '#amenityVal#', $key, $temp );
				$temp = str_replace( '#amenity_checked#', $checked, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#amenity_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		#make hash
		$time = time();
		$hash = md5( $time. $externals['eventID'] );
		$contents = str_replace( '#time#', $time, $contents );
		$contents = str_replace( '#hash#', $hash, $contents );
		
		echo $contents;
	}
	
	protected static function showDeleteSingle( $externals, $instance = false )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		
		$filename = BOOKAROOM_PATH . 'templates/events/delete_single.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#eventID#', $externals['eventID'], $contents );
		if( $instance == true ):
			$contents = str_replace( '#action#', 'deleteCheckInstance', $contents );
		else:
			$contents = str_replace( '#action#', 'deleteCheck', $contents );
		endif;
			
		# common values
		$goodArr = array( 'endTime','eventDesc','eventStart','eventTitle','everyNWeeks','maxReg','waitingList','presenter','privateNotes','publicEmail','publicName','publicPhone','regDate','startTime','website','websiteText', 'yourName' );

		foreach( $goodArr as $val ):
			$contents = str_replace( '#'.$val.'#', $externals[$val], $contents );
		endforeach;
		
		# room and branch
		if( !empty( $externals['noLocation_branch'] ) ):
			$contents = str_replace( '#roomName#', 'No location required', $contents );
			$contents = str_replace( '#branchName#', $branchList[$externals['noLocation_branch']]['branchDesc'], $contents );
		else:
			$contents = str_replace( '#roomName#', $roomContList['id'][$externals['roomID']]['desc'], $contents );
			$contents = str_replace( '#branchName#', $branchList[$roomContList['id'][$externals['roomID']]['branchID']]['branchDesc'], $contents );
			
		endif;
		
		$contents = str_replace( '#eventDate#', $externals['eventStart'], $contents );
		if( $externals['allDay'] == true ):
			$contents = str_replace( '#eventTimes#', 'All Day', $contents );
		else:
			$contents = str_replace( '#eventTimes#', $externals['startTime'].' -'.$externals['endTime'], $contents );
		endif;
		
		# registration
		switch( $externals['registration'] ):
			case 'staff':
				$registration = 'Staff';
				break;
			case 'true':
				$registration = 'Yes';
				break;
			default:
				$registration = 'No';
				break;
		endswitch; 
		
		$contents = str_replace( '#registration#', $registration, $contents );
		
		
# Categories
		$categoryList = bookaroom_settings_categories::getNameList();
		
		# add to page
		$category_line = repCon( 'category', $contents );
		$categoryNone_line = repCon( 'categoryNone', $contents, TRUE );
		$category_right_line = repCon( 'category_right', $category_line );
		
		if( count( $categoryList['active'] ) == 0 ):
			$contents = str_replace( '#category_line#', $categoryNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $categoryList['active'] );
			
			for( $t=0; $t < ceil( count( $categoryList['active'] )/2); $t++):
				$temp = $category_line;
				
				
				if( $t== 0 ):
					$curVal = current( $categoryList['active'] );
				else:
					$curVal = next( $categoryList['active'] );
				endif;
				
			
				if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#category#', $curVal['categories_desc'], $temp );
				$temp = str_replace( '#categoryVal#', $curVal['categories_id'], $temp );
				$temp = str_replace( '#category_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $categoryList['active'] );

				if( $nextItem ):
				
					$curVal = current( $categoryList['active'] );
					
					if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#category_right_line#', $category_right_line, $temp );				
					$temp = str_replace( '#category_right#', $curVal['categories_desc'], $temp );
					$temp = str_replace( '#categoryVal_right#', $curVal['categories_id'], $temp );
					$temp = str_replace( '#category_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#category_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#category_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			$contents = str_replace( '#category_line#', implode( "\r\n", $final ), $contents );
		endif;	
		
		
		# age groups
		$ageGroupList = bookaroom_settings_age::getNameList();
		
		# add to page
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$ageGroupNone_line = repCon( 'ageGroupNone', $contents, TRUE );
		$ageGroup_right_line = repCon( 'ageGroup_right', $ageGroup_line );
		
		if( count( $ageGroupList['active'] ) == 0 ):
			$contents = str_replace( '#ageGroup_line#', $ageGroupNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $ageGroupList['active'] );
			
			for( $t=0; $t < ceil( count( $ageGroupList['active'] )/2); $t++):
				$temp = $ageGroup_line;
				
				
				if( $t== 0 ):
					$curVal = current( $ageGroupList['active'] );
				else:
					$curVal = next( $ageGroupList['active'] );
				endif;
				
			
				if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#ageGroup#', $curVal['age_desc'], $temp );
				$temp = str_replace( '#ageGroupVal#', $curVal['age_id'], $temp );
				$temp = str_replace( '#ageGroup_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $ageGroupList['active'] );

				if( $nextItem ):
				
					$curVal = current( $ageGroupList['active'] );

					if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#ageGroup_right_line#', $ageGroup_right_line, $temp );				
					$temp = str_replace( '#ageGroup_right#', $curVal['age_desc'], $temp );
					$temp = str_replace( '#ageGroupVal_right#', $curVal['age_id'], $temp );
					$temp = str_replace( '#ageGroup_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#ageGroup_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#ageGroup_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		# other options
		if( !empty( $externals['doNotPublish'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#doNotPublish_checked#', $checked, $contents );

# amenities
		$final = array();
		if( empty( $roomContList['id'][$externals['roomID']]['rooms'] ) ):
			$roomContList['id'][$externals['roomID']]['rooms'] = array();
		endif;
			 
		foreach( $roomContList['id'][$externals['roomID']]['rooms'] as $val ):
			if( !empty( $roomList['id'][$val]['amenity'] ) && is_array( $roomList['id'][$val]['amenity'] ) ):
				$final = array_merge( $final, $roomList['id'][$val]['amenity'] );
			endif;
		endforeach;

		$final = array_unique( $final );
		
		$amenity = array();

		foreach( $final as $val ):
			$amenity[$val] = $amenityList[$val];
		endforeach;


		# add to page
		$amenity_line = repCon( 'amenity', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );

		if( count( $amenity ) == 0 ):
			$contents = str_replace( '#amenity_line#', $amenityNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
	
			foreach( $amenity as $key => $val ):
				$temp = $amenity_line;
				
				if( !empty( $externals['amenity'] ) && in_array( $key, $externals['amenity'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;
	
				$temp = str_replace( '#amenity#', $val, $temp );
				$temp = str_replace( '#amenityVal#', $key, $temp );
				$temp = str_replace( '#amenity_checked#', $checked, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#amenity_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		#make hash
		$time = time();
		$hash = md5( $time. $externals['eventID'] );
		$contents = str_replace( '#time#', $time, $contents );
		$contents = str_replace( '#hash#', $hash, $contents );
		
		echo $contents;
	}
	
	protected static function showEventForm_event( $externals, $errorArr = NULL, $eventInfo = array() )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		
		$filename = BOOKAROOM_PATH . 'templates/events/eventForm_event.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#eventID#', $externals['eventID'], $contents );
		

		# errors
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorArr['errorMSG'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorArr['errorMSG'], $contents );
		endif;
		
		$instances = self::getInstances( $externals['res_id'] );
		
		$instance_line = repCon( 'instance', $contents );
		
		foreach( $instances as $key => $val ):
			$temp = $instance_line;
			
			$temp = str_replace( '#instanceDate#', date( 'm/d/Y', strtotime( $val['ti_startTime'] ) ), $temp );
			$temp = str_replace( '#instanceStartTime#', date( 'g:i a', strtotime( $val['ti_startTime'] ) ), $temp );
			$temp = str_replace( '#instanceEndTime#', date( 'g:i a', strtotime( $val['ti_endTime'] ) ), $temp );
			
			if( empty( $val['ti_roomID'] ) && !empty( $val['ti_noLocation_branch'] ) ):
				$temp = str_replace( '#instanceBranch#', $branchList[$val['ti_noLocation_branch']]['branchDesc'], $temp );
				$temp = str_replace( '#instanceRoom#', 'No location required', $temp );
				
			else:
				$branchID = self::branch_and_room_id( $val['ti_roomID'], $branchList, $roomContList );
				
				if( !empty( $branchList[$branchID]['branchDesc'] ) ):
					$temp = str_replace( '#instanceBranch#', $branchList[$branchID]['branchDesc'], $temp );
				else:
					$temp = str_replace( '#instanceBranch#', 'Unknown/Deleted Branch', $temp );
				endif;
					
				
				if( !empty( $roomContList['id'][$val['ti_roomID']]['desc'] ) ):
					$temp = str_replace( '#instanceRoom#',$roomContList['id'][$val['ti_roomID']]['desc'], $temp );
				else:
					$temp = str_replace( '#instanceRoom#', "Unknown/Deleted Room", $temp );
				endif;
			endif;
			
			$temp = str_replace( '#instanceID#', $val['ti_id'], $temp );
			
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#instance_line#', implode( "\r\n", $final ), $contents );
		###########***
		
		# common values
		$goodArr = array( 'endTime','eventDesc','eventStart','eventTitle','everyNWeeks','maxReg','waitingList','presenter','privateNotes','publicEmail','publicName','publicPhone','regDate','startTime','website','websiteText', 'yourName' );

		foreach( $goodArr as $val ):
			$contents = str_replace( '#'.$val.'#', $externals[$val], $contents );
		endforeach;

		# amenities
		$final = array();
		if( empty( $roomContList['id'][$externals['roomID']]['rooms'] ) ):
			$roomContList['id'][$externals['roomID']]['rooms'] = array();
		endif;
			 
		foreach( $roomContList['id'][$externals['roomID']]['rooms'] as $val ):
			if( !empty( $roomList['id'][$val]['amenity'] ) && is_array( $roomList['id'][$val]['amenity'] ) ):
				$final = array_merge( $final, $roomList['id'][$val]['amenity'] );
			endif;
		endforeach;

		$final = array_unique( $final );
		
		$amenity = array();

		foreach( $final as $val ):
			$amenity[$val] = $amenityList[$val];
		endforeach;


		# add to page
		$amenity_line = repCon( 'amenity', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );

		if( count( $amenity ) == 0 ):
			$contents = str_replace( '#amenity_line#', $amenityNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
	
			foreach( $amenity as $key => $val ):
				$temp = $amenity_line;
				
				if( !empty( $externals['amenity'] ) && in_array( $key, $externals['amenity'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;
	
				$temp = str_replace( '#amenity#', $val, $temp );
				$temp = str_replace( '#amenityVal#', $key, $temp );
				$temp = str_replace( '#amenity_checked#', $checked, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#amenity_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		# add dates rows
		
		$row_line = repCon( 'row', $contents );
		
		if( empty( $externals['addDateVals'] ) ):
			$contents = str_replace( '#row_line#', NULL, $contents );
		else:
			$temp = NULL;
			$final = array();
			$count = 0;
			foreach( $externals['addDateVals'] as $val ):
				$temp = $row_line;
				
				$temp = str_replace( '#rowNum#', $count++, $temp );
				$temp = str_replace( '#rowVal#', $val, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#row_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		# registation
		$goodArr = array( 'true' => NULL, 'false' => NULL, 'staff' => NULL );

		switch( $externals['registration'] ):
			case 'true':
				$goodArr['true'] = ' checked="checked"';						
				break;
			case 'staff':
				$goodArr['staff'] = ' checked="checked"';
				break;
			case 'false':
			default:
				$goodArr['false'] = ' checked="checked"';
				break;
		endswitch;
		
		foreach( $goodArr as $key => $val ):
			$contents = str_replace( "#reg_{$key}_selected#", $val, $contents );
		endforeach;
		
		# Categories
		$categoryList = bookaroom_settings_categories::getNameList();
		
		# add to page
		$category_line = repCon( 'category', $contents );
		$categoryNone_line = repCon( 'categoryNone', $contents, TRUE );
		$category_right_line = repCon( 'category_right', $category_line );
		
		if( count( $categoryList['active'] ) == 0 ):
			$contents = str_replace( '#category_line#', $categoryNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $categoryList['active'] );
			
			for( $t=0; $t < ceil( count( $categoryList['active'] )/2); $t++):
				$temp = $category_line;
				
				
				if( $t== 0 ):
					$curVal = current( $categoryList['active'] );
				else:
					$curVal = next( $categoryList['active'] );
				endif;
				
			
				if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#category#', $curVal['categories_desc'], $temp );
				$temp = str_replace( '#categoryVal#', $curVal['categories_id'], $temp );
				$temp = str_replace( '#category_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $categoryList['active'] );

				if( $nextItem ):
				
					$curVal = current( $categoryList['active'] );
					
					if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#category_right_line#', $category_right_line, $temp );				
					$temp = str_replace( '#category_right#', $curVal['categories_desc'], $temp );
					$temp = str_replace( '#categoryVal_right#', $curVal['categories_id'], $temp );
					$temp = str_replace( '#category_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#category_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#category_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			$contents = str_replace( '#category_line#', implode( "\r\n", $final ), $contents );
		endif;	
		
		
		# age groups
		$ageGroupList = bookaroom_settings_age::getNameList();
		
		# add to page
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$ageGroupNone_line = repCon( 'ageGroupNone', $contents, TRUE );
		$ageGroup_right_line = repCon( 'ageGroup_right', $ageGroup_line );
		
		if( count( $ageGroupList['active'] ) == 0 ):
			$contents = str_replace( '#ageGroup_line#', $ageGroupNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $ageGroupList['active'] );
			
			for( $t=0; $t < ceil( count( $ageGroupList['active'] )/2); $t++):
				$temp = $ageGroup_line;
				
				
				if( $t== 0 ):
					$curVal = current( $ageGroupList['active'] );
				else:
					$curVal = next( $ageGroupList['active'] );
				endif;
				
			
				if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#ageGroup#', $curVal['age_desc'], $temp );
				$temp = str_replace( '#ageGroupVal#', $curVal['age_id'], $temp );
				$temp = str_replace( '#ageGroup_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $ageGroupList['active'] );

				if( $nextItem ):
				
					$curVal = current( $ageGroupList['active'] );

					if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#ageGroup_right_line#', $ageGroup_right_line, $temp );				
					$temp = str_replace( '#ageGroup_right#', $curVal['age_desc'], $temp );
					$temp = str_replace( '#ageGroupVal_right#', $curVal['age_id'], $temp );
					$temp = str_replace( '#ageGroup_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#ageGroup_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#ageGroup_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		# other options
		if( !empty( $externals['doNotPublish'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#doNotPublish_checked#', $checked, $contents );
	
		
		# ERROR BACKGROUNDS
		if( !empty( $errorArr['errorBG'] ) ):
			$goodArr = array( 'eventStart', 'recurrence', 'startTime', 'endTime', 'eventTitle', 'eventDesc', 'registration', 'maxReg', 'waitingList', 'regDate', 
				'dailyType', 'dailyType_everyNDays', 'dailyType_weekends', 'dailyType_weekdays', 'dailyEndType_Occurrences', 'dailyEndType_endBy', 
				'dailyEndType_Occurrences_form', 'dailyEndType_endBy_form', 'website', 'everyNWeeks', 'weeklyDay', 'weeklyEndType_Occurrences', 'weeklyEndType_endBy', 
				'weeklyEndType_Occurrences_form', 'weeklyEndType_endBy_form', 'addDates', 'yourName', 'category', 'ageGroup', 'publicPhone', 'location' );
			foreach( $goodArr as $val ):
				if( array_key_exists( $val, $errorArr['errorBG'] ) ):
					$contents = str_replace( "#class_{$val}#", 'error', $contents );
				else:
					$contents = str_replace( "#class_{$val}#", NULL, $contents );				
				endif;
			endforeach;
		endif;
		
		# ADD DATE ERROR BACKGROUNDS
		$count = 0;
		if( !is_array( $externals['addDateVals'] ) ):
			$externals['addDateVals'] = array();
		endif;
		
		foreach( $externals['addDateVals'] as $key => $val ):
			if( !empty( $errorArr['errorBG']['addDateVals'][$count] ) ):
				$contents = str_replace( "#class_addDatesForm_{$count}#", 'error', $contents );
			else:
				$contents = str_replace( "#class_addDatesForm_{$count}#", NULL, $contents );
			endif;
			$count++;
		endforeach;
		
		$contents = str_replace( '#recurrance#', $checked, $contents );
				
		echo $contents;
	}
	
	protected static function showEventForm_instance( $externals, $errorArr = NULL, $eventInfo = array() )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		
		$filename = BOOKAROOM_PATH . 'templates/events/eventForm_instance.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#eventID#', $externals['eventID'], $contents );

		# no location
		if( !empty( $externals['roomID'] ) and substr( $externals['roomID'], 0, 6 ) == 'noloc-' ):
			$branchInfo = explode( '-', $externals['roomID'] );
			if( !array_key_exists( $branchInfo['0'], $branchList ) ):
				$branchID = NULL;
				$roomID = NULL;
			endif;
		else:
			$branchID = self::branch_and_room_id( $externals['roomID'], $branchList, $roomContList );
			$roomID = $externals['roomID'];
		endif;
		# errors
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorArr['errorMSG'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorArr['errorMSG'], $contents );
		endif;
		
		# branch and room
		
		# make branch drop
		$room_line = repCon( 'room', $contents );
		$final = array();
		
		foreach( $branchList as $key => $val ):
			$branchName = $val['branchDesc'];
			$temp = $room_line;
			$temp = str_replace( '#roomVal#', NULL, $temp );
			$temp = str_replace( '#roomDesc#', $branchName, $temp );
			$temp = str_replace( '#roomDisabled#', ' disabled="disabled"', $temp );
			$temp = str_replace( '#roomSelected#', NULL, $temp );
			$final[] = $temp;

			if( true == $val['branch_hasNoloc'] ):
				$temp = $room_line;
				$selected = ( $externals['roomID'] == 'noloc-'.$val['branchID'] ) ? ' selected="selected"' : NULL;
				$temp = str_replace( '#roomVal#', 'noloc-'.$val['branchID'], $temp );
				$temp = str_replace( '#roomDesc#', '&nbsp;&nbsp;&nbsp;&nbsp;'.$branchName.' - No location required', $temp );
				$temp = str_replace( '#roomSelected#', $selected, $temp );
				$temp = str_replace( '#roomDisabled#', NULL, $temp );
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
	


		# common values
		$goodArr = array( 'ev_desc' => 'eventDesc', 'ev_title' => 'eventTitle', 'ev_maxReg' => 'maxReg', 'ev_waitingList' => 'waitingList', 'ev_presenter' => 'presenter', 'ev_privateNotes' => 'privateNotes', 'ev_publicEmail' => 'publicEmail', 'ev_publicName' => 'publicName', 'ev_publicPhone' => 'publicPhone', 'ev_website' => 'website', 'ev_webText' => 'websiteText', 'ti_extraInfo' => 'extraInfo' );

		foreach( $goodArr as $key => $val ):
			$contents = str_replace( '#'.$val.'#', $eventInfo[$key], $contents );
		endforeach;

		$contents = str_replace( '#eventStart#', date( 'm/d/Y', strtotime( $externals['eventStart'] ) ), $contents );
		
		if( !empty( $externals['regStartDate'] ) ):
			$regDate = date( 'm/d/Y', strtotime( $externals['regStartDate'] ) );	
		else:
			$regDate = NULL;
		endif;
		
		$contents = str_replace( '#regDate#', $regDate, $contents );
		
		$contents = str_replace( '#eventStart#', date( 'm/d/Y', strtotime( $externals['startTime'] ) ), $contents );

		$startTime = date( 'g:i a', strtotime( $externals['startTime'] ) );
		$endTime = date( 'g:i a', strtotime( $externals['endTime'] ) );
		
		if( $startTime == '12:00 am' and $endTime = '11:59 pm' ):
			$contents = str_replace( '#allDay_true_checked#', ' checked="checked"', $contents );
			$contents = str_replace( '#allDay_false_checked#', NULL, $contents );
			$contents = str_replace( '#startTime#', NULL, $contents );
			$contents = str_replace( '#endTime#', NULL, $contents );
		else:
			$contents = str_replace( '#allDay_true_checked#', NULL, $contents );
			$contents = str_replace( '#allDay_false_checked#', ' checked="checked"', $contents );						
			$contents = str_replace( '#startTime#', $startTime, $contents );
			$contents = str_replace( '#endTime#', $endTime, $contents );

		endif;
		
		# amenities
		$final = array();
		if( empty( $roomContList['id'][$eventInfo['ti_roomID']]['rooms'] ) ):
			$roomContList['id'][$eventInfo['ti_roomID']]['rooms'] = array();
		endif;
		
		foreach( $roomContList['id'][$eventInfo['ti_roomID']]['rooms'] as $val ):
			if( !empty( $roomList['id'][$val]['amenity'] ) && is_array( $roomList['id'][$val]['amenity'] ) ):
				$final = array_merge( $final, $roomList['id'][$val]['amenity'] );
			endif;
		endforeach;

		$final = array_unique( $final );
		
		$amenity = array();

		foreach( $final as $val ):
			$amenity[$val] = $amenityList[$val];
		endforeach;


		# add to page
		$amenity_line = repCon( 'amenity', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );

		if( count( $amenity ) == 0 ):
			$contents = str_replace( '#amenity_line#', $amenityNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
	
			foreach( $amenity as $key => $val ):
				$temp = $amenity_line;
				
				if( !empty( $eventInfo['amenity'] ) && in_array( $key, $eventInfo['amenity'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;
	
				$temp = str_replace( '#amenity#', $val, $temp );
				$temp = str_replace( '#amenityVal#', $key, $temp );
				$temp = str_replace( '#amenity_checked#', $checked, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#amenity_line#', implode( "\r\n", $final ), $contents );
		endif;

		# registation
		$goodArr = array( 'true' => NULL, 'false' => NULL, 'staff' => NULL );

		switch( $eventInfo['registration'] ):
			case 'true':
				$regVal = 'Yes';
				break;
			case 'staff':
				$regVal = 'Staff';
				break;
			case 'false':
			default:
				$regVal = 'No';
				break;
		endswitch;
		
		$contents = str_replace( '#registration#', $regVal, $contents );
		# Categories
		$categoryList = bookaroom_settings_categories::getNameList();
		
		# add to page
		$category_line = repCon( 'category', $contents );
		$categoryNone_line = repCon( 'categoryNone', $contents, TRUE );
		$category_right_line = repCon( 'category_right', $category_line );
		
		if( count( $categoryList['active'] ) == 0 ):
			$contents = str_replace( '#category_line#', $categoryNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $categoryList['active'] );
			
			for( $t=0; $t < ceil( count( $categoryList['active'] )/2); $t++):
				$temp = $category_line;
				
				
				if( $t== 0 ):
					$curVal = current( $categoryList['active'] );
				else:
					$curVal = next( $categoryList['active'] );
				endif;
				
			
				if( !empty( $eventInfo['category'] ) && in_array( $curVal['categories_id'], $eventInfo['category'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#category#', $curVal['categories_desc'], $temp );
				$temp = str_replace( '#categoryVal#', $curVal['categories_id'], $temp );
				$temp = str_replace( '#category_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $categoryList['active'] );

				if( $nextItem ):
				
					$curVal = current( $categoryList['active'] );
					
					if( !empty( $eventInfo['category'] ) && in_array( $curVal['categories_id'], $eventInfo['category'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#category_right_line#', $category_right_line, $temp );				
					$temp = str_replace( '#category_right#', $curVal['categories_desc'], $temp );
					$temp = str_replace( '#categoryVal_right#', $curVal['categories_id'], $temp );
					$temp = str_replace( '#category_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#category_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#category_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			$contents = str_replace( '#category_line#', implode( "\r\n", $final ), $contents );
		endif;	
		
		
		# age groups
		$ageGroupList = bookaroom_settings_age::getNameList();
		
		# add to page
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$ageGroupNone_line = repCon( 'ageGroupNone', $contents, TRUE );
		$ageGroup_right_line = repCon( 'ageGroup_right', $ageGroup_line );
		
		if( count( $ageGroupList['active'] ) == 0 ):
			$contents = str_replace( '#ageGroup_line#', $ageGroupNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $ageGroupList['active'] );
			
			for( $t=0; $t < ceil( count( $ageGroupList['active'] )/2); $t++):
				$temp = $ageGroup_line;
				
				
				if( $t== 0 ):
					$curVal = current( $ageGroupList['active'] );
				else:
					$curVal = next( $ageGroupList['active'] );
				endif;
				
			
				if( !empty( $eventInfo['ageGroup'] ) && in_array( $curVal['age_id'], $eventInfo['ageGroup'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#ageGroup#', $curVal['age_desc'], $temp );
				$temp = str_replace( '#ageGroupVal#', $curVal['age_id'], $temp );
				$temp = str_replace( '#ageGroup_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $ageGroupList['active'] );

				if( $nextItem ):
				
					$curVal = current( $ageGroupList['active'] );

					if( !empty( $eventInfo['ageGroup'] ) && in_array( $curVal['age_id'], $eventInfo['ageGroup'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#ageGroup_right_line#', $ageGroup_right_line, $temp );				
					$temp = str_replace( '#ageGroup_right#', $curVal['age_desc'], $temp );
					$temp = str_replace( '#ageGroupVal_right#', $curVal['age_id'], $temp );
					$temp = str_replace( '#ageGroup_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#ageGroup_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#ageGroup_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		# other options
		if( !empty( $eventInfo['doNotPublish'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#doNotPublish_checked#', $checked, $contents );
		
		# ERROR BACKGROUNDS
		if( !empty( $errorArr['errorBG'] ) ):
			$goodArr = array( 'eventStart', 'startTime', 'endTime' );
			foreach( $goodArr as $val ):
				if( array_key_exists( $val, $errorArr['errorBG'] ) ):
					$contents = str_replace( "#class_{$val}#", 'error', $contents );
				else:
					$contents = str_replace( "#class_{$val}#", NULL, $contents );				
				endif;
			endforeach;
		endif;
											
		echo $contents;
		
	}
	
	protected static function showEventForm_times( $externals, $errorArr = NULL, $displayName = 'New', $action = 'checkInformation', $changeAction = 'changeRooms' )
	{
		$roomContList = bookaroom_settings_roomConts::getRoomContList();
		$roomList = bookaroom_settings_rooms::getRoomList();
		$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		$amenityList = bookaroom_settings_amenities::getAmenityList();
		
		$filename = BOOKAROOM_PATH . 'templates/events/eventForm_times.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#changeAction#', $changeAction, $contents );
		
		$contents = str_replace( '#action#', $action, $contents );
		$contents = str_replace( '#displayName#', $displayName, $contents );
		$contents = str_replace( '#eventID#', $externals['eventID'], $contents );
		
		# no location
		if( !empty( $externals['roomID'] ) and substr( $externals['roomID'], 0, 6 ) == 'noloc-' ):
			$branchInfo = explode( '-', $externals['roomID'] );
			if( !array_key_exists( $branchInfo['0'], $branchList ) ):
				$branchID = NULL;
				$roomID = NULL;
			endif;
		else:
			$branchID = self::branch_and_room_id( $externals['roomID'], $branchList, $roomContList );
			$roomID = $externals['roomID'];
		endif;

		# errors
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorArr['errorMSG'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorArr['errorMSG'], $contents );
		endif;
		
		# branch and room
		
		# make branch drop
		$room_line = repCon( 'room', $contents );
		$final = array();
		
		$temp = $room_line;
		$temp = str_replace( '#roomVal#', NULL, $temp );
		$temp = str_replace( '#roomDesc#', 'Please choose a location.', $temp );
		$temp = str_replace( '#roomDisabled#', ' disabled="disabled"', $temp );
		if( empty( $externals['roomID'] ) ):
			$selected = ' selected="selected"';
		else:
			$selected = NULL;
		endif;
		$temp = str_replace( '#roomSelected#', $selected, $temp );
		$final[] = $temp;
		
		foreach( $branchList as $key => $val ):
			$branchName = $val['branchDesc'];
			$temp = $room_line;
			$temp = str_replace( '#roomVal#', NULL, $temp );
			$temp = str_replace( '#roomDesc#', $branchName, $temp );
			$temp = str_replace( '#roomDisabled#', ' disabled="disabled"', $temp );
			$temp = str_replace( '#roomSelected#', NULL, $temp );
			$final[] = $temp;

			
			$temp = $room_line;
			
			if( true == $val['branch_hasNoloc'] ):
				$selected = ( $externals['roomID'] == 'noloc-'.$val['branchID'] ) ? ' selected="selected"' : NULL;
				$temp = str_replace( '#roomVal#', 'noloc-'.$val['branchID'], $temp );
				$temp = str_replace( '#roomDesc#', '&nbsp;&nbsp;&nbsp;&nbsp;'.$branchName.' - No location required', $temp );
				$temp = str_replace( '#roomSelected#', $selected, $temp );
				$temp = str_replace( '#roomDisabled#', NULL, $temp );
				$final[] = $temp;
			endif;			

			# get all room conts
			$curRoomList = empty( $roomContList['branch'][$val['branchID']] ) ? array() : $roomContList['branch'][$val['branchID']];
			
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
	

		# recurrence
		$goodArr = array( 'single' => 'Single', 'daily' => 'Daily', 'weekly' => 'Weekly', 'addDates' => 'Add Dates' );
				
		$reoc_line = repCon( 'reoc', $contents );
		$temp = NULL;
		$final = array();
		
		foreach( $goodArr as $key => $val ):
			$temp = $reoc_line;
			
			$selected = NULL;
			
			if( $externals['recurrence'] === $key ):
				$selected = 'selected="selected"';
				$contents = str_replace( '#showName#', $key, $contents );
			endif;
			
			$temp = str_replace( "#reoc_disp#", $val, $temp );
			$temp = str_replace( "#reoc_val#", $key, $temp );
			$temp = str_replace( "#reoc_selected#", $selected, $temp );
			
			$final[] = $temp;			
		endforeach;
		
		$contents = str_replace( '#reoc_line#', implode( "\r\n", $final), $contents );
		
		# daily
		$checked_everyNDays	= NULL;
		$checked_weekdays	= NULL;
		$checked_weekends	= NULL;

		switch( $externals['dailyType'] ):
			case 'everyNDays':
				$checked_everyNDays	= ' checked="checked"';
				break;
			case 'weekdays':
				$checked_weekdays	= ' checked="checked"';
				break;
			case 'weekends':
				$checked_weekends	= ' checked="checked"';
				break;
		endswitch;
		
		$contents = str_replace( '#everyNDays_checked#', $checked_everyNDays, $contents );
		$contents = str_replace( '#weekdays_checked#', $checked_weekdays, $contents );
		$contents = str_replace( '#weekends_checked#', $checked_weekends, $contents );
		
		$contents = str_replace( '#dailyEveryNDaysVal#', $externals['dailyEveryNDaysVal'], $contents );

		# daily type
		$checked_Occurrences	= NULL;
		$checked_endBy		= NULL;

		switch( $externals['dailyEndType'] ):
			case 'Occurrences':
				$checked_Occurrences	= ' checked="checked"';
				break;
			case 'endBy':
				$checked_endBy	= ' checked="checked"';
				break;
		endswitch;
		
		$contents = str_replace( '#daily_Occurrence_checked#', $checked_Occurrences, $contents );
		$contents = str_replace( '#daily_endBy_checked#', $checked_endBy, $contents );
		
		$contents = str_replace( '#daily_Occurrence#', $externals['daily_Occurrence'], $contents );
		$contents = str_replace( '#daily_endBy#', $externals['daily_endBy'], $contents );
		
		# weekly
		$weekly_line = repCon( 'weekly', $contents );
		$temp = NULL;
		$final = array();
		
		$goodArr = array( 1 => 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday' );

		if( empty( $externals['weeklyDay'] ) ):
			$externals['weeklyDay'] = array();
		endif;
			
		foreach( $goodArr as $key => $val ):
			$temp = $weekly_line;
			$temp = str_replace( '#weekly_val#', $val, $temp );
			
			$checked = ( in_array( $val, $externals['weeklyDay'] ) ) ? ' checked="checked"' : NULL;
			$temp = str_replace( '#weekly_checked#', $checked, $temp );
				
			$final[] = $temp;
		endforeach;
		
		$contents = str_replace( '#weekly_line#', implode( "\r\n", $final ), $contents );

		# weekly type
		$checked_Occurrences	= NULL;
		$checked_endBy		= NULL;

		switch( $externals['weeklyEndType'] ):
			case 'Occurrences':
				$checked_Occurrences	= ' checked="checked"';
				break;
			case 'endBy':
				$checked_endBy	= ' checked="checked"';
				break;
		endswitch;
		
		$contents = str_replace( '#weekly_Occurrence_checked#', $checked_Occurrences, $contents );
		$contents = str_replace( '#weekly_endBy_checked#', $checked_endBy, $contents );
		
		$contents = str_replace( '#weekly_Occurrence#', $externals['weekly_Occurrence'], $contents );
		$contents = str_replace( '#weekly_endBy#', $externals['weekly_endBy'], $contents );
		
		
		# all day
		if( $externals['allDay'] == 'true' ):
			$allDayTrue = ' checked="checked"';
			$allDayFalse = NULL;
		else:
			$allDayTrue = NULL;
			$allDayFalse = ' checked="checked"';
		endif;
	
		$contents = str_replace( '#allDay_true_checked#', $allDayTrue, $contents );
		$contents = str_replace( '#allDay_false_checked#', $allDayFalse, $contents );
		
		
		###########***
		
		# common values
		$goodArr = array( 'endTime','eventDesc','eventStart','eventTitle','everyNWeeks','maxReg','waitingList','presenter','privateNotes','publicEmail','publicName','publicPhone','regDate','startTime','website','websiteText', 'yourName' );

		foreach( $goodArr as $val ):
			$contents = str_replace( '#'.$val.'#', $externals[$val], $contents );
		endforeach;

		# amenities
		$final = array();
		if( empty( $roomContList['id'][$externals['roomID']]['rooms'] ) ):
			$roomContList['id'][$externals['roomID']]['rooms'] = array();
		endif;
			 
		foreach( $roomContList['id'][$externals['roomID']]['rooms'] as $val ):
			if( !empty( $roomList['id'][$val]['amenity'] ) && is_array( $roomList['id'][$val]['amenity'] ) ):
				$final = array_merge( $final, $roomList['id'][$val]['amenity'] );
			endif;
		endforeach;

		$final = array_unique( $final );
		
		$amenity = array();

		foreach( $final as $val ):
			$amenity[$val] = $amenityList[$val];
		endforeach;


		# add to page
		$amenity_line = repCon( 'amenity', $contents );
		$amenityNone_line = repCon( 'amenityNone', $contents, TRUE );

		if( count( $amenity ) == 0 ):
			$contents = str_replace( '#amenity_line#', $amenityNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
	
			foreach( $amenity as $key => $val ):
				$temp = $amenity_line;
				
				if( !empty( $externals['amenity'] ) && in_array( $key, $externals['amenity'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;
	
				$temp = str_replace( '#amenity#', $val, $temp );
				$temp = str_replace( '#amenityVal#', $key, $temp );
				$temp = str_replace( '#amenity_checked#', $checked, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#amenity_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		# add dates rows
		
		$row_line = repCon( 'row', $contents );
		
		if( empty( $externals['addDateVals'] ) ):
			$contents = str_replace( '#row_line#', NULL, $contents );
		else:
			$temp = NULL;
			$final = array();
			$count = 0;
			foreach( $externals['addDateVals'] as $val ):
				$temp = $row_line;
				
				$temp = str_replace( '#rowNum#', $count++, $temp );
				$temp = str_replace( '#rowVal#', $val, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#row_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		# registation
		$goodArr = array( 'true' => NULL, 'false' => NULL, 'staff' => NULL );

		switch( $externals['registration'] ):
			case 'yes':
				$goodArr['true'] = ' checked="checked"';						
				break;
			case 'staff':
				$goodArr['staff'] = ' checked="checked"';
				break;
			case 'no':
			default:
				$goodArr['false'] = ' checked="checked"';
				break;
		endswitch;
		
		foreach( $goodArr as $key => $val ):
			$contents = str_replace( "#reg_{$key}_selected#", $val, $contents );
		endforeach;
		
		# Categories
		$categoryList = bookaroom_settings_categories::getNameList();
		
		# add to page
		$category_line = repCon( 'category', $contents );
		$categoryNone_line = repCon( 'categoryNone', $contents, TRUE );
		$category_right_line = repCon( 'category_right', $category_line );
		
		if( count( $categoryList['active'] ) == 0 ):
			$contents = str_replace( '#category_line#', $categoryNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $categoryList['active'] );
			
			for( $t=0; $t < ceil( count( $categoryList['active'] )/2); $t++):
				$temp = $category_line;
				
				
				if( $t== 0 ):
					$curVal = current( $categoryList['active'] );
				else:
					$curVal = next( $categoryList['active'] );
				endif;
				
			
				if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#category#', $curVal['categories_desc'], $temp );
				$temp = str_replace( '#categoryVal#', $curVal['categories_id'], $temp );
				$temp = str_replace( '#category_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $categoryList['active'] );

				if( $nextItem ):
				
					$curVal = current( $categoryList['active'] );
					
					if( !empty( $externals['category'] ) && in_array( $curVal['categories_id'], $externals['category'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#category_right_line#', $category_right_line, $temp );				
					$temp = str_replace( '#category_right#', $curVal['categories_desc'], $temp );
					$temp = str_replace( '#categoryVal_right#', $curVal['categories_id'], $temp );
					$temp = str_replace( '#category_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#category_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#category_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			$contents = str_replace( '#category_line#', implode( "\r\n", $final ), $contents );
		endif;	
		
		
		# age groups
		$ageGroupList = bookaroom_settings_age::getNameList();
		
		# add to page
		$ageGroup_line = repCon( 'ageGroup', $contents );
		$ageGroupNone_line = repCon( 'ageGroupNone', $contents, TRUE );
		$ageGroup_right_line = repCon( 'ageGroup_right', $ageGroup_line );
		
		if( count( $ageGroupList['active'] ) == 0 ):
			$contents = str_replace( '#ageGroup_line#', $ageGroupNone_line, $contents );
		else:		
			$final = array();		
			$temp = NULL;
			
			
			
			$keyList = array_keys( $ageGroupList['active'] );
			
			for( $t=0; $t < ceil( count( $ageGroupList['active'] )/2); $t++):
				$temp = $ageGroup_line;
				
				
				if( $t== 0 ):
					$curVal = current( $ageGroupList['active'] );
				else:
					$curVal = next( $ageGroupList['active'] );
				endif;
				
			
				if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
					$checked = ' checked="checked"';
				else:
					$checked = NULL;
				endif;

			
				$temp = str_replace( '#ageGroup#', $curVal['age_desc'], $temp );
				$temp = str_replace( '#ageGroupVal#', $curVal['age_id'], $temp );
				$temp = str_replace( '#ageGroup_checked#', $checked, $temp );
				
			
				
				$nextItem =  next( $ageGroupList['active'] );

				if( $nextItem ):
				
					$curVal = current( $ageGroupList['active'] );

					if( !empty( $externals['ageGroup'] ) && in_array( $curVal['age_id'], $externals['ageGroup'] ) ):
						$checked = ' checked="checked"';
					else:
						$checked = NULL;
					endif;
					$temp = str_replace( '#ageGroup_right_line#', $ageGroup_right_line, $temp );				
					$temp = str_replace( '#ageGroup_right#', $curVal['age_desc'], $temp );
					$temp = str_replace( '#ageGroupVal_right#', $curVal['age_id'], $temp );
					$temp = str_replace( '#ageGroup_right_checked#', $checked, $temp );

					
				else:
					$temp = str_replace( '#ageGroup_right_line#', '&nbsp;', $temp );
					$temp = str_replace( '#ageGroup_right#', '&nbsp;', $temp );
					
				endif;
				$final[] = $temp;				
			endfor;
			
			$contents = str_replace( '#ageGroup_line#', implode( "\r\n", $final ), $contents );
		endif;		
		
		# other options
		if( !empty( $externals['doNotPublish'] ) ):
			$checked = ' checked="checked"';
		else:
			$checked = NULL;
		endif;
		
		$contents = str_replace( '#doNotPublish_checked#', $checked, $contents );
	
		
		# ERROR BACKGROUNDS
		if( !empty( $errorArr['errorBG'] ) ):
			$goodArr = array( 'eventStart', 'recurrence', 'startTime', 'endTime', 'eventTitle', 'eventDesc', 'registration', 'maxReg', 'waitingList', 'regDate', 
				'dailyType', 'dailyType_everyNDays', 'dailyType_weekends', 'dailyType_weekdays', 'dailyEndType_Occurrences', 'dailyEndType_endBy', 
				'dailyEndType_Occurrences_form', 'dailyEndType_endBy_form', 'website', 'everyNWeeks', 'weeklyDay', 'weeklyEndType_Occurrences', 'weeklyEndType_endBy', 
				'weeklyEndType_Occurrences_form', 'weeklyEndType_endBy_form', 'addDates', 'yourName', 'category', 'ageGroup', 'publicPhone', 'location' );
			foreach( $goodArr as $val ):
				if( array_key_exists( $val, $errorArr['errorBG'] ) ):
					$contents = str_replace( "#class_{$val}#", 'error', $contents );
				else:
					$contents = str_replace( "#class_{$val}#", NULL, $contents );				
				endif;
			endforeach;
		endif;
		
		# ADD DATE ERROR BACKGROUNDS
		$count = 0;
		if( !is_array( $externals['addDateVals'] ) ):
			$externals['addDateVals'] = array();
		endif;
		
		foreach( $externals['addDateVals'] as $key => $val ):
			if( !empty( $errorArr['errorBG']['addDateVals'][$count] ) ):
				$contents = str_replace( "#class_addDatesForm_{$count}#", 'error', $contents );
			else:
				$contents = str_replace( "#class_addDatesForm_{$count}#", NULL, $contents );
			endif;
			$count++;
		endforeach;
		
		$contents = str_replace( '#recurrance#', $checked, $contents );
				
		echo $contents;
	}
	
	protected static function showEventForm( $externals, $roomContList, $branchList, $errorMSG = NULL )
	{
		#$roomContList = bookaroom_settings_roomConts::getRoomContList();
		#$branchList = bookaroom_settings_branches::getBranchList( TRUE );

		$filename = BOOKAROOM_PATH . 'templates/events/eventForm.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );

		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		#$roomContList = bookaroom_settings_roomConts::getRoomContList();
		#$branchList = bookaroom_settings_branches::getBranchList( TRUE );
		
		# errors
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		endif;
		# branch list
		self::makeBranchList( $externals['branchID'], $externals['roomID'], $contents, $branchList, $roomContList );
		
		echo $contents;
	}	
	
	protected static function showAttendanceSuccess( )
	{
		$filename = BOOKAROOM_PATH . 'templates/events/showAttendanceSuccess.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		echo $contents;
		
	}
}
?>