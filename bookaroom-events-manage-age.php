<?PHP
class bookaroom_settings_age
{
	public static function showFormAge() {		
		$externals = self::getExternals();
		#$error = NULL;
		
		switch( $externals['action'] ):
			case 'addCheck':
				# check for errors
				if( ( $errorMSG = self::checkNewAge( $externals['newName'] ) ) == TRUE ):
					self::showForm( $externals, $errorMSG );
					break;
				endif;
				self::addGroup( $externals['newName'] );
				self::showForm( NULL, "You have successfully added the group {$externals['newName']}" );
				break;
			
			case 'moveTop':
			case 'moveBottom':
			case 'moveDown':
			case 'moveUp':
				# check that ID is valid and active
				$error = self::moveGroup( $externals['action'], $externals['groupID'] );
				self::showForm( $externals, $error );
				break;
			
			case 'edit':
				# check that ID is valid and active
				if( ( $error = self::checkID( $externals['groupID'], 'edit' ) ) == TRUE ):
					self::showForm( $externals, $error );
				else:
					$nameList = self::getNameList();
					self::showEdit( $nameList['active'][$externals['groupID']]['age_desc'], $externals['groupID'] );
				endif;

				break;
			case 'editCheck':
				# check that ID is valid and active
				if( ( $error = self::checkID( $externals['groupID'], 'edit' ) ) == TRUE ):
					self::showForm( $externals, $error );
					break;
				else:
					
					$nameList = self::getNameList();
					if( ( $error = self::checkEdit( $externals['groupID'], $externals['newName'] ) ) == TRUE ):
						self::showEdit( $externals['newName'], $externals['groupID'], $error );
					else:
						self::editGroup( $externals['newName'], $externals['groupID'] );
						$nameList = self::getNameList();
						self::showForm( array(), 'You have successfully edited an age group.' );
					endif;
				endif;

				break;

			case 'deactivate':
				# check that ID is valid and active
				if( ( $error = self::checkID( $externals['groupID'], 'deactivate' ) ) == TRUE ):
					self::showForm( $externals, $error );
				else:
					$nameList = self::getNameList();
					self::showActivateChangeCheck( $nameList['active'][$externals['groupID']]['age_desc'], $externals['groupID'], 'deactivate' );
				endif;
				break;
			
			case 'deactivateFinal':
				if( ( $error = self::checkHash( $externals['hash'], $externals['time'], $externals['groupID'], 'active' ) ) == TRUE ):
					$nameList = self::getNameList();
					self::showActivateChangeCheck( $nameList['active'][$externals['groupID']]['age_desc'], $externals['groupID'], 'deactivate', $error );
				else:
					self::changeActivation( $externals['groupID'], 0 );
					self::showForm( $externals, 'You have successfully deactivated an age group.' );
				endif;
				break;

			case 'reactivate':
				# check that ID is valid and active
				if( ( $error = self::checkID( $externals['groupID'], 'reactivate' ) ) == TRUE ):
					self::showForm( $externals, $error );
				else:
					$nameList = self::getNameList();
					self::showActivateChangeCheck( $nameList['inactive'][$externals['groupID']]['age_desc'], $externals['groupID'], 'reactivate' );
				endif;
				break;
			
			case 'reactivateFinal':
				if( ( $error = self::checkHash( $externals['hash'], $externals['time'], $externals['groupID'], 'inactive' ) ) == TRUE ):
					$nameList = self::getNameList();
					self::showActivateChangeCheck( $nameList['active'][$externals['groupID']]['age_desc'], $externals['groupID'], 'reactivate', $error );
				else:
					self::changeActivation( $externals['groupID'], 1 );
					self::showForm( $externals, 'You have successfully deactivated an age group.' );
				endif;
				break;

										
			default:
				self::showForm( $externals );
				break;
		endswitch;
	}
	
	protected static function changeActivation( $groupID, $value )
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_event_ages";
		
		$wpdb->update( $table_name, array( 'age_active' => $value), array( 'age_id' => $groupID ) );
		
		return TRUE;
	}
	
	protected static function checkHash( $hash, $time, $groupID, $type )
	{
		$nameList = self::getNameList();
	
		$newHash = md5( $groupID . $time . $nameList[$type][$groupID]['age_desc'] );

		if( $newHash !== $hash ):
			return 'There has been a security error. Please try again.';
		endif;
		
		return FALSE;
	}
	
	protected static function showActivateChangeCheck( $groupName, $groupID, $type, $errorMSG = NULL )
	{
		switch( $type ):
			case 'deactivate':
				$filename = BOOKAROOM_PATH . 'templates/events/age_form_deactivate.html';
				break;
			case 'reactivate':
				$filename = BOOKAROOM_PATH . 'templates/events/age_form_reactivate.html';
				break;
			default:
				die( 'error: wronge activation change type.' );
				break;
		endswitch;
		
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# error line
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		endif;


		$contents = str_replace( '#groupName#', $groupName, $contents );
		$contents = str_replace( '#groupID#', $groupID, $contents );
		
		# make hash
		$time = time();
		
		$hash = md5( $groupID . $time . $groupName );
		$contents = str_replace( '#hash#', $hash, $contents );
		$contents = str_replace( '#time#', $time, $contents );
		echo $contents;
	}
		
	protected static function editGroup( $newName, $groupID )
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_event_ages";
		
		$wpdb->update( $table_name, array( 'age_desc' => $newName ), array( 'age_id' => $groupID ) );
		
		return TRUE;
	}
	
	protected static function checkEdit( $groupID, $newName )
	{
		# get namelist	
		$nameList = self::getNameList();
		
		# check for dupe
		if( ( $isDupe = array_search( strtolower( $newName ), $nameList['all'] ) ) == TRUE ):
			# if dupe but same ID, error that you didn't change it
			if( $groupID == $isDupe ):
				return 'You haven\'t made any changes.';
			else:
				if( true == $nameList['status'][$isDupe] ):
					return "The name you've entered is in use in an <em><strong>active</strong></em> age group already.";
				else:
					return "The name you've entered is in use in an <em><strong>inactive</strong></em> age group already. You can reactivate it in the bottom menu.";
				endif;
			# remember to filter against $curID if not empty so you		
			endif;
		endif;

		return FALSE;
	}
	
	protected static function addGroup( $newName )
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_event_ages";

		# get row count
		$sql = "SELECT MAX( `age_order`) AS `highCount` FROM `$table_name` ";
		
		$newOrderArr = $wpdb->get_results( $sql, ARRAY_A );
		$newOrder = $newOrderArr[0]['highCount'];

		$wpdb->insert( 		$table_name, 
							array( 
								'age_desc' => $newName,
								'age_order' => ++$newOrder
							), 
							array( 
								'%s',
								'%d'
							) 
						);
		return TRUE;
	}
	
	protected static function checkID ( $groupID, $action )
	{
		# setup vars
		$final = NULL;
		$nameList = self::getNameList();
		
		#check if ID exists	
		if( !array_key_exists( $groupID, $nameList['all'] ) ):
			return 'That ID doesn\'t exist.';
		endif;
		
		switch( $action ):
			case 'edit':
				# check if ID active
				if( !array_key_exists( $groupID, $nameList['active'] ) ):
					return "If you would like to edit this age group. Please <a href=\"?page=bookaroom_event_settings&action=reactivate&groupID={$groupID}\">reactivate</a> it first.";
				endif;
				break;
			
			case 'deactivate':
				if( !array_key_exists( $groupID, $nameList['active'] ) ):
					return 'This age group is already inactive. You cannot deactivate it.';
				endif;
				break;

			case 'reactivate':
				if( array_key_exists( $groupID, $nameList['active'] ) ):
					return 'This age group is already active. You cannot reactivate it.';
				endif;
				
				break;		
		endswitch;
		
		return $final;
	}
	
	protected static function showEdit( $newName, $groupID, $errorMSG = NULL )
	{
		$filename = BOOKAROOM_PATH . 'templates/events/age_form_edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# error line
		$error_line = repCon( 'error', $contents );
		
		if( empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );
		endif;
		
		
		# swap
		$contents = str_replace( '#newName#', $newName, $contents );
		$contents = str_replace( '#groupID#', $groupID, $contents );
		
		
		echo $contents;
	}
	
	protected static function moveGroup( $moveType, $moveID )
	{
		# valid move type?
		$goodArr = array( 'moveUp', 'moveDown', 'moveTop', 'moveBottom' );
		if( !in_array( $moveType, $goodArr ) ):
			return 'You have chosen an invalid move type. Please try again.';
		endif;
		
		# get name list
		$nameList = self::getNameList();
		
		# check for valid id
		if( !array_key_exists( $moveID, $nameList['status'] ) ):
			return 'The ID you\'ve tried to move doesn\'t exist.';
		endif;
		
		# check if inactive
		if( $nameList['status'][$moveID] == FALSE ):
			return 'The group you\'re trying to move is inactive and can\'t be reordered.';
		endif;
		

		# get entire list and find current position.
		$curList = array_values( $nameList['order'] );

		# if move up or top, error if first line
		if( in_array( $moveType, array( 'moveUp', 'moveTop' ) ) ): 		
			if( ( $curPos = array_search( $moveID, $curList ) ) == 0 ):
				return 'The group you\'re trying to move is the first in the list and can go no higher.';
			endif;
		endif;
		
		# if move down or bottom, error
		if( in_array( $moveType, array( 'moveDown', 'moveBottom' ) ) ): 
			if( ( $curPos = array_search( $moveID, $curList ) ) == ( count( $curList ) - 1 ) ):
				return 'The group you\'re trying to move is the last in the list and can go no lower.';	
			endif;
		endif;
				
		switch( $moveType ):
			case 'moveUp':		
				$holder = $curList[$curPos];
				$curList[$curPos] = $curList[$curPos-1];
				$curList[$curPos-1] = $holder;
				break;
			
			case 'moveTop': 
				$holder = $curList[$curPos];
				unset( $curList[$curPos] );
				$curList = array_merge( array( $holder ), $curList );
				break;
				
			case 'moveDown':
				$holder = $curList[$curPos];
				$curList[$curPos] = $curList[$curPos+1];
				$curList[$curPos+1] = $holder;
				break;

			case 'moveBottom': 
				$listCount = count( $curList ) - 1;
				$holder = $curList[$curPos];
				unset( $curList[$curPos] );
				$curList = array_merge( $curList, array( $holder ) );
				break;
				
		endswitch;
						
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_event_ages";
		
		$count = 1;
		foreach( $curList as $val ):
			$wpdb->update( $table_name, array( 'age_order' => $count++ ), array( 'age_id' => $val ) );
		endforeach;
		
		return NULL;	
	}
	
	protected static function checkNewAge( $newAgeName, $curID = NULL )
	{
		$errors = array();
		$errorMSG = FALSE;
		
		# check for empty
		if( empty( $newAgeName ) ):
			$errors[] = "You haven't entered a new age category. Please try again.";
		else:
		
			$nameList = self::getNameList();
			
			# check if live or inactive group shared the same name
			if( $dupeID = array_search( strtolower( trim( $newAgeName ) ), $nameList['all'] ) ):
				if( true == $nameList['status'][$dupeID] ):
					$errors[] = "The name you've entered is in use in an <em><strong>active</strong></em> age group already.";
				else:
					$errors[] = "The name you've entered is in use in an <em><strong>inactive</strong></em> age group already. You can reactivate it in the bottom menu.";
				endif;
			# remember to filter against $curID if not empty so you		
			endif;
		
		endif;
		
		# check for errors
		if( count( $errors ) !== 0 ):
			array_walk( $errors, function( &$value ) {
				$value = "<p>{$value}</p>";
			});
			$errorMSG = implode( "\r\n", $errors );
			
		endif;

		return $errorMSG;
		
	}
	
	public static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();

		# setup GET variables
		$getArr = array(	'action'					=> FILTER_SANITIZE_STRING, 
							'groupID'					=> FILTER_SANITIZE_STRING, 
							'hash'						=> FILTER_SANITIZE_STRING, 
							'time'						=> FILTER_SANITIZE_STRING );

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) )
			$final = array_merge( $final, $getTemp );

		# setup POST variables
		$postArr = array(	'action'					=> FILTER_SANITIZE_STRING,
							'newName'					=> FILTER_SANITIZE_STRING,
							'groupID'					=> FILTER_SANITIZE_STRING );
	
	

		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) )
			$final = array_merge( $final, $postTemp );

		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			elseif( is_array( $final[$key] ) ):
				$final[$key] = array_keys( $final[$key] );
			else:			
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;
				
		return $final;
	}
	
	public static function getNameList()
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_event_ages";
		$sql = "SELECT `age_id`, `age_desc`, `age_order`, `age_active` FROM `$table_name` ";
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		
		$final = array( 'active' => array(), 'inactive' => array(), 'all' => array(), 'status' => array(), 'order' => array() );
			
		foreach( $cooked as $key => $val ):
			$active = ( empty( $val['age_active'] ) ) ? 'inactive' : 'active';
			$final[$active][$val['age_id']]		= $val;
			$final['all'][$val['age_id']]		= strtolower( $val['age_desc'] );
			$final['status'][$val['age_id']]	= $val['age_active'];
			$final['order'][$val['age_order']]	= $val['age_id'];
		endforeach;
		
		ksort( $final['order'] );
		
		return $final;
	}
	
	protected static function showForm( $externals, $errorMSG = NULL )
	{
		$filename = BOOKAROOM_PATH . 'templates/events/age_form.html';	
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		#error line
		$error_line = repCon( 'error', $contents );
		
		if( !empty( $errorMSG ) ):
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMSG#', $errorMSG, $contents );			
		else:
			$contents = str_replace( '#error_line#', NULL, $contents );
		endif;
		
		# groups list
		$nameList = self::getNameList();
		
		# ------------------------------------------------------
		# active 
		# ------------------------------------------------------
		# get lines
		$active_line = repCon( 'active', $contents );
		$activeNone_line = repCon( 'activeNone', $contents, TRUE );
		

		if( count( $nameList['active'] ) == false ):
			$contents = str_replace( '#active_line#', $activeNone_line, $contents );
		else:
			$temp = NULL;
			$final = array();

			$keyList = array_values( array_intersect( $nameList['order'], array_keys( $nameList['active'] ) ) );

			foreach( $keyList as $key => $val ):
				$temp = $active_line;
				$linkLine = self::makeLinks( $val, $key, count( $keyList ) );
				$temp = str_replace( '#linkLine#', $linkLine, $temp );
				
				$temp = str_replace( '#groupName#', $nameList['active'][$val]['age_desc'], $temp );
				$temp = str_replace( '#groupID#', $nameList['active'][$val]['age_id'], $temp );
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#active_line#', implode( "\r\n", $final ), $contents );
		endif;

		# ------------------------------------------------------		
		# inactive
		# ------------------------------------------------------		
		$inactive_line = repCon( 'inactive', $contents );
		$inactiveNone_line = repCon( 'inactiveNone', $contents, TRUE );
		
		if( count( $nameList['inactive'] ) == false ):
			$contents = str_replace( '#inactive_line#', $inactiveNone_line, $contents );
		else:
			$temp = NULL;
			$final = array();
			
			asort( $nameList['all'] );
			$keyList = array_intersect( array_keys( $nameList['all'] ), array_keys( $nameList['inactive'] ) );
			
			
			foreach( $keyList as $val ):
				$temp = $inactive_line;
				$temp = str_replace( '#groupName#', $nameList['inactive'][$val]['age_desc'], $temp );
				$temp = str_replace( '#groupID#', $nameList['inactive'][$val]['age_id'], $temp );
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#inactive_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		# ------------------------------------------------------
		# add new
		# ------------------------------------------------------
		$contents = str_replace( '#newName#', $externals['newName'], $contents );
		

		
		echo $contents;
	}
	
	protected static function makeLinks( $groupID, $curPos, $topPos )
	{

		$link = array();
		# if there top?
		switch( $curPos):
			case 0:
				# top line
				$link[] = 'Top';
				$link[] = 'Move Up';
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveDown&groupID={$groupID}\">Move Down</a>";
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveBottom&groupID={$groupID}\">Bottom</a>";
				break;
				
			case ( $curPos == ( $topPos - 1 ) ):
				# bottom line
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveTop&groupID={$groupID}\">Top</a>";
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveUp&groupID={$groupID}\">Move Up</a>";
				$link[] = 'Move Down';
				$link[] = 'Bottom';
				break;
				
			default:
				# any other lines
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveTop&groupID={$groupID}\">Top</a>";
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveUp&groupID={$groupID}\">Move Up</a>";
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveDown&groupID={$groupID}\">Move Down</a>";
				$link[] = "<a href=\"?page=bookaroom_event_settings&action=moveBottom&groupID={$groupID}\">Bottom</a>";
				break;
		endswitch;
		
		$final = implode( ' | ', $link );
		
		return $final;			
	}
}
?>