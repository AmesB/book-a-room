<?PHP
class bookaroom_settings_cityManagement
{
	public static function bookaroom_admin_mainCityManagement()
	{
		$cityList = self::getCityList();
		# figure out what to do
		# first, is there an action?
		$externals = self::getExternals();
		
		switch( $externals['action'] ):
			case 'deleteCheck':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['cityID'], $cityList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'cities/IDerror' );
				else:
					# show delete screen
					$roomList		= bookaroom_settings_rooms::getRoomList();
					self::deleteCity( $externals['cityID'] );
					bookaroom_settings::showMessage( 'cities/delete_success' );
				endif;

				break;
			
			case 'delete':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['cityID'], $cityList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'cities/IDerror' );
				else:
					# show delete screen
					$cityInfo = self::getCityInfo( $externals['cityID'] );
					self::showCityDelete(  $cityInfo );
				endif;
				
				break;

			case 'editCheck':
				# check entries
				if( ( $errors = self::checkEditCity( $externals, $cityList ) ) == NULL ):
					self::editCity( $externals );
					bookaroom_settings::showMessage( 'cities/editSuccess' );

					break;	
				endif;
				
				$externals['errors'] = $errors;
				
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['cityID'], $cityList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'cities/IDerror' );
				else:
					# show edit screen
					self::showCityFormEdit( $externals, 'editCheck', 'Edit' );
				endif;
				
				break;
			
			case 'edit':
				# check that there is an ID and it is valid
				if( bookaroom_settings::checkID( $externals['cityID'], $cityList ) == FALSE ):
					# show error page
					bookaroom_settings::showMessage( 'cities/IDerror' );
				else:
					# show edit screen
					$cityInfo = self::getCityInfo( $externals['cityID'] );
					self::showCityFormEdit( $cityInfo, 'editCheck', 'Edit' );
				endif;
				
				break;
				
			case 'addCheck':
				# check entries
				if( ( $errors = self::checkEditCity( $externals, $cityList ) ) == NULL ):
					self::addCity( $externals );
					bookaroom_settings::showMessage( 'cities/add_success' );

					break;	
				endif;
				
				$externals['errors'] = $errors;
				# show edit screen
				self::showCityFormEdit( $externals, 'addCheck', 'Add' );
				break;
							
			case 'add':
				self::showCityFormEdit( $externals, 'addCheck', 'Add' );
				break;
			
			default:
				self::showCityFormList( $cityList );
				break;
				
		endswitch;
	}
	
	public static function addCity( $externals )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_cityList";
				
		$final = $wpdb->insert( $table_name, array( 	'cityDesc' => $externals['cityDesc'] ) );

		return true;
	}
	
	public static function checkEditCity( &$externals, $cityList )
	# check the name for duplicates or empty
	{
		$final = NULL;
		$error = array();
		
		# check for empty city name
		if( empty( $externals['cityDesc'] ) ):
			$error[] = 'You must enter an city name.';	
		endif;
		
		# check dupe name
		if( bookaroom_settings::dupeCheck( $cityList, $externals['cityDesc'], $externals['cityID'] ) == 1 ):
			$error[] = 'That city name is already in use. Please choose another.';	
		endif;
		
		# if errors, implode and return error messages

		if( count( $error ) !== 0 ):
			$final = implode( "<br />", $error );
		endif;
		
		return $final;

	}
	
	public static function deleteCity( $cityID )
	# add a new branch
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_cityList";
		
		$sql = "DELETE FROM `{$table_name}` WHERE `cityID` = '{$cityID}' LIMIT 1";
		
		$wpdb->query( $sql );
		
		return true;
		
	}
	
	public static function editCity( $externals )
	# change the branch settings
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_cityList";
		
		
		$final = $wpdb->update( $table_name, 
			array( 	'cityDesc' => $externals['cityDesc'], 
			 ), 			
			array( 'cityID' =>  $externals['cityID'] ) );
		
		return true;
	}
	
	public static function getCityInfo( $cityID )
	# get information about branch from daabase based on the ID
	{
		global $wpdb;
		
		$table_name = $wpdb->prefix . "bookaroom_cityList";
		
		$final = $wpdb->get_row( $wpdb->prepare( "SELECT cityDesc FROM `$table_name` WHERE `cityID` = %d", $cityID ) );

		$cityInfo = array( 'cityDesc' => $final->cityDesc, 'cityID' => $cityID );
		
		return $cityInfo;
	}
		
	public static function getCityList()
	# get a list of all of the available cities. 
	# Return NULL on no cities
	# otherwise, return an array with the unique ID of each city
	# as the key and the description as the val
	{
		global $wpdb;
		$table_name = $wpdb->prefix . "bookaroom_cityList";
		
		$sql = "SELECT `cityID`, `cityDesc` FROM `$table_name` ORDER BY `cityDesc`";
		$count = 0;
		
		$cooked = $wpdb->get_results( $sql, ARRAY_A );
		if( count( $cooked ) == 0 ):
			return NULL;
		endif;
		
		foreach( $cooked as $key => $val ):
			$final[$val['cityID']] = $val['cityDesc'];
		endforeach;
		
		return $final;
 	}		
	
	public static function getExternals()
	# Pull in POST and GET values
	{
		$final = array();
		
		# setup GET variables
		$getArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'cityID'				=> FILTER_SANITIZE_STRING,);

		# pull in and apply to final
		if( $getTemp = filter_input_array( INPUT_GET, $getArr ) ):
			$final += $getTemp;
		endif;

		# setup POST variables
		$postArr = array(	'action'				=> FILTER_SANITIZE_STRING,
							'cityID'				=> FILTER_SANITIZE_STRING, 
							'cityDesc'				=> FILTER_SANITIZE_STRING,);
	
	
	
		# pull in and apply to final
		if( $postTemp = filter_input_array( INPUT_POST, $postArr ) ):
			$final += $postTemp;
		endif;

		$arrayCheck = array_unique( array_merge( array_keys( $getArr ), array_keys( $postArr ) ) );
		
		foreach( $arrayCheck as $key ):
			if( empty( $final[$key] ) ):
				$final[$key] = NULL;
			else:
				$final[$key] = trim( $final[$key] );
			endif;
		endforeach;
		
		return $final;
	}
	
	public static function showCityFormEdit( $cityInfo, $action, $actionName )
	# show edit page and fill with values
	{
		$filename = BOOKAROOM_PATH . 'templates/cities/edit.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		# show errors if any
		$error_line = repCon( 'error', $contents );
		
		if( empty( $cityInfo['errors'] ) ):
			$contents = str_replace( '#error_line#', NULL, $contents );
		else:
			$contents = str_replace( '#error_line#', $error_line, $contents );
			$contents = str_replace( '#errorMsg#', $cityInfo['errors'], $contents);
		endif;
		
		# fill in values
		$contents = str_replace( '#action#', $action, $contents );
		$contents = str_replace( '#actionName#', $actionName, $contents);
		$contents = str_replace( '#cityDesc#', $cityInfo['cityDesc'], $contents );
		$contents = str_replace( '#cityID#', $cityInfo['cityID'], $contents );
		
		echo $contents;	
	}
	
	public static function showCityDelete( $cityInfo )
	# show delete page and fill with values
	{
		# get template
		$filename = BOOKAROOM_PATH . 'templates/cities/delete.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		$contents = str_replace( '#cityDesc#', $cityInfo['cityDesc'], $contents );
		$contents = str_replace( '#cityID#', $cityInfo['cityID'], $contents );
		
		echo $contents;
	}
	
	public static function showCityFormList( $cityList )
	# show edit page and fill with values
	{
		$filename = BOOKAROOM_PATH . 'templates/cities/mainAdmin.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
				# create some and none lines and check for current cities
		$some_line = repCon( 'some', $contents );
		$line_line = repCon( 'line', $some_line );
		$none_line = repCon( 'none', $contents, TRUE );

		if( count( $cityList ) == 0 ):
		# if none, replace with none line
			$contents = str_replace( '#some_line#', $none_line, $contents );
		else:
		# if some, replace with some line and loop to display each one
			$contents = str_replace( '#some_line#', $some_line, $contents );

			$temp = NULL;
			$final = array();
			$count = 0;

			foreach( $cityList as $key => $val ):
				$temp = $line_line;
				
				$bg_class = ($count++%2) ? 'odd_row' : 'even_row';
				$temp = str_replace( '#bg_class#', $bg_class, $temp );
				
				$temp = str_replace( '#cityID#', $key, $temp );
				$temp = str_replace( '#cityDesc#', $val, $temp );
				
				$final[] = $temp;
			endforeach;
			
			$contents = str_replace( '#line_line#', implode( "\r\n", $final ), $contents );
		endif;
		
		echo $contents;	
	}
}
?>