<?php

/**
*
*
* @filename:		    language.class.php
* @filetype:		    PHP
* @description:	    This language class and help in translation from one language key
* 									value array to another.
* @version:			    2.4.13
* @author(s):			  JAY & AMA
* @authoremail(s):  evolutioneerbeyond@yahoo.com & j.ilukhor@gmail.com
* @twitter:         @deusex0
*                   @One_Oracle
* @lastmodified:    02/04/2013 11:58:15
* @license:         http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
* @copyright:       Copyright (c) 2013 Jencube
* @usage:
* @supportfile(s):
*
*
*/

class Language {

	public $userLang;
	private $defaultLang = 'en-us';
	private $langFile;
	private $langDir = 'lang/';
	private $langFileExtension = '.php';
	private $error = array();

	public function __construct( $lang = NULL ) {
		if ( empty( $lang['dir'] ) )
			$lang['dir'] = dirname(__FILE__) . '/' . $this->langDir . $this->userLang . $this->langFileExtension;

		$this->userLang = ( !empty( $lang['lang'] ) )? $lang['lang'] : $this->defaultLang;
		$this->langFile = $lang['dir'] . '/' . $this->userLang . $this->langFileExtension;
		ini_set('default_charset', 'utf-8');
	}

	public function get_available_list(){
		$langFileList = "";
		if($handle = opendir($this->langDir)){
		    while (false !== ($file = readdir($handle))){
		        if ($file != "." && $file != ".." && strtolower(substr($file, strrpos($file, '.') + 1)) == substr($this->langFileExtension, strrpos($this->langFileExtension, '.') + 1)){
		            // $langFileList .= '<li><a href="'.$path.$file.'">'.substr_replace($file, "", strrpos($file, '.')).'</a> : </li>';
		            $langFileList[] = substr_replace($file, "", strrpos($file, '.'));
		        }
		    }
		    closedir($handle);
		}
		return $langFileList;
	}

	private function lang_exist(){
		if ( !file_exists( $this->langFile ) ) {
			$this->error[] = 'Language file does not exists <em>'.$this->langFile.'</em>';
			return FALSE;
		}
		return TRUE;
	}

  public function details( $keyword ) {
    include( $this->langFile );

    if ( ( array_key_exists( $keyword, $lang ) ) ) {
      return $lang[$keyword];
    }
    return FALSE;
  }

	public function translate( $keyword, $value = NULL, $needle = NULL ){
		if ( empty($keyword) ) {
			$this->error[] = 'Please enter a keyword';
			return FALSE;
		}

		if ( !$this->lang_exist() ) {
			$this->langFile = dirname(__FILE__) . '/' . $this->langDir . $this->defaultLang . $this->langFileExtension;
		}

		include( $this->langFile );

		if ( ( array_key_exists( $keyword, $lang ) ) ) {
	      return ( !empty( $needle ) )? $this->replace( $needle, $value, $lang[$keyword] ) : $lang[$keyword];
	      // return ( !empty( $needle ) )? $this->replace_new( $needle, $lang[$keyword] ) : $lang[$keyword];
	    } else {
	      return ( !empty( $needle ) )? $this->replace( $needle, $value, $this->translate_to( $keyword ) ) : $this->translate_to( $keyword );
	      // return ( !empty( $needle ) )? $this->replace_new( $needle, $this->translate_to( $keyword ) ) : $this->translate_to( $keyword );
	    }

	}

	private function translate_to( $keywords ) {
		if ( empty( $keywords ) ) {
			$this->error[] = 'Please enter a keyword';
			return FALSE;
		}

		include( $this->langFile );

		$specialChar = array( '"', ',', '.', '-', '\'', '*', ';', '<', '>', '(', ')', '[', ']', '?', '$', '@', '!', '£', '#' );
		$cleanKeywords = trim( str_replace( $specialChar,' ', $keywords ) );
		$keywordArray = explode( " ", $cleanKeywords );
		$arrayCount = count( $keywordArray );
		$translated = $keywords;
		for( $i = 0; $i < $arrayCount; $i++ ) {
			if ( array_key_exists( $keywordArray[$i], $lang ) )
				$translated = str_replace( $keywordArray[$i], $lang[$keywordArray[$i]], $translated) ;
		}

		return $translated;
	}

	// In new version upgrade this to accept an array value
	// to enable it replace multiple values in the language files
	private function replace( $needle, $value = NULL, $haystack ) {
		return str_replace( $needle, $value, $haystack );
	}

	// New replace_new method not currently been used
	private function replace_new( $replacements = array(), $haystack ) {
		if ( !is_array( $replacements ) )
			return FALSE;

		if ( empty( $haystack ) )
			return FALSE;

		foreach ( $replacements as $needle => $replace ) {
			$haystack = str_replace( $needle, $replace, $haystack );
		}
		return $haystack;
	}

	public function errors(){
		foreach($this->error as $key => $value)
			return $value;
	}
}

?>