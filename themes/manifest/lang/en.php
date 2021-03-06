<?php

/**
 * @author Jason F. Irwin
 * @copyright 2012
 * 
 * Class contains the English language strings for use in the Manifest Theme
 * 
 * Change Log
 * ----------
 * 2012.10.07 - Created Class (J2fi)
 */
require_once( LIB_DIR . '/functions.php' );

class theme_en implements lang_base {
    var $labels;

    function __construct( $Custom = array() ) {
        $this->labels = $this->_fillLabels( $Custom );
    }

    public function getLangCd() {
        return $this->labels['lang_cd'];
    }

    public function getLangName() {
        return $this->labels['lang_name'];
    }
    
    public function getStrings() {
        return $this->labels;
    }

    private function _fillLabels( $Custom ) {
        $rVal = array('lang_name'       => "English",
                      'lang_cd'         => "EN",

                      'SiteName'		=> "Noteworthy Lives!",
                      'SiteDescr'		=> "A Demo of a Clean and Simple Noteworthy Theme",
                      'lblEnableJS'		=> "Please Enable JavaScript to View This Site",

                      'lblHome'         => "Home",
                      'lblAbout'        => "About Me",
                      'lblArchive'      => "Archives",
                      'lblContact'      => "Contact",
                      'lblRSS'          => "RSS",
                      'lblLink'         => "Links",
                      'lblSitemap'      => "Sitemap",
                      'lblSearch'       => "Search",
                      'lblFeedTitle'    => "Subscribe to My RSS Feed",
                      'lblTweet'        => "Tweet",
                      'lblElsewhere'	=> "Elsewhere",

                      'lblPage'         => "Page",
                      'lblNextPage'     => "Next Page",
                      'lblPrevPage'     => "Previous Page",
                      'lblLandPage'     => "Landing Page",
                      'lblGeoTag'       => "Geo-Tagged",
                      'lblReadMore'     => "Read More...",

                      'lblPoweredBy'    => "Powered By <a href=\"http://dematigo.com/projects#Noteworthy\" title=\"Noteworthy\">Noteworthy</a>.",
                      'lblAllRights'    => "All Rights Reserved",
                      'lblMyName'       => "",

                      );

        // Add any Custom Labels that are Required
        if ( count($Custom) > 0 ) {
            foreach( $Custom as $key=>$val ) {
                $rVal[ "[$key]" ] = $val;
            }
        }

        // Return the Completed Array
        return $rVal;
    }
}

?>