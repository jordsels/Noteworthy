<?php

/**
 * @author Jason F. Irwin
 * @copyright 2012
 * 
 * Class contains the rules and methods called for Application Settings Data
 */
require_once(LIB_DIR . '/functions.php');

class Settings extends Midori {
    var $settings;
    var $messages;
    var $errors;

    function __construct( $settings ) {
        $this->settings = $settings;
        $this->errors = array();
        $this->messages = getLangDefaults( $this->settings['DispLang'] );
    }

    /***********************************************************************
     *  Public Functions
     ***********************************************************************/
    /**
     *	Determine Which Sets of Data Need to be Updated based on the Content and
     *		run the necessary Functions. Return a Boolean When Done.
     */
    function update() {
	    $rVal = array( 'isGood'	 => false,
	    			   'Message' => '',
	    			  );
	    $isGood = false;
	    $Errs = "";

	    switch ( NoNull($this->settings['dataset']) ) {
	    	case 'settings':
	    		// Update the Database and Debug Settings
		    	$isGood = $this->_createDBFile();
	    		break;

	    	case 'email':
	    		// Update the Email Settings
	    		$isGood = $this->_saveEmailConfig();
	    		break;

	    	case 'email-adminurl':
	    		// Send an Email Reminder of the Administration URL
	    		$isGood = $this->_emailAdminLink();
	    		if ( $isGood ) {
		    		$rVal['Message'] = $this->messages['lblEmailSent'];
	    		}
	    		break;

	    	case 'createdb':
	    		// Create the Database
	    		$isGood = $this->_createDBTables();
	    		break;
	    	
	    	case 'updatedb':
	    		// Update the Database
	    		$isGood = $this->_updateDBTables();
	    		break;

	    	case 'dashboard':
	    		// Update Some of the Dashboard Settings (?)
	    		break;

	    	case 'evernote':
	    		// Update the Evernote Data (If Necessary)
	    		break;

	    	case 'lists':
	    		// Update Some of the List Data
	    		break;

	    	case 'about':
	    		// Update the 'About Me' Data
	    		break;

		    case 'sites':
		    	// Update the Main Site Data
		    	$isGood = $this->_saveSiteData();
		    	break;
		    
		    case 'logout':
		    	// Delete the Server-Side Browser Reference
		    	$isGood = $this->_doLogOut();
		    	break;

		    case 'social':
		    	$isGood = $this->_saveSocialData();
		    	break;

		    default:
		    	// Do Nothing
	    }

	    // Set the Return Message
    	if ( $isGood ) {
    		$rVal['isGood'] = BoolYN( $isGood );
    		if ( $rVal['Message'] == '' ) {
		    	$rVal['Message'] = NoNull($this->messages['lblSetUpdGood'], "Successfully Updated Settings");    		
    		}

    	} else {
    		foreach ( $this->errors as $Key=>$Msg ) {
    			if ( $Errs != "" ) { $Errs .= "<br />\r\n"; }
	    		$Errs .= $Msg;
    		}
	    	$rVal['Message'] = $Errs;
    	}

	    // Return a Boolean Response
	    return $rVal;
    }

    /***********************************************************************
     *
     *
     *  Private Functions
     *
     *
     ***********************************************************************/

    /***********************************************************************
     *  Database
     ***********************************************************************/
    /**
     *	Function Records the Database Values if there are differences and returns
     *		a Boolean response
     */
    private function _createDBFile() {
	    $rVal = false;
	    
	    $DBType = nullInt( $this->settings['cmbStoreType'] );
	    $DBInfo = array();
	    $DBServ = NoNull( $this->settings['txtDBServ'], DB_SERV );
	    $DBName = NoNull( $this->settings['txtDBName'], DB_NAME );
	    $DBUser = NoNull( $this->settings['txtDBUser'], DB_USER );
	    $DBPass = NoNull( $this->settings['txtDBPass'], DB_PASS );
	    $isDebug = nullInt( $this->settings['cmbDebugMode'] );

	    // Validate the MySQL Login (If Necessary)
	    if ( $DBType == 1 ) {
	    	writeNote( "Testing DB Settings ..." );
		    $DBInfo = $this->_testSQLSettings( $DBServ, $DBName, $DBUser, $DBPass );
		    if ( !$DBInfo['LoginOK'] ) { $this->errors[] = NoNull($this->messages['lblSetUpdErr001'], "One or More Invalid MySQL Settings"); }
	    }

	    // Record the Data
	    if ( $DBInfo['LoginOK'] ) {
		    $rVal = $this->_saveDBConfigData( $DBType, $DBServ, $DBName, $DBUser, $DBPass, $isDebug );
		    if ( $rVal ) {
			    // Create the Tables if Necessary
			    if ( !$DBInfo['TableOK'] ) {
				    $rVal = $this->_createDBTables();
				    if ( !$rVal ) { $this->errors[] = NoNull($this->messages['lblSetUpdErr004'], "Could Not Populate Database"); }
			    }
		    } else {
		    	$this->errors[] = NoNull($this->messages['lblSetUpdErr002'], "Could Not Save Configuration Data");
		    }
	    }

	    // Return a Boolean Response
	    return $rVal;
    }

    /**
     *	Function Tests the SQL Login Data passed and Returns a Boolean Response
     */
    private function _testSQLSettings( $DBServ, $DBName, $DBUser, $DBPass ) {
    	writeNote( "_testSQLSettings( $DBServ, $DBName, $DBUser, $DBPass )" );
    	$rVal = array( 'LoginOK' => false,
    				   'TableOK' => false,
    				  );
	    $r = 0;

	    if ( $DBServ == "" || $DBName == "" || $DBUser == "" || $DBPass == "" ) {
	    	$this->errors[] = NoNull($this->messages['lblSetUpdErr001'], "One or More Invalid MySQL Settings");
		    return $rVal;
	    }

	    // Test the Connection
	    $sqlStr = "SHOW TABLES;";
        $db = mysql_connect($DBServ, $DBUser, $DBPass);
        $selected = mysql_select_db($DBName, $db);
        $utf8 = mysql_query("SET NAMES " . DB_CHARSET);
        $result = mysql_query($sqlStr);

        if ( $result ) {
        	// Mark the Login as OK
        	$rVal['LoginOK'] = true;

            // Read the Result into an Array
            $ColName = "Tables_in_$DBName";
            while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
            	if ( $this->_isValidSQLTable( NoNull($row[$ColName]) ) ) {
	            	$r++;
            	}
            }

            // Close the MySQL Connection
            mysql_close( $db );
        }

        // Ensure the Database has 4 Valid Tables
        if ( $r == 4 ) {
	        $rVal['TableOK'] = true;
        }

        // Return the Status
        return $rVal;
    }

    /**
     *	Ensure the TableName is Valid
     *		Note: In a future version this should also check the Table Columns to ensure
     *			  they are up to date (or compatible) with the running version
     */
    private function _isValidSQLTable( $TableName ) {
	    $valid = array( 'Content', 'Meta', 'SysParm', 'Type' );
	    $rVal = false;

	    if ( in_array($TableName, $valid) ) { $rVal = true; }
	    
	    // Return the Boolean Status
	    return $rVal;
    }

    /**
     * Function Saves a Setting with a Specific Token to the Temp Directory
     */
    private function _saveDBConfigData( $DBType, $DBServ, $DBName, $DBUser, $DBPass, $isDebug ) {
    	// Perform some VERY basic Validation here
    	if ( $isDebug < 0 || $isDebug > 1 ) { $isDebug = 0; }
    	if ( $DBType < 1 || $DBType > 2 ) { $DBType = 2; }
    	$rVal = false;

	    // Check to see if the Settings File Exists or Not
	    if ( checkDIRExists( CONF_DIR ) ) {
		    $ConfFile = CONF_DIR . '/config-db.php';
		    $ConfData = "<?php \r\n" .
		    			"\r\n" .
		    			"   /** ************************************* **\r\n" .
		    			"    *  DO NOT CHANGE THESE SETTINGS MANUALLY  *\r\n" .
		    			"    *  These Settings are Controlled Through  *\r\n" .
		    			"    *  Your Noteworthy Admin / Settings Page  *\r\n" .
		    			"    ** ************************************* **/\r\n" .
		    			"define('DB_SERV', '$DBServ');\r\n" .
		    			"define('DB_MAIN', '$DBName');\r\n" .
		    			"define('DB_USER', '$DBUser');\r\n" .
		    			"define('DB_PASS', '$DBPass');\r\n" .
		    			"define('DB_CHARSET', 'utf8');\r\n" .
		    			"define('DB_COLLATE', 'UTF8_UNICODE_CI');\r\n" .
		    			"define('DB_TYPE', $DBType);\r\n" .
		    			"define('DEBUG_ENABLED', $isDebug);\r\n" .
		    			"\r\n" .
		    			"?>";

		    // Write the File to the Configuration Folder
		    $fh = fopen($ConfFile, 'w');
		    fwrite($fh, $ConfData);
		    fclose($fh);

			// Set a Happy Return Boolean		    
		    $rVal = true;

	    } else {
		    writeNote( "The Configuration File [" . CONF_DIR . "] Either Does Not Exist and Cannot Be Created, or is Not Writable" );
	    }

	    // Return a Boolean Response
	    return $rVal;
    }

    /**
     *	Create the Tables
     *	Note: $doTruncate will (of course) first Truncate the Tables if they Exist
     */
    private function _createDBTables( $doTruncate = false ) {
    	$Actions = $this->_readSQLInstallScript( $doTruncate );
    	$DBName = DB_MAIN;
	    $rVal = false;

	    if ( $DBName != "" && is_array($Actions) ) {
		    foreach ( $Actions as $Key=>$sqlStr ) {
		    	$sqlDo = str_replace( "[DBNAME]", $DBName, $sqlStr );
			    doSQLExecute($sqlDo);
		    }

		    // Update to the Most Current Version (If Required)
		    $rVal = $this->_updateDBTables();
	    }

	    // Return the Success Value
	    return $rVal;
    }
    
    /**
     *	Function Updates the Database to the Current Version
     */
    private function _updateDBTables() {
    	$CurrVer = 0;
	    $rVal = false;

	    // Determine the current Database Version
	    $sqlStr = "SELECT `intVal` FROM `SysParm` WHERE `isDeleted` = 'N' and `Code` = 'DB_VERSION';";
	    $rslt = doSQLQuery( $sqlStr );
	    if ( is_array($rslt) ) {
		    $CurrVer = nullInt($rslt[0]['intVal']);
	    }

	    // Collect the Necessary SQL Update Statements
	    for ( $i = $CurrVer; $i < DB_VER; $i++ ) {
		    $sqlList = $this->_readSQLInstallScript( false, $i + 1 );

		    if ( is_array($sqlList) ) {
			    foreach( $sqlList as $sqlStr ) {
				    $sqlDo = str_replace( "[DBNAME]", DB_MAIN, $sqlStr );
				    doSQLExecute($sqlDo);
			    }

			    // If We're Here, Chances Are It's Good
			    $rVal = true;
		    } else {
			    $this->error[] = "Problem With the SQL Update Script";
		    }
	    }

	    // Return the Boolean Response
	    return $rVal;
    }

    /**
     *	Read the SQL install.php file into an array
     */
    private function _readSQLInstallScript( $doTruncate = false, $Version = 0 ) {
    	$SQLFile = BASE_DIR . "/sql/install.sql";
    	if ( $Version > 0 ) {
	    	$SQLFile = BASE_DIR . "/sql/update" . substr("0000" . $Version, -4) . ".sql";
    	}
	    writeNote( "Reading SQL Scripts File: $SQLFile" );
	    $rVal = array();
	    $i = 0;

	    // Add the Table Truncation Lines (if Requested)
	    // -- This Needs to be Done Better; SHOW TABLES followed by a TRUNCATE --
	    if ( $doTruncate ) {
		    $trunks = array( 'Type', 'Meta', 'Content', 'SysParm' );
		    foreach ( $trunks as $tbl ) {
			    $rVal[$i] = "TRUNCATE TABLE IF EXISTS `[DBNAME]`.`$tbl`;";
			    $i++;
		    }
	    }

	    // Add the Main Table Definitions & Populations
    	if ( file_exists($SQLFile) ) {
	    	$lines = file($SQLFile);

	    	foreach ( $lines as $line ) {
	    		$rVal[$i] .= $line;

	    		// If there is a Semi-Colon, The Line is Complete
	    		if ( strpos($line, ';') ) { $i++; }
	    	}

    	} else {
    		$rVal = false;
	    	$this->error[] = "SQL File Missing!";
    	}

    	// Return the Array of SQL Strings
    	return $rVal;
    }

    /***********************************************************************
     *  Log Out
     ***********************************************************************/
    /**
     *	Function Calls the User/Logout Function and Returns a Boolean
     */
    private function _doLogOut() {
	    $rVal = false;

	    // Fire Up the User Class
	    require_once( LIB_DIR . '/user.php' );
	    $usr = new User( $this->settings['token'] );
	    $rVal = $usr->doLogout();

	    // Return the Boolean Response
	    return $rVal;
    }

    /***********************************************************************
     *  Email
     ***********************************************************************/
    /**
     *	Function Records Email Settings to the appropriate Configuration File
     */
    private function _saveEmailConfig() {
	    $data = array( 'EmailOn'	 => NoNull($this->settings['cmbEmail'], 'N'),
	    			   'EmailServ'   => NoNull($this->settings['txtMailHost']),
		               'EmailPort'   => intval($this->settings['txtMailPort']),
		               'EmailUser'	 => NoNull($this->settings['txtMailUser']),
		               'EmailPass'	 => NoNull($this->settings['txtMailPass']),
		               'EmailSSL'	 => NoNull($this->settings['cmbMailSSL'], 'N'),
		               'EmailSendTo' => NoNull($this->settings['txtMailSendTo']),
		               'EmailReplyTo' => NoNull($this->settings['txtMailReply']),
		              );

		// Record the Data Accordingly
		foreach ( $data as $Key=>$Val ) {
			saveSetting( 'core', $Key, $Val );
		}

		// Return the Boolean Response
		return true;
    }
    
    /**
     *	Function Emails the Admin Link to the Currently Logged In User
     */
    private function _emailAdminLink() {
    	$rVal = false;
    	
    	if ( array_key_exists('token', $this->settings) ) {
    		require_once( LIB_DIR . '/email.php' );
	    	require_once( LIB_DIR . '/user.php' );
	    	$usr = new User( $this->settings['token'] );
	    	
	    	$AdminURL = $this->settings['HomeURL'] . "/" . $this->settings['adminCode'] . "/";
	    	$Items = array( 'inptEmail'   => $usr->EmailAddr(),
	    					'inptName'    => '',
	    					'inptMessage' => "This is an automated message from " . $this->settings['SiteName'] . "<br />\r\n" .
						    			     "Your Administration screens can be found at: $AdminURL <br />\r\n" .
						    			     "<br />\r\n" .
						    			     "Be sure to keep this email in a safe place so you don't forget.",
						    'PgSub1'		  => "send",
						    );

			// Prepare the Email Class
			$email = new Email( $Items );
			$data = $email->perform();

			// Check the Results
			if ( $data['isGood'] == 'Y' ) {
				$rVal = true;
			}
    	}

    	// Return the Boolean Response
    	return $rVal;
    }

    /***********************************************************************
     *  Sites
     ***********************************************************************/
    /**
     *	Function Records Site Data to the appropriate Configuration File
     */
    private function _saveSiteData() {
    	$isDefault = ($this->settings['chkisDefault'] == "on") ? 'Y' : 'N';
    	$SiteID = nullInt( $this->settings['dispSiteID'], $this->settings['SiteID'] );
    	$RebuildCache = ( $this->settings['txtLocation'] != $this->settings['Location'] ) ? true : false;
    	$HomeURL = NoNull( $this->settings['txtHomeURL'] );
    	$apiURL = ( $HomeURL != "" ) ? "$HomeURL/api/" : "";
    	$CacheToken = "Site_$SiteID";
	    $rVal = false;

	    $data = array('require_key'		=> 'Y',

	    			  'HomeURL'			=> $HomeURL,
	    			  'api_url'			=> $apiURL,

		              'Location'        => $this->settings['txtLocation'],
		              'isDefault'       => $isDefault,

		              'SiteName'		=> $this->settings['txtSiteName'],
		              'SiteDescr'		=> $this->settings['txtSiteDescr'],
		              'SiteSEOTags'		=> $this->settings['txtSiteSEO'],

		              'doComments'		=> $this->settings['raComments'],
		              'doWebCron'		=> $this->settings['raWebCron'],
		              'DisqusID'     	=> $this->settings['txtDisqusID'],
		              'AkismetKey'		=> $this->settings['txtAkismetKey'],
		              'doTwitter'		=> $this->settings['raTwitter'],
		              'TwitName'		=> $this->settings['txtTwitName'],

		              'EN_ENABLED'		=> 'Y',
		              );

		// Record the Data Accordingly
		foreach ( $data as $Key=>$Val ) {
			saveSetting( $CacheToken, $Key, $Val );
		}

		if ( $RebuildCache ) {
			$rVal = scrubDIR( $this->settings['ContentDIR'] . '/cache' );
			$HomeURL = $this->settings['HomeURL'];
			$Pages = array( "/", "/archives/", "/rss/" );
			foreach ( $Pages as $Page ) {
				$cache = fopen($HomeURL . $Page, "r");
			}
		}
		
		// Save the Site Master
		$DefaultSite = -1;
		if ( $isDefault ) { $DefaultSite = $SiteID; }
		$rVal = $this->_saveSiteMaster( $DefaultSite );

		// Return a Boolean Response
		return $rVal;
    }
    
    /**
     *	Function Saves the Sites Master File and Returns a Boolean
     */
    private function _saveSiteMaster( $DefaultSite = -1 ) {
    	$Sites = getSitesList();
    	$data = array();

	    if ( $DefaultSite >= 0 ) { $setDefault = true; }
	    foreach ( $Sites as $SiteID ) {
	    	$dtl = getSiteDetails($SiteID);
		    $data[ $SiteID ] = $dtl['HomeURL'];
		    if ( $DefaultSite < 0 && $dtl['isDefault'] == 'Y' ) {
			    $DefaultSite = $SiteID;
		    }
		    saveSetting( "Site_$SiteID", 'isDefault', 'N' );
	    }
	    
	    // Set the Default Accordingly
	    saveSetting( "Site_$DefaultSite", 'isDefault', 'Y' );
	    $data[$DefaultSite] = 'default';

		// Record the Data Accordingly
		foreach ( $data as $Key=>$Val ) {
			saveSetting( 'SiteMaster', $Key, $Val );
		}

	    // Return a Happy Boolean
	    return true;
    }

    /**
     *	Function Records Social Data to the appropriate Configuration File
     */
    private function _saveSocialData() {
    	$SiteID = nullInt( $this->settings['SiteID'] );
    	$CacheToken = "Site_$SiteID";

	    $data = array('SocName01'		=> $this->settings['txtSocName01'],
		              'SocLink01'		=> $this->settings['txtSocLink01'],
		              'SocShow01'		=> ($this->settings['chkSocShow01'] == "on") ? 'Y' : 'N',
		              
		              'SocName02'		=> $this->settings['txtSocName02'],
		              'SocLink02'		=> $this->settings['txtSocLink02'],
		              'SocShow02'		=> ($this->settings['chkSocShow02'] == "on") ? 'Y' : 'N',
		              
		              'SocName03'		=> $this->settings['txtSocName03'],
		              'SocLink03'		=> $this->settings['txtSocLink03'],
		              'SocShow03'		=> ($this->settings['chkSocShow03'] == "on") ? 'Y' : 'N',
		              
		              'SocName04'		=> $this->settings['txtSocName04'],
		              'SocLink04'		=> $this->settings['txtSocLink04'],
		              'SocShow04'		=> ($this->settings['chkSocShow04'] == "on") ? 'Y' : 'N',
		              
		              'SocName05'		=> $this->settings['txtSocName05'],
		              'SocLink05'		=> $this->settings['txtSocLink05'],
		              'SocShow05'		=> ($this->settings['chkSocShow05'] == "on") ? 'Y' : 'N',
		              );

		// Record the Data Accordingly
		foreach ( $data as $Key=>$Val ) {
			saveSetting( $CacheToken, $Key, $Val );
		}

		// Return a Happy Boolean Response
		return true;
    }

}

?>