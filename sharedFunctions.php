<?PHP
function branchListDrop( $contents, $cur_roomID, $cur_branchID, $cur_noloc_branchID, $branchList, $roomContList,$selectBranch = FALSE, $noLocation = FALSE, $noneMessage = 'Do Not Filter' )
#
# This function will replace a templated option in $contents with a branch drop down list
# of options.
#
####
#
# only ONE of the following three should be true:
# $cur_roomID:			the ID if the current room, if selected
# $cur_branchID:		the ID of the branch, if selected
# $cur_noloc_branchID : the ID of the branch if no-location is selected for that branch
#
####
#
# $contents:     the contents to which we will be adding the branch drop list.
#
# $cur_roomID:   the POST return of the room ID from the form
#
# $selectBranch: if true, branches will be selectable. False means they appear but
#                can't be chosen
#
# $noLocation:   allow selection and viewing of branches that allow "no location needed"
{
	# requirements
	require_once( BOOKAROOM_PATH . 'bookaroom-meetings-branches.php' );
	require_once( BOOKAROOM_PATH . 'bookaroom-meetings-roomConts.php' );
	
	# pull branch and room line from template
	$branch_line = repCon( 'branch', $contents );
	$room_line = repCon( 'room', $contents );
	
	# set up other variables
	$final = array();
	
	# Default entry: no filter chosen or invalid filter chosen.
	$temp = $room_line;
	$temp = str_replace( '#roomVal#', NULL, $temp );
	$selected = ( empty( $cur_branchID ) ) ? ' selected="selected"' : NULL;
	$temp = str_replace( '#roomDesc#', $noneMessage, $temp );
	$temp = str_replace( '#roomSelected#', $selected, $temp );
	$temp = str_replace( '#roomSelectedClass#', NULL, $temp );
	$final[] = $temp;

	# cycle through each branch
	foreach( $branchList as $key => $val ):
		# replace common variables
		$branchName = $val['branchDesc'];
		$temp = $room_line;
		$temp = str_replace( '#roomVal#', 'branch-'.$key, $temp );
		$selected = ( $cur_branchID == $val['branchID'] ) ? ' selected="selected"' : NULL;
		$temp = str_replace( '#roomDesc#', $branchName, $temp );
		$temp = str_replace( '#roomSelected#', $selected, $temp );
		
		# if selectBranch is true, then replace class with disabled and remove "disabled" function
		if( $selectBranch == true ):
			$temp = str_replace( '#roomSelectedClass#', ' class="disabled"', $temp );
			$temp = str_replace( '#roomDisabled#', NULL, $temp );
		# if selectBranch is replace, then remove class and add "disabled" function
		else:
			$temp = str_replace( '#roomSelectedClass#', NULL, $temp );
			$temp = str_replace( '#roomDisabled#', ' disabled="disabled"', $temp );
		endif;	
		
		# add this branch to the drop down
		$final[] = $temp;
		
		# add the noLocation line if enabled
		if( $noLocation == true ):
			$temp = $room_line;
			if( true == $val['branch_hasNoloc'] ):
				$selected = ( $cur_noloc_branchID == $val['branchID'] ) ? ' selected="selected"' : NULL;
				$temp = str_replace( '#roomVal#', 'noloc-'.$val['branchID'], $temp );
				$temp = str_replace( '#roomDesc#', '&nbsp;&nbsp;&nbsp;&nbsp;'.$branchName.' - No location required', $temp );
				$temp = str_replace( '#roomSelected#', $selected, $temp );
				$temp = str_replace( '#roomSelectedClass#', ' class="noloc"', $temp );
				$final[] = $temp;
			endif;
		endif;
		
		# get all room conts
		$curRoomList = $roomContList['branch'][$val['branchID']];
		
		if( empty( $curRoomList ) ) $curRoomList = array();
		
		# replace values for each room
		foreach( $curRoomList as $roomContID ):
			
			$temp = $room_line;
			
			$selected = ( $cur_roomID == $roomContID ) ? ' selected="selected"' : NULL;
			$temp = str_replace( '#roomVal#', $roomContID, $temp );
			$temp = str_replace( '#roomDesc#', '&nbsp;&nbsp;&nbsp;&nbsp;'.$roomContList['id'][$roomContID]['desc'].'&nbsp;['.$roomContList['id'][$roomContID]['occupancy'].']', $temp );
			$temp = str_replace( '#roomSelected#', $selected, $temp );
			$temp = str_replace( '#roomDisabled#', NULL, $temp );
			$final[] = $temp;

		endforeach;
	endforeach;
			
	# replace template with new drop down options
	$contents = str_replace( '#room_line#', implode( "\r\n", $final ), $contents );
	
	$returnVal = array( 'roomID' => $cur_roomID, 'branchID' => $cur_branchID, 'noloc_branchID' => $cur_noloc_branchID );
	
	if( empty( $cur_roomID ) and empty( $cur_branchID ) and empty( $cur_noloc_branch ) ):
		$returnVal['hasLocation'] = NULL;		
	else:
		$returnVal['hasLocation'] = true;
	endif;
	
	return $contents;
}

function branch_and_room_id( &$roomID, $branchList, $roomContList )
# Convert
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

function getRoomInfo( $cur_roomID = NULL, $branchList, $roomContList, $selectBranch = FALSE, $noLocation = FALSE )
{
	$cur_branchID = $cur_noloc_branchID = NULL;
	# this section handles the 'no location' and entire branch selections.
	#
	# selectBranch is true, you can select the branch
	#
	# if noLocation is true, you can see and select a 'No location' for each
	# branch

	# Let's find out if, and what, room, location or branch is selected.
	#
	# first, lets see if there is a room ID selected and check that it's valid.
	if( !empty( $cur_roomID ) ):
		# we have an ID. Lets see if noLocation is turned on and, if so, lets see
		
		
		# is no location enabled?
		if( $noLocation == true ):
			# is this is a "no location" room ID?
			if( substr( $cur_roomID, 0, 6 ) == 'noloc-' ):
				# we have a no location ID. Lets find the branch
				$noLocBranchID = substr( $cur_roomID, 6 );
				# is it a valid branch? compare against the branch list keys
				if( array_key_exists( $noLocBranchID, $branchList ) ):
					$cur_noloc_branchID = (int)$noLocBranchID;
					$cur_roomID = NULL;
				endif;
			endif;
		endif;
		# is selectBranch enabled?
		if( $selectBranch == true ):
			# is this a 'branch' room ID?
			if( substr( $cur_roomID, 0, 7 ) == 'branch-' ):
				# we have a branch selection. Lets find the branch ID
				$branchID = substr( $cur_roomID, 7 );
				# is it a valid branch? compare against the branch list keys
				if( array_key_exists( $branchID, $branchList ) ):
					$cur_branchID = (int)$branchID;
					$cur_roomID = NULL;
				endif;
			endif;
		endif;
	endif;

	return array( 'cur_roomID' => $cur_roomID, 'cur_branchID' => $cur_branchID, 'cur_noloc_branchID' => $cur_noloc_branchID );
}

function make_brief($mess, $length=50)
# cut off text to nearest word based on length
{
	$mess = strip_tags($mess);
	if(!is_numeric($length) || strlen($mess) <= $length)
	{
		return $mess;
	}
	
	$mess = substr($mess, 0, $length);
	
	$chop = strrchr($mess, " ");
	$final = substr($mess, 0, strlen($mess) - strlen($chop)) . "...";
	return $final;
}

function br2nl($string)
{
    return preg_replace("/<br[^>]*>\s*\r*\n*/is", "\n", $string);
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
			@session_start();
		}
	}
endif;
	if( !function_exists( "myEndSession" ) ):
	function myEndSession() {
		@session_destroy();
	}
endif;
/*
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
*/
function makeLink_correctPermaLink() {
	$permStruc = get_option( 'permalink_structure' );
	$resURL = get_option( 'bookaroom_reservation_URL' );
	if( empty( $permStruc ) ):
		return '?page_id=' . $resURL . '&';		
	else:
		return $resURL . '?';
	endif;
}
?>