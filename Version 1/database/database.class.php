<?php

/**
*
*
* @filename:		    mysql.class.php
* @filetype:		    PHP
* @description:	    This database class has a clean and common methods that works
*					          with various types of database (such as: msSQL, mySQL, mySQLi,
*					          postgres). It has a cache system integrated along side
*					          making very effective and powerful database class..
* @version:			    1.1.13
* @author(s):			  JAY & AMA
* @authoremail(s):  evolutioneerbeyond@yahoo.com & j.ilukhor@gmail.com
* @twitter:         @deusex0
*                   @One_Oracle
* @lastmodified:    16/01/2013 09:35:30
* @license:         http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
* @copyright:       Copyright (c) 2013 Jencube
* @usage:
* @supportfile(s):
*
*
*/


class Database {
	private $conn = 0;
	var $query = 0;
	var $queryTime = 0;
	var $queryCount = 0;
	var $queryData;
	var $queryDebugList = array();
	var $sql;
	protected $dbType;
	protected $dbHost;
	protected $dbName;
	protected $dbUser;
	protected $dbPass;
	protected $dbPort = 0;
	protected $remote = 0;
	protected $dbCharset;
	protected $dbCollate;
	var $error = array();
	var $logData;
	var $logDir;
	protected $cache;
	var $cacheDir;
	var $cacheAge;
	var $cacheFile;
	var $cacheMod;
	var $numRows = 0;
	var $numFields = 0;

	public function __construct( $config = array() ) {
		$this->logData = "Database() Initialized<br />";
		$this->logDir = (!empty($config['log_path']) && $this->check_directory($config['log_path']))? $config['log_path'] : '/log';
		$this->dbType = (isset($config['db_type']))? $config['db_type'] : 'mysql';
		$this->dbHost = ($config['db_host'])? $config['db_host'] : 'localhost';
		$this->dbName = $config['db_name'];
		$this->dbUser = $config['db_user'];
		$this->dbPass = $config['db_pass'];
		$this->remote = $config['remote'];
		$this->dbPort = ($config['remote'])? $config['port'] : '3360';
		$this->cache = (!empty($config['cache']))? $config['cache'] : FALSE;
		$this->cacheDir = (!empty($config['cache_path']) && $this->check_directory($config['cache_path']))? $config['cache_path'] : '/cache';
		$this->cacheAge = (($config['cache'] == TRUE) && !empty($config['cache_age']))? $config['cache_age'] : 300;
    if ( !$this->is_db_connected() ) {
		$this->connect();
    }
	}

	private function connect(){
    $this->logData .= "connect() called<br />";

		switch($this->dbType){
			case "mysql":
				if(!$this->remote){
					$this->conn = @mysql_connect( $this->dbHost, $this->dbUser, $this->dbPass );
				} else {
					$this->conn = @mysql_connect($this->dbHost.':'.$this->port,$this->dbUser,$this->dbPass);
				}
				if(!$this->conn){
					$this->logData .= "mysql_connect() failed<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Unable to connect to database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mysqli":
				if(!$this->remote){
					$this->conn = @mysqli_connect($this->dbHost,$this->dbUser,$this->dbPass);
				} else {
					$this->conn = @mysqli_connect($this->dbHost.':'.$this->port,$this->dbUser,$this->dbPass);
				}
				if(!$this->conn){
					$this->logData .= "mysqli_connect() failed<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Unable to connect to database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mssql":
				if(!$this->remote){
					$this->conn = @mssql_connect($this->dbHost,$this->dbUser,$this->dbPass);
				} else {
					$this->conn = @mssql_connect($this->dbHost.':'.$this->port,$this->dbUser,$this->dbPass);
				}
				if(!$this->conn){
					$this->logData .= "mssql_connect() failed<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Unable to connect to database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
      case "postgres":
        if(!$this->remote){
          $this->conn = @pg_connect($this->dbHost,$this->dbUser,$this->dbPass);
        } else {
          $this->conn = @pg_connect($this->dbHost.':'.$this->port,$this->dbUser,$this->dbPass);
        }
        if(!$this->conn){
          $this->logData .= "pg_connect() failed<br />";
          $this->logData .= $this->sql_error()."<br />";
          $this->error[] = "Unable to connect to database: <em>".$this->sql_error()."</em><br />";
          return FALSE;
        }
      break;
		}

    return $this->select_db();

	}

  private function sql_error() {
    $errormsg = "";

    switch($this->dbType){
      case "mysql":
        $errormsg = @mysql_error();
      break;
      case "mysqli":
        $errormsg = @mysqli_error($this->conn);
      break;
      case "mssql":
        $errormsg = @mssql_get_last_message();
      break;
      case "postgres":
        $errormsg = @pg_last_error($this->conn);
      break;
    }

    return $errormsg;
  }

  private function sql_erno() {
    $errorno = "";

    switch( $this->dbType ) {
      case "mysql":
        $errorno = @mysql_errno($this->conn);
      break;
      case "mysqli":
        $errorno = @mysqli_errno($this->conn);
      break;
      case "mssql":
        $errorno = @mssql_errno($this->conn);
      break;
      case "postgres":
        $errorno = @pg_last_errno($this->conn);
      break;
    }

    return $errorno;
  }

	public function select_db( $db = NULL ){
		$this->logData .= "select_db() called<br />";

    if ( !empty( $db ) )
      $this->dbName = $db;

		switch($this->dbType){
			case "mysql":
				if(!@mysql_select_db($this->dbName,$this->conn)){
					$this->logData .= "Could not select database named ".$this->dbName."<br />";
					$this->logData .= mysql_error()."<br />";
					$this->error[] = "Could not select database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mysqli":
				if(!@mysqli_select_db($this->conn,$this->dbName)){
					$this->logData .= "Could not select database named ".$this->dbName."<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Could not select database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mssql":
				if(!@mssql_select_db($this->dbName,$this->conn)){
					$this->logData .= "Could not select database named ".$this->dbName."<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Could not select database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "postgres":
				if(!$this->remote){
					$this->conn = @pg_connect("host=$this->dbHost dbname=$this->dbName user=$this->dbUser password=$this->dbPass");
				} else {
					$this->conn = @pg_connect("host=$this->dbHost port=$this->port dbname=$this->dbName user=$this->dbUser password=$this->dbPass");
				}
				if(!$this->conn){
					$this->logData .= "pg_connect() failed<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Unable to connect to database: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
		}
		return TRUE;
	}

	public function is_db_connected(){
		return ( $this->conn ) ? TRUE : FALSE;
	}

	public function query( $sql = NULL ){
		$this->logData .= "query() called<br />";
		$this->sql = ( empty( $sql ) )? $this->sql : $sql;
		$this->logData .= "Query: ".$this->sql."<br />";

		if( !$this->is_db_connected() ) {
			if( !$this->connect() ){
				$this->logData .= "Database connection does not exist.<br />";
				$this->error[] = "Database connection does not exist<br />";
				return FALSE;
			}
		}

		switch($this->dbType){
			case "mysql":
				if(!$this->query = @mysql_query( $this->sql, $this->conn ) ) {
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Could not run query: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mysqli":
				if( !$this->query = @mysqli_query( $this->conn, $this->sql ) ) {
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Could not run query: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mssql":
				if( !$this->query = @mssql_query( $this->sql, $this->conn ) ) {
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= mssql_error()."<br />";
					$this->error[] = "Could not run query: <em>".mssql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "postgres":
				if( !$this->query = @pg_query( $this->conn, $this->sql ) ) {
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= pg_last_error()."<br />";
					$this->error[] = "Could not run query: <em>".pg_last_error()."</em><br />";
					return FALSE;
				}
			break;
		}

    $this->numRows = $this->num_rows();
		return $this->query;
    unset( $sql );
	}

  // array( tablename => 'NOT NULL', fieldid => 'id', limit => '', where => '`name` = 'james' AND `privilege` = 'user'', type => '0')
  // Contributed by akinas.com => mysql_random_row

  public function rand_query( $data ) {
    $this->logData .= "rand_query() called<br />";
    $this->logData .= "Query: ".$this->sql."<br />";

    if(!$this->is_db_connected()){
      if(!$this->connect()){
        $this->logData .= "Database connection does not exist.<br />";
        $this->error[] = "Database connection does not exist<br />";
        return FALSE;
      }
    }

    switch ( $data['type'] ) {
      case '0':
        // 4th Slowest 100% of time to execute
        $where = ( isset( $data['where'] ) && !empty( $data['where'] ) )? "WHERE " . $data['where'] : "";
        $randQuery = $this->query("
          SELECT " . $data['fields'] . "
          FROM `" . $data['tablename'] . "`
          " . $where . "
          ORDER BY RAND()
          LIMIT " . $data['limit'] . "
          ");
        break;
      case '1':
        // 3rd - 79% of time to execute
        // Continuous change, no repetition
        $where = ( isset( $data['where'] ) && !empty( $data['where'] ) )? "WHERE " . $data['where'] : "";
        $andWhere = ( !empty( $data['where'] ) )? "AND " . $where : "";
        $queryRange = $this->query("
          SELECT MAX( `" . $data['fieldid'] . "` ) AS maxid, MIN( `" . $data['fieldid'] . "` ) AS minid
          FROM `" . $data['tablename'] . "`
          " . $where . "
          ");
        $fetchRange = $this->fetch_object( $queryRange );
        $randID = mt_rand( $fetchRange->minid, $fetchRange->maxid );
        $randQuery = $this->query("
          SELECT " . $data['fields'] . "
          FROM `" . $data['tablename'] . "`
          WHERE `" . $data['fieldid'] . "` >= " . $randID . " " . $andWhere . "
          LIMIT " . $data['limit'] . "
          ");
        break;
      case '2':
        // 2nd - 16% of time to execute
        // Repetition
        $where = ( isset( $data['where'] ) && !empty( $data['where'] ) )? "WHERE " . $where : "";
        $andWhere = ( !empty( $data['where'] ) )? "AND " . $where : "";
        $randQuery = $this->query("
          SELECT " . $data['fields'] . "
          FROM `" . $data['tablename'] . "`
          WHERE `" . $data['fieldid'] . "` >= ( SELECT FLOOR( MAX( " . $data['fieldid'] . " ) * RAND() )
            FROM `" . $data['tablename'] . "` " . $where . " )
          " . $andWhere . "
          ORDER BY " . $data['fieldid'] . "
          LIMIT " . $data['limit'] . "
          ");
        break;
      case '3':
        // 1st - Fastest; 13% of time to execute
        // COntinuous change, no repetition
        $where = ( !empty( $data['where'] ) )? "WHERE " . $data['where'] : "";
        $offsetResult = $this->query("
          SELECT FLOOR( RAND() * COUNT(*) ) AS offset
          FROM `" . $data['tablename'] . "`
          " . $where . "
          ");
        $offsetFetch = $this->fetch_object( $offsetResult );
        $offset = $offsetFetch->offset;
        $randQuery = $this->query("
          SELECT " . $data['fields'] . "
          FROM `" . $data['tablename'] . "`
          " . $where . "
          LIMIT " . $offset . ', ' . $data['limit'] . "
          ");
        break;
    }
    return $randQuery;
  }

	public function fetch( $type = 'object', $sql = NULL ){
		$this->logData .= "fetch() called<br />";
		// $this->sql = ( empty( $sql ) ) ? $this->sql : $sql;
		// $this->cacheFile = $this->cacheDir.'/'.md5( $this->sql ).'.cache';

    if ( $this->cache == TRUE && $this->verify_cache() ) {
      return $this->get_cache();
			// if($this->cacheAge == "0"){
			// 	return $this->fetch_option($type);
			// } else {
			// 	if($this->check_cache()){
			// 		if(date('Y-m-d',$this->cacheMod) != date('Y-m-d',time())){
			// 			$this->logData .= "cache has expired<br />";
			// 			return $this->fetch_option($type);
			// 		} else if((time() - $this->cacheMod) >= $this->cacheAge){
			// 			$this->logData .= "cache has expired<br />";
			// 			return $this->fetch_option($type);
			// 		} else {
			// 			return $this->get_cache();
			// 		}
			// 	} else {
			// 		return $this->fetch_option($type);
			// 	}
			// }
		}
		return $this->fetch_option( $type );

	}

  private function verify_cache() {
    if ($this->cacheAge == "0" ) {
      $this->logData .= "cache age not set<br />";
      return FALSE;
    } else {
      if ( $this->check_cache() ) {
        if ( date( 'Y-m-d', $this->cacheMod ) != date( 'Y-m-d', time() ) ) {
          $this->logData .= "cache has expired<br />";
          return FALSE;
        } else if ( ( time() - $this->cacheMod ) >= $this->cacheAge ) {
          $this->logData .= "cache has expired<br />";
          return FALSE;
        } else {
          return TRUE;
        }
      } else {
        return FALSE;
      }
    }
  }

	public function fetch_option( $type = 'object' ){
		if( !$this->query() ) {
			return FALSE;
		}

		switch( $type ) {
			case "object":
				return $this->fetch_object();
			break;
			case "array":
				return $this->fetch_array();
			break;
			case "row":
				return $this->fetch_row();
			break;
			case "assoc":
				return $this->fetch_assoc();
			break;
		}
		return FALSE;
	}

  private function fetch_end() {
    if($this->cache == TRUE){
      $this->set_cache();
    }
    $endTime = microtime(1);
    $this->debug_data( $startTime, $endTime, $this->queryData );
    $this->close();
  }

	public function fetch_all( $result = FALSE ) {
    $this->logData .= "fetch_all() called<br />";
    $this->cacheFile = $this->cacheDir.'/'.md5($this->sql).'.cache';
		$query = ( $result !== FALSE )? $result : $this->query;

		if ( $query === FALSE ) {
			return FALSE;
		}

    if ( $this->cache == TRUE && $this->verify_cache() ) {
      return $this->get_cache();
    } else {

      $startTime = microtime(1);

      $data = array();
  		while ( $obj = $this->fetch_object( $query ) ) {
  			$data[] = $obj;
  		}

      $endTime = microtime(1);

      $this->free_memory( $query );
      $this->close();
      if( $this->cache == TRUE ) {
        $this->queryData = $data;
        $this->set_cache();
      }
      $this->debug_data( $startTime, $endTime, $this->queryData );
      unset( $result );
    }
		return $data;
	}

  public function fetch_field( $result = FALSE ) {
    $this->logData .= "fetch_field() called<br />";
    $this->cacheFile = $this->cacheDir.'/'.md5($this->sql).'.cache';
    $this->query = ( $result !== FALSE )? $result : $this->query;

    if ( $this->query == FALSE ) {
      return FALSE;
    }

    if ( $this->cache == TRUE && $this->verify_cache() ) {
      return $this->get_cache();
    } else {

      $startTime = microtime(1);

      if ( $row = $this->fetch_row() ) {
        $data = $row[0];
      }

      $endTime = microtime(1);

      $this->free_memory( $query );
      $this->close();
      if( $this->cache == TRUE ) {
        $this->queryData = $data;
        $this->set_cache();
      }
      $this->debug_data( $startTime, $endTime, $this->queryData );
      unset( $result );
    }
    return $data;
  }

	public function fetch_assoc(){
		$this->logData .= "fetch_assoc() called<br />";
		$this->logData .= "Query: ".$this->sql."<br />";
		$this->queryData = "";
		$this->cacheFile = $this->cacheDir.'/'.md5($this->sql).'.cache';

		$startTime = microtime(1);

		switch($this->dbType){
			case "mysql":
				$this->queryData = @mysql_fetch_assoc($this->query);
			break;
			case "mysqli":
				$this->queryData = @mysqli_fetch_assoc($this->query);
			break;
			case "mssql":
				$this->queryData = @mssql_fetch_assoc($this->query);
			break;
			case "postgres":
				$this->queryData = @pg_fetch_assoc($this->query);
			break;
		}

    $endTime = microtime(1);
		$this->close();
		$this->free_memory($this->query);
    if($this->cache == TRUE){
      $this->set_cache();
    }
    $this->debug_data($startTime,$endTime,$this->queryData);
		return $this->queryData;
	}

	public function fetch_object(){
		$this->logData .= "fetch_object() called<br />";
		$this->logData .= "Query: ".$this->sql."<br />";
		$this->queryData = "";
		$this->cacheFile = $this->cacheDir.'/'.md5($this->sql).'.cache';

		$startTime = microtime(1);
		switch($this->dbType){
			case "mysql":
				$this->queryData =  @mysql_fetch_object($this->query);
			break;
			case "mysqli":
				$this->queryData = @mysqli_fetch_object($this->query);
			break;
			case "mssql":
				$this->queryData = @mssql_fetch_object($this->query);
			break;
			case "postgres":
				$this->queryData = @pg_fetch_object($this->query);
			break;
		}

    if($this->cache == TRUE){
      $this->set_cache();
    }
    $endTime = microtime(1);
    $this->debug_data( $startTime, $endTime, $this->queryData );
    $this->close();
    return $this->queryData;
	}

	public function fetch_array(){
		$this->logData .= "fetch_array() called<br />";
		$this->logData .= "Query: ".$this->sql."<br />";
		$this->cacheFile = $this->cacheDir . '/' . md5( $this->sql ) . '.cache';
    $this->queryData = "";

		$startTime = microtime(1);
		switch( $this->dbType ){
			case "mysql":
				$this->queryData = @mysql_fetch_array( $this->query, MYSQL_ASSOC );
			break;
			case "mysqli":
				$this->queryData = @mysqli_fetch_array( $this->query, MYSQLi_ASSOC );
			break;
			case "mssql":
				$this->queryData = @mssql_fetch_array( $this->query, MSSQL_ASSOC );
			break;
			case "postgres":
				$this->queryData = @pg_fetch_array( $this->query, NULL, PGSQL_ASSOC );
			break;
		}

		$endTime = microtime(1);
		$this->free_memory( $this->query );
    if( $this->cache == TRUE ){
      $this->set_cache();
    }
		$this->debug_data( $startTime, $endTime, $this->queryData );
    $this->close();
		return $this->queryData;
	}

	public function fetch_row(){
		$this->logData .= "fetch_row() called<br />";
		$this->logData .= "Query: ".$this->sql."<br />";
		$this->queryData = "";
		$this->cacheFile = $this->cacheDir.'/'.md5($this->sql).'.cache';

		$startTime = microtime(1);
		switch($this->dbType){
			case "mysql":
				$this->queryData = @mysql_fetch_row($this->query);
			break;
			case "mysqli":
				$this->queryData = @mysqli_fetch_row($this->query);
			break;
			case "mssql":
				$this->queryData = @mssql_fetch_row($this->query);
			break;
			case "postgres":
				$this->queryData = @pg_fetch_row($this->query);
			break;
		}

		$this->close();
		$endTime = microtime(1);
		$this->free_memory($this->query);
		if($this->cache == TRUE){
      $this->set_cache();
    }
		$this->debug_data($startTime,$endTime,$this->queryData);
		return $this->queryData;
	}

	public function search( $tableName, $q=NULL, $searchFields, $orderBy=NULL, $limit=NULL ) {
		$this->logData .= "search() called<br />";
		$criteria = "";
		$query = "";
		if(!isset($q) || empty($q)) {
			$query = "";
		} else {
			foreach ($searchFields as $searchFilter) {
				$criteria .= " OR `".$this->escape($searchFilter)."` LIKE '%".$this->escape($q)."%'";
			}
		}

		if(!empty($criteria)) {
			$searchQuery = trim(substr($criteria,3));
			$query = "WHERE ".$searchQuery;
		}

		if(!empty($orderBy)) {
			if(substr(strtoupper(trim($orderBy)),0,8) != 'ORDER BY'){
				$query .= " ORDER BY ".$orderBy;
			} else {
				$query .= " ".trim($orderBy);
			}
		}

		if(!empty($limit)) {
			if(substr(strtoupper(trim($limit)),0,5) != 'LIMIT'){
				$query .= " LIMIT ".$limit;
			} else {
				$query .= " ".trim($limit);
			}
		}

		$this->query("SELECT * FROM ".$tableName." ".$query);

		if($this->numRows > 0){
			return $this->fetch();
		}
		return FALSE;
	}

	public function output( $value, $type = 'jsone' ) {
		switch( $type ){
			case "jsone":
				$outputData = json_encode( $value );
			break;
			case "jsond":
				$outputData = json_decode( $value );
			break;
			case "base64e":
				$outputData = base64_encode( $value );
			break;
			case "base64d":
				$outputData = base64_decode( $value );
			break;
			case "hexe":
				$outputData = bin2hex( $value );
			break;
			case "hexd":
				$outputData = @pack( "H*", $value );
			break;
			case "uue":
				$outputData = convert_uuencode( $value );
			break;
			case "uud":
				$outputData = convert_uudecode( $value );
			break;
		}
		return $outputData;
	}

	public function _encrypt( $value, $type = 'md5' ) {
		switch( $type ) {
			case "md5":
				$outputData = md5( $value );
			break;
			case "md5d":
				$outputData = md5( md5( $value ) );
			break;
			case "sha1":
				$outputData = sha1( $value );
			break;
			case "crypt":
				$outputData = crypt( $value ); // encoding varies dependent on the OS
			break;
		}
		return $outputData;
	}

	public function insert( $tableName, $insertData ) {
		$this->logData .= "insert() called<br />";
		$fields = '';

		// retrieve the keys (column titles) of the array
		// $fields = array_keys( $insertData );

		foreach( $insertData as $key ) {
			$data[] = $this->escape( $key ); // I removed addslashes() function
		}

		foreach($insertData as $key => $value){
			$fields .= ", `".$this->escape( $key )."`";
			// $values .= ", '".$this->escape($value)."'";
		}

		// insert query
		$this->sql = "INSERT INTO `" . $tableName . "`
			(" . trim( substr( $fields, 2 ) ) . ")
			VALUES ('" . implode( "','", $data ) . "')";

		$this->logData .= "Query: ".$this->sql."<br />";

		$startTime = microtime(1);
		if ( $this->query( $this->sql ) ) {
			return TRUE;
		}
		$endTime = microtime(1);
		$this->debug_data( $startTime, $endTime, $this->sql );
    //echo $this->logData;
		return FALSE;
    unset( $tableName, $insertData );
	}

	public function update( $tableName, $updateData, $identifier = NULL ) {
		$this->logData .= "update() called<br />";
		$where = '';
		$fields = '';
		if( !empty( $identifier ) ) {
			if( substr( strtoupper( trim( $identifier ) ), 0, 5 ) != 'WHERE' ) {
				$where = " WHERE ". $identifier;
			} else {
				$where = " ". trim( $identifier );
			}
		}

		$this->sql = "UPDATE `".$tableName."` SET ";

		$data = array();
		foreach( $updateData as $column => $value ) {
			$data[] = "`". $this->escape( $column ) ."` = '". $this->escape( $value ) ."'"; // I removed addslashes() function on d value
		}
		$this->sql .= implode( ',', $data );
		$this->sql .= $where;

		$startTime = microtime(1);
		if ( $this->query( $this->sql ) ) {
			return TRUE;
		}

		$this->logData .= "Query: ".$this->sql."<br />";

		$endTime = microtime(1);
		$this->debug_data( $startTime, $endTime, $this->sql );
		return FALSE;
    unset( $tableName, $updateData, $identifier );

	}

	public function delete( $tableName, $identifier = NULL ) {
		$this->logData .= "delete() called<br />";
		$where = '';
		if( !empty( $identifier ) ) {
			if( substr( strtoupper( trim( $identifier ) ), 0, 5 ) != 'WHERE' ) {
				$where = " WHERE ". $identifier;
			} else {
				$where = " ".trim( $identifier );
			}
		}

		$this->sql = "DELETE FROM ".$tableName.$where;
		$this->logData .= "Query: ".$this->sql."<br />";

		$startTime = microtime(1);
		if( $this->query( $this->sql ) ) {
			return TRUE;
		}
		$endTime = microtime(1);
		$this->debug_data( $startTime, $endTime, $this->sql );
		return FALSE;
    unset( $tableName, $identifier );
	}

	public function num_rows( $query = NULL ) {
		$this->logData .= "num_rows() called<br />";
		$this->query = ( empty( $query ) ) ? $this->query : $query;

		if( !$this->is_db_connected() ){
			if( !$this->connect() ){
				$this->logData .= "num_rows(): Database connection does not exist.<br />";
				$this->error[] = "Database connection does not exist<br />";
				return FALSE;
			}
		}

		if ( empty( $this->query ) ) {
			return FALSE;
		}

		switch( $this->dbType ){
			case "mysql":
				if( !$this->numRows = @mysql_num_rows( $this->query ) ) {
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Error: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
				break;
			case "mysqli":
				if( !$this->numRows = @mysqli_num_rows( $this->query ) ) {
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Error: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mssql":
				if(!$this->numRows = @mssql_num_rows($this->query)){
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Error: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
				break;
			case "postgres":
				if(!$this->numRows = @pg_num_rows($this->query)){
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= $this->sql_error()."<br />";
					$this->error[] = "Error: <em>".$this->sql_error()."</em><br />";
					return FALSE;
				}
				break;
		}
		return $this->numRows;
	}

	public function num_fields(){
		$this->logData .= "num_fields() called<br />";

		if(!$this->is_db_connected()){
			if(!$this->connect()){
				$this->logData .= "num_fields(): Database connection does not exist.<br />";
				$this->error[] = "Database connection does not exist<br />";
				return FALSE;
			}
		}

		switch($this->dbType){
			case "mysql":
				if(!$this->numFields = @mysql_num_fields($this->query)){
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= mysql_error()."<br />";
					$this->error[] = "Error: <em>".mysql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mysqli":
				if(!$this->numFields = @mysqli_num_fields($this->query)){
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= mysqli_error()."<br />";
					$this->error[] = "Error: <em>".mysqli_error()."</em><br />";
					return FALSE;
				}
			break;
			case "mssql":
				if(!$this->numFields = @mssql_num_fields($this->query)){
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= mssql_error()."<br />";
					$this->error[] = "Error: <em>".mssql_error()."</em><br />";
					return FALSE;
				}
			break;
			case "postgres":
				if(!$this->numFields = @pg_num_fields($this->query)){
					$this->logData .= "Query execution failed.<br />";
					$this->logData .= pg_last_error()."<br />";
					$this->error[] = "Error: <em>".pg_last_error()."</em><br />";
					return FALSE;
				}
			break;
		}
		return TRUE;
	}

	public function insert_id(){
		$this->logData .= "insert_id() called.<br />";

		if(!$this->is_db_connected()){
			$this->logData .= "insert_id(): Database connection does not exist.<br />";
			$this->error[] = "Database connection does not exist<br />";
			return FALSE;
		}

		switch($this->dbType){
			case "mysql":
				return @mysql_insert_id();
			break;
			case "mysqli":
				return @mysqli_insert_id($this->query);
			break;
			case "mssql":
				if(!$query = @mssql_query( $this->sql, $this->conn ) ) {
					$this->logData .= "Failed to retrieve identity value.<br />";
					$this->logData .= mssql_error()."<br />";
					$this->error[] = "Failed to retrieve identity value: <em>".mssql_error()."</em><br />";
					return FALSE;
				}

				if(!$lastID = @mssql_result( $query, 0, 0 ) ) {
					$this->logData .= "Failed to retrieve identity value.<br />";
					$this->logData .= mssql_error()."<br />";
					$this->error[] = "Failed to retrieve identity value: <em>".mssql_error()."</em><br />";
					return FALSE;
				}
				return $lastID;
			break;
			case "postgres":
				return @pg_last_oid( $this->query );
			break;
		}
		return FALSE;
	}

	public function affected_rows(){
		$this->logData .= "affected_rows() called.<br />";

		if(!$this->is_db_connected()){
			$this->logData .= "affected_rows(): Database connection does not exist.<br />";
			$this->error[] = "Database connection does not exist<br />";
			return FALSE;
		}

		switch( $this->dbType ){
			case "mysql":
				return @mysql_affected_rows( $this->conn );
			break;
			case "mysqli":
				return @mysqli_affected_rows( $this->conn );
			break;
			case "mssql":
				return @mssql_rows_affected( $this->conn );
			break;
			case "postgres":
				return @pg_affected_rows( $this->query );
			break;
		}
		return FALSE;
	}

	public function table_details( $tableName, $colName = 'Field' ) {
    $this->logData .= "table_details() called.<br />";
		$columns = '';
		$query = $this->query( "SHOW COLUMNS FROM " . $tableName );
		while( $row = $this->fetch_array( $query ) ){
			$columns[] = $row->$colName;
		}
		return $columns;
	}

	public function set_charset( $char = 'utf8' ) {

		#mysql_set_charset('utf8', $link);

	}

	public function create_table( $sql ) {
    $this->logData .= "create_db() called.<br />";

    if ( empty( $sql ) )
      return FALSE;

    $query = $this->query( $sql );

    if ( $query ) {
      return TRUE;
    } else {
      return FALSE;
      $this->logData .= "Unable to create: <pre>" . $sql . "</pre><br />";
      $this->error[] = "Unable to create: <pre>" . $sql . "</pre>";
    }

	}

  public function import_sql( $file ) {
    $this->logData .= "import_sql() called.<br />";

    if(!$this->is_db_connected()){
      $this->logData .= "import_sql(): Database connection does not exist.<br />";
      $this->error[] = "Database connection does not exist<br />";
      return FALSE;
    }

    if ( !file_exists( $file ) ) {
      $this->logData .= "import_sql(): The file " . $file . " does not exist.<br />";
      $this->error[] = "The file " . $file . " does not exist.<br />";
      return FALSE;
    }

    $content = @file_get_contents( $file );
    if ( !$content ) {
      $this->logData .= "import_sql(): Unable to get the contents of the file  " . $file . ".<br />";
      $this->error[] = "Unable to get the contents of the file  " . $file . ".<br />";
      return FALSE;
    }

    $sql = explode( ';', $content );
    foreach ( $sql as $query ) {
      if ( !empty( $query ) ) {
        $result = $this->query( $query );

        if ( !$result ) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

	public function optimize( $tableName ){
		return $this->query( "OPTIMIZE TABLE `". $this->escape( $tableName ) ."` " );
	}

  public function optimize_all() {
    while ( $tables = $this->show_tables() ) {
      foreach ( $tables as $key => $tableName ) {
        if ( $this->optimize( $tableName ) ) {
          return TRUE;
        } else {
          return FALSE;
          $this->logData .= "Cannot optimize " . $tableName . " table<br />";
          $this->error[] = "Cannot optimize " . $tableName . " table";
        }
      }
    }
  }

  public function table_exists( $tableName = NULL ) {
    while ( $table = $this->show_tables() ) {
      if ( $table[0] == $tableName ) {
        return TRUE;
      } else {
        return FALSE;
        $this->logData .= $tableName . "don't exist<br />";
        $this->error[] = $tableName . "don't exist<br />";
      }

    }
    unset( $tableName );
  }

	public function show_tables() {
		$table = $this->query( "SHOW TABLES " );
    $fetch = $this->fetch_row( $table );
		if ( $fetch == FALSE )
			return $fetch;
		else
			return FALSE;
		unset( $fetch );
	}

  public function filter( $var ) {
    return preg_replace( '/[^\.\/\,\-\_\'\"\@\?\!\:\$\+ a-zA-Z0-9()]/', '', $var );
  }

	public function escape( $strs ) {
		$this->logData .= "escape() called<br />";

		if ( !$this->connect() ) {
			$this->connect();
		}

		$str = htmlspecialchars( trim( $strs ) );

		// $str = preg_replace( '/\s\s+/', ' ', trim( $strs ) );

		if( get_magic_quotes_gpc() ) {
			$str = stripslashes( $str );
		}

		$str = strip_tags( $str );

		switch( $this->dbType ) {
			case "mysql":
				return mysql_real_escape_string( $str );
			break;
			case "mysqli":
				return @mysqli_real_escape_string( $str );
			break;
			case "mssql":
				return @str_replace( "'", "''", $str );
			break;
			case "postgres":
				return @pg_escape_string( $str );
			break;
		}
		return FALSE;
	}

	private function free_memory( $query ){
		$this->logData .= "free_memory() called<br />";

		if( $this->query == NULL || $this->query === FALSE ) {
			$this->logData .= "free_memory(): Database connection does not exist.<br />";
			return FALSE;
		}

    if ( !empty( $query ) )
      $this->query = $query;

		switch( $this->dbType ){
			case "mysql":
				return @mysql_free_result( $this->query );
			break;
			case "mysqli":
				return @mysqli_free_result( $this->query );
			break;
			case "mssql":
				return @mssql_free_result( $this->query );
			break;
			case "postgres":
				return @pg_free_result( $this->query );
			break;
		}
		return FALSE;
	}

	public function close(){
		$this->logData .= "close() called<br />";

		if(!$this->is_db_connected()){
			$this->logData .= "close(): Database connection does not exist<br />";
			$this->error[] = "Database connection does not exist";
			return FALSE;
		}

		switch( $this->dbType ) {
			case "mysql":
				return @mysql_close();
			break;
			case "mysqli":
				return @mysqli_close( $this->conn );
			break;
			case "mssql":
				return @mssql_close();
			break;
			case "postgres":
				return @pg_close( $this->conn );
			break;
      $this->free_memory($this->queryData);
      $this->conn = FALSE;
		}
		return FALSE;
	}

	public function check_cache(){
		$this->logData .= "check_cache() called<br />";

		if(!file_exists($this->cacheFile)){
			$this->logData .= $this->cacheFile." does not exist<br />";
			$this->error[] = "Cache File: <em>".$this->cacheFile." does not exist</em>";
			return FALSE;
		}
		$this->cacheMod = filemtime($this->cacheFile);
		return TRUE;
	}

	public function set_cache(){
		$this->logData .= "set_cache() called<br />";

    if ( !empty( $result) )
      $this->queryData = $result;

		if( ! $cacheFile = @fopen( $this->cacheFile, "w" ) ){
			$this->logData .= "Could not open cache ".$this->cacheFile."<br />";
			$this->error[] = "Cache: <em>Could not open cache ".$this->cacheFile."/em>";
			return FALSE;
		}

		if ( ! @fwrite( $cacheFile, $this->output( $this->queryData ) ) ) {
			$this->logData .= "Could not write to cache ".$this->cacheFile."<br />";
			$this->error[] = "Cache: <em>Could not write to cache ".$this->cacheFile."/em>";
			fclose($cacheFile);
			return FALSE;
		}

		fclose( $cacheFile );
		return TRUE;
	}

	public function get_cache(){
		$this->logData .= "get_cache() called<br />";
		$this->logData .= "Query: ".$this->sql."<br />";

		$startTime = microtime(1);
		if(!$cacheFile = @file_get_contents($this->cacheFile)){
			$this->logData .= "Could not read cache ".$this->cacheFile."<br />";
			$this->error[] = "Cache: <em>Could not read cache ".$this->cacheFile."/em>";
			return FALSE;
		}

		if(!$this->queryData = json_decode($cacheFile)){
			$this->logData .= "get_cache() failed<br />";
			return FALSE;
		}
		$endTime = microtime(1);
		$this->numRows = count($this->queryData);
		$this->debug_data($startTime,$endTime,$this->queryData,'cache');
		return $this->queryData;
	}

	public function check_directory($path){
		if($handle = @opendir($path)){
			return TRUE;
			@closedir($handle);
		}
		return FALSE;
	}

	private function debug_data( $start, $end, $query, $queryType = "db" ) {
		$this->queryCount++;
		$time = number_format( $end - $start, 8);
		$this->queryTime = $this->queryTime + $time;
		$this->queryDebugList[$this->queryCount] = array(
															'Query' => $query,
															'Time' => $time,
															'Type' => $queryType
														);
	}

	public function print_result($data=NULL){
		if($data == FALSE) {
			return FALSE;
		}
		print_r($data);
	}


  public function is_empty( $value ) {
    return empty( $value );
  }

  public function is_email_valid( $email ) {
    return preg_match( '/^[a-zA-Z0-9._%-]+@([a-zA-Z0-9.-]+\.)+[a-zA-Z]{2,4}$/u', $email );
  }

  public function is_phone_valid( $number ) {
    return @ereg( "^[0-9+]+$", $number );
  }

  public function is_float_valid( $string ) {
    return preg_match( "/^[0-9]+(.[0-9]+)?$/", $string );
  }

  public function is_ipaddress( $ip ) {
  	return preg_match( '/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/', $ip );
  }

  public function get_IP() {
   $ipaddress = '';

    foreach ( array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key ) {
        if ( array_key_exists( $key, $_SERVER ) === true ) {
            foreach ( explode( ',', $_SERVER[$key] ) as $ip ) {
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
                  if ( $this->is_ipaddress( $ip ) ) {
                    $ipaddress = $ip;
                  } else {
                    $ipaddress = '0.0.0.0';
                  }
                }
            }
        }
    }

    // if ( $this->is_ipaddress( @$_SERVER['HTTP_CLIENT_IP'] ) )
    //     $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    // else if ( $this->is_ipaddress( @$_SERVER['HTTP_X_FORWARDED_FOR'] ) )
    //     $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    // else if ( $this->is_ipaddress( @$_SERVER['HTTP_X_FORWARDED'] ) )
    //     $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    // else if ( $this->is_ipaddress( @$_SERVER['HTTP_FORWARDED_FOR'] ) )
    //     $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    // else if ( $this->is_ipaddress( @$_SERVER['HTTP_FORWARDED']) )
    //     $ipaddress = $_SERVER['HTTP_FORWARDED'];
    // else if ( $this->is_ipaddress( @$_SERVER['REMOTE_ADDR'] ) )
    //     $ipaddress = $_SERVER['REMOTE_ADDR'];
    // else
    //     $ipaddress = '0.0.0.0';

    return $ipaddress;
  }

	public function generate_session_id() {
    if ( !isset( $_SESSION ) )
		  @session_start();

		@session_regenerate_id();
	}

  public function get_session( $name, $ID = NULL ) {
    if ( !isset( $_SESSION[$name] ) )
      @session_start();

    if ( empty( $ID ) && !isset( $ID ) ) {
    	return ( isset( $_SESSION[$name] ) )? $_SESSION[$name] : FALSE;
    } else {
    	return ( isset( $_SESSION[$name][$ID] ) )? $_SESSION[$name][$ID] : FALSE;
    }

  }

  public function set_session( $name, $value = NULL, $ID = NULL ) {
    if ( !isset( $_SESSION[$name] ) )
      @session_start();

    $this->generate_session_id(); // Update the current session id with a newly generated one
    if ( empty( $ID ) && !isset( $ID ) ) {
    	$_SESSION[$name] = $value;
    } else {
    	$_SESSION[$name][$ID] = $value;
    }
  }

  public function register_session( $sessionData = array(), $name = NULL ) {
    if ( !isset( $_SESSION[$name] ) )
      @session_start();

    $this->generate_session_id(); // Update the current session id with a newly generated one
    foreach( $sessionData as $key => $value ){
      if ( empty( $name ) && !isset( $name ) ) {
        $_SESSION[$key] = $value;
      } else {
        $_SESSION[$name][$key] = $value;
      }
    }
  }

	public function clear_session( $sessionData = array(), $name = NULL ){
    if ( !isset( $_SESSION[$name] ) )
      @session_start();

    if ( is_array( $sessionData ) && count( $sessionData ) > 0 ) {

      foreach( $sessionData as $key ) {
        if ( empty( $name ) && !isset( $name ) ) {
          unset($_SESSION[$key]);
        } else {
          unset($_SESSION[$name][$key]);
        }
      }


      // NOTE: $variable = $_SESSION['variable']; unset( $_SESSION['variable'], $variable );
      //
      // $sessionList = "";

      // foreach( $sessionData as $key ){
      //  if ( empty( $name ) ) {
      //    $sessionList .= '$_SESSION['.$key.'], ';
      //  } else {
      //    $sessionList .= '$_SESSION['.$name.']['.$key.'], ';
      //  }
      // }
      // $length = strlen( $sessionList );
      // $sessionArray = substr( $sessionList, 0, ( $length - 2 ) );
      // unset( $sessionArray );

    } else {

      @session_unset();
      @session_destroy();
      $this->generate_session_id(); // Update the current session id with a newly generated one
      @$_SESSION = array(); // to clear the current session array completely

    }
	}

  public function send_sms( $sms ) {

  }

  public function send_email( $email ) {
    $headers = array();
    $emailBody = '';
    $eol = "\r\n";

    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/html; charset=iso-8859-1";
    $headers[] = "X-Mailer: PHP-".phpversion();
    $headers[] = "Mailer: JencubeWebService/ 24.10.13";
    $headers[] = "Message-ID: <" . $_SERVER['REQUEST_TIME'] . md5($_SERVER['REQUEST_TIME'] . $email['to'] ) ."@" . $_SERVER['SERVER_NAME'] . ">";
    $headers[] = "From: " . $email['from'] . " <" . $email['fromemail'] . ">";
    $headers[] = "To: " . $email['to'] . " <" . $email['toemail'] . ">";

    if ( isset( $email['cc'] ) && !empty( $email['cc'] ) ) {
      $headers[] = "Cc: " . $email['cc'];
    }

    if ( isset( $email['bcc'] ) && !empty( $email['bcc'] ) ) {
      $headers[] = "Bcc: " . $email['bcc'];
    }

    if ( !isset( $email['reply'] ) && !isset( $email['replyemail'] ) ) {
      $headers[] = "Reply-To: " . $email['from'] . " <" . $email['fromemail'] . ">";
      $headers[] = "Return-Path: " . $email['from'] . " <" . $email['fromemail'] . ">";
    } else {
      $headers[] = "Reply-To: " . $email['reply'] . " <" . $email['replyemail'] . ">";
      $headers[] = "Return-Path: " . $email['reply'] . " <" . $email['replyemail'] . ">";
    }
    $headers[] = "Subject: " . $email['subject'];

    $subject = $email['subject'];
    $emailBody = $email['message'] . $eol . $eol;
    $emailHeaders =  implode( $eol, $headers );

    if ( @mail( $email['toemail'], $subject, $emailBody, $emailHeaders ) ) {
      return TRUE;
    } else {
      return FALSE;
    }

  }

	public function code_generator( $digits = 40, $options = NULL ) {

	    if ( !is_array( $options ) || empty( $options ) ) {

	      $characters = array( 1, 2, 3, 4 );

	    } else {

	      $characters = array();

	      if ( !array_key_exists( 'caps', $options ) || ( array_key_exists( 'caps', $options ) && $options['caps'] === TRUE ) ) {
	        $characters[] .= '1';
	      }

	      if ( !array_key_exists( 'small', $options ) || ( array_key_exists( 'small', $options ) && $options['small'] === TRUE ) ) {
	        $characters[] .= '2';
	      }

	      if ( !array_key_exists( 'number', $options ) || ( array_key_exists( 'number', $options ) && $options['number'] === TRUE ) ) {
	        $characters[] .= '3';
	      }

	      if ( !array_key_exists( 'special', $options ) || ( array_key_exists( 'special', $options ) && $options['special'] === TRUE ) ) {
	        $characters[] .= '4';
	      }

	      if ( is_array( $options['characters'] ) ) {
			$characters[] .= '5';
			$userChars = $options['characters'];
	      }

	    }

		$code = '';
		$alphabetCaps = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
		$alphabetSmall = array('a','b','c','d','e','f','g','h','j','k','m','n','o','p','q','r','s','t','u','v','w','x','y','z');
		$numbers = array('0','1','2','3','4','5','6','7','8','9');
		$specialChars = array('+','-','*','&','$','#','@','!','{','}','(',')');
		// $max = 4 - count($options);

		for( $i = 1; $i < $digits + 1; $i++ ) {
			// To decide if the digit should be numeric or alphabet
			// $single1 = rand( 1, $max);
      		$single = array_rand($characters);

			if ( ( $characters[$single] == 1 )  ) {
				$alphaCapIndex = array_rand( $alphabetCaps );
				$code .= $alphabetCaps[$alphaCapIndex];
			}

			if ( ( $characters[$single] == 2 )  ) {
				$alphaSmallIndex = array_rand( $alphabetSmall );
				$code .= $alphabetSmall[$alphaSmallIndex];
			}

			if ( ( $characters[$single] == 3 )  ) {
				$numIndex = array_rand( $numbers );
				$code .= $numbers[$numIndex];
			}

			if ( ( $characters[$single] == 4 ) ) {
				$charIndex = array_rand( $specialChars );
				$code .= $specialChars[$charIndex];
			}

			if ( ( $characters[$single] == 5 ) ) {
				$charIndex = array_rand( $userChars );
				$code .= $userChars[$charIndex];
			}

		}

		return $code;

	}

	public function errors(){
		foreach($this->error as $key => $value)
			return $value;
	}

}

?>