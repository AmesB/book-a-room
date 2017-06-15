<?PHP
class bookaroom_help 
{

	public static function showHelp()
	{
		$filename = BOOKAROOM_PATH . 'templates/help/help.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );	
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
	
	public static function showHelp_setup()
	{
		$filename = BOOKAROOM_PATH . 'templates/help/help_setup.html';
		$handle = fopen( $filename, "r" );
		$contents = fread( $handle, filesize( $filename ) );
		fclose( $handle );	
		
		$contents = str_replace( '#pluginLocation#', plugins_url( '', __FILE__ ), $contents );
		
		echo $contents;
	}
}
?>