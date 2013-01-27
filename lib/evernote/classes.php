<?php

/**
 * @author Jason F. Irwin
 * @copyright 2012
 * 
 * Class contains extended Classes for Evernote
 */
require_once( LIB_DIR . '/functions.php');

class evernoteNoteSortOrder {
	const CREATED = 1;
	const UPDATED = 2;
	const RELEVANCE = 3;
	const UPDATE_SEQUENCE_NUMBER = 4;
	const TITLE = 5;
}