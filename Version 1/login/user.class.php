<?php
/**
*
*
* @filename:        user.class.php
* @description:     PHP Class to help database manipulation. It can be used as a user class, login class etc.
* @supportfile(s):  mysql.class.php, language.class.php
* @version:			    1.10.13
* @filetype:		    PHP
* @author:          JAY
* @authoremail:     j.ilukhor@gmail.com
* @twitter:			    @deusex0
* @lastmodified:    05/10/2013 11:18:20
* @license:         http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
* @copyright:       Copyright (c) 2013 Jay
* @usage:
*
*
*/

	class Users {

    protected $userID;
    protected $sessionID;
    private $merchantID;
    private $branchID;
    private $userData = array();
    private $username;
    private $password;
    private $encryptPassword;
    private $is_superuser = FALSE;

    private $posted;
    private $lastLogin;
    private $token;
    private $rememberme;
    private $ipaddress;
    public $redirectUrl;

    private $loginAccess;
    private $accessLevel;
    private $isOnline;

    private $tablePrefix;
    private $adminTableName;
    private $userTableName;
    private $failedTableName;

    public $db;
    private $merchant;

    private $access = FALSE;

    private $sessionName;
    private $cookieName;
    private $cookieTime;
    private $cookiePath;

    public $errors = array();
    public $errorInput = array();
    public $confirmation = NULL;

    public function __construct( $data = NULL ) {
      $config = array(
          "db_type" => DB_TYPE,
          "db_host" => DB_HOST,
          "db_name" => DB_NAME,
          "db_user" => DB_USERNAME,
          "db_pass" => DB_PASSWORD,
          "log_path" => LOG_PATH,
          "remote" => FALSE,
          "db_type" => DB_TYPE,
          "cache" => CACHE,
          "cache_path" => CACHE_PATH,
          "cache_age" => CACHE_AGE
        );

      $this->db = new Database( $config );
      $this->merchant = new Merchant();
      $this->settings = new Settings();

      $this->sessionName = SESSION_NAME;
      $this->cookieName = COOKIE_NAME;
      $this->cookieTime = ( $this->merchant->cookie_time() ) ? $this->merchant->cookie_time() : COOKIE_TIME;
      $this->cookiePath = COOKIE_PATH;
      $this->tablePrefix = TABLE_PREFIX;
      $this->userTableName = TABLE_PREFIX . "merchant_users";
      $this->failedTableName = TABLE_PREFIX . "merchant_uattempts";
      $this->adminTableName = TABLE_PREFIX . "users";
      // $url = $this->db->get_session( $this->sessionName, 'redirectUrl' );
      // $this->redirectUrl = ( !empty( $url ) )? $url : page( 'login', FALSE );
      $this->redirectUrl = page( 'login', FALSE );

      $this->posted = ( isset( $data['signin'] ) ) ? TRUE : FALSE;

      if ( isset( $data['token'] ) )
        $this->token = $this->db->filter( $data['token'] );

      $this->sessionID = ( isset( $data['username'] ) ) ? $this->db->code_generator( 60 ) : $this->db->get_session( $this->sessionName, 'sessionID' );

      $checkUsername = explode( ':', $data['username'] );

      if ( is_array( $checkUsername ) && ( $this->settings->get( 'super_user' ) == $checkUsername[0] ) ) {
        $this->is_superuser = TRUE;
        $this->username = $this->db->filter( $checkUsername[1] );
      } else {
        $this->is_superuser = FALSE;
        if ( isset( $data['username'] ) )
          $this->username = $this->db->filter( $data['username'] );
      }

      if ( isset( $data['password'] ) )
        $this->password = $this->db->filter( $data['password'] );

      $this->merchantID = ( isset( $data['merchant'] ) ) ? $this->db->filter( $data['merchant'] ) : $this->merchant->check_uniqname();

      $this->encryptPassword = $this->db->_encrypt( $this->password, ENCRYPT );
      if ( isset( $data['rememberme'] ) )
        $this->rememberme = $this->db->filter( $data['rememberme'] );

      $lastLogin = $this->db->get_session( $this->sessionName, 'lastLogin' );
      $this->lastLogin = ( !empty( $lastLogin ) )? $lastLogin : date( DB_DATE_TIME );

      $this->ipaddress = $this->db->get_IP();
    }

    public function set_data( $params ) {
      // Store posted data
      $this->__construct( $params );
    }

    public function is_login() {
      $sessionID = $this->db->get_session( $this->sessionName, 'sessionID' );
      if ( $this->db->get_session( $this->sessionName, 'superUser' ) == 1 )
        $this->is_superuser = TRUE;

     ( !$this->posted )? $this->verify_session() : $this->verify_posted_data();

      return $this->access;
    }

    private function verify_session() { // Just added $this->login_time() to tactle the issue of time login
      $this->access = ( $this->session_exist() && $this->check_database() && $this->login_time() )? TRUE : FALSE;
       return $this->access;
    }

    private function session_exist() {
      $session = $this->db->get_session( $this->sessionName );
      if( ( isset( $session ) && ( $session != '' ) ) || $_COOKIE[$this->cookieName] ) {
        if ( $this->load_user_data( $this->db->get_session( $this->sessionName, 'ID' ) ) )
          return TRUE;
      }
      return FALSE;
    }

    private function check_database() {

      if ( $this->db->get_session( $this->sessionName, 'superUser' ) == '1' )
        $this->is_superuser = TRUE;


      if ( $this->is_superuser ) {

        $query = $this->db->query("
          SELECT *
          FROM `" . $this->adminTableName . "`
          WHERE `user_session` = '" . $this->db->escape( $this->sessionID ) . "'
        ");

      } else {

        $query = $this->db->query("
          SELECT *
          FROM `" . $this->userTableName . "` AS u
          INNER JOIN `gfk_merchant_branch` AS b
          INNER JOIN gfk_merchant AS m
          ON u.user_session = '" . $this->db->escape( $this->sessionID ) . "'
          AND b.branch_merchant_id = '" . $this->db->escape( $this->merchantID ) . "'
          AND b.branch_id = u.user_branch_id
          AND m.merchant_id = '" . $this->db->escape( $this->merchantID ) . "'
          ");
      }

      if ( $this->db->num_rows() > 0 ) {
        return TRUE;
      }
      return FALSE;
    }

    private function verify_posted_data() {
      try {

        if ( !$this->is_token_valid() )
          throw new Exception( 'invalid_submission' );

        if ( empty( $this->username ) || empty( $this->password ) ) {
          $this->errorInput['username'] = '1';
          $this->errorInput['password'] = '1';
          throw new Exception( 'empty_user_pass' );
        }

        if ( !$this->is_data_valid() ) {
          $this->errorInput['username'] = '1';
          $this->errorInput['password'] = '1';
          throw new Exception( 'invalid_data' );
        }

        if ( !$this->login_time() )
          throw new Exception( 'terminate_time' );

        if ( !$this->verify_login_data() ) {
          $this->errorInput['username'] = '1';
          $this->errorInput['password'] = '1';
          throw new Exception( 'invalid_user_pass' );
        }

        if ( $this->loginAccess == '2' )
          throw new Exception( 'account_suspended' );

        if ( $this->loginAccess == '0' )
          throw new Exception( 'no_login_access' );

        if ( $this->isOnline == '1' )
          throw new Exception( 'already_logged_in' );

        $this->access = TRUE;
        $this->register_session();
        $this->update_login( 'login' );

      } catch ( Exception $e ) {
        $this->access = FALSE;
        $this->errors[] .= $e->getMessage();
      }

    }

    public function token() {
      $randEncrypt = $this->db->_encrypt( uniqid( mt_rand(), true ), ENCRYPT );
      $this->db->set_session( $this->sessionName, $randEncrypt, 'token' );
      return $randEncrypt;
    }

    private function is_token_valid() {
      $sessionToken = $this->db->get_session( $this->sessionName, 'token' );
      return ( !isset( $sessionToken ) || $this->token != $sessionToken )? FALSE : TRUE;
    }

    private function is_data_valid() {
      return ( preg_match( "/^[a-zA-z0-9._-]{". LOGIN_DATA_LENGTH .",15}$/", $this->username ) && preg_match( "/^[a-zA-z0-9|{}().@$]{". LOGIN_DATA_LENGTH .",30}$/", $this->password ) ) ? TRUE : FALSE;
    }

    private function verify_login_data() {
      if ( $this->is_superuser ) {

        $query = $this->db->query("
          SELECT *
          FROM `" . $this->adminTableName . "`
          WHERE user_name = '" . $this->db->escape( $this->username ) . "'
          AND user_pass = '" . $this->db->escape( $this->encryptPassword ) . "'
        ");

      } else {

        $query = $this->db->query("
          SELECT *
          FROM `" . $this->userTableName . "` AS u
          INNER JOIN `gfk_merchant_branch` AS b
          INNER JOIN gfk_merchant AS m
          ON u.user_name = '" . $this->db->escape( $this->username ) . "'
          AND u.user_pass = '" . $this->db->escape( $this->encryptPassword ) . "'
          AND b.branch_merchant_id = '" . $this->db->escape( $this->merchantID ) . "'
          AND b.branch_id = u.user_branch_id
          AND m.merchant_id = '" . $this->db->escape( $this->merchantID ) . "'
        ");

      }

      if ( $this->db->num_rows() == 0 ) {

        $loginAttempts = (object) json_decode( $this->merchant->get( 'login_attempt', $this->merchantID ), true );

        if ( $this->username_exist() && ( $loginAttempts->status == 'active' ) ) {
          $this->failed_login( $systemAttempts->count );
        }
        return FALSE;

      }

      if ( $this->userData = $this->db->fetch_array() ) {
        $this->branchID = ( $this->is_superuser ) ? 0 : $this->userData['user_branch_id'];
        $this->update_login('resetattempt');
        $this->userID = $this->userData['user_id'];
        $this->loginAccess = $this->userData['user_is_active'];
        $this->accessLevel = $this->userData['user_access_level'];
        $this->isOnline = $this->userData['user_is_online'];
        if ( $this->rememberme ) {
          $this->set_cookie();
        }
      }
      return TRUE;

    }


    private function login_time() {
      $loginPeriod = (object) json_decode( $this->merchant->get( 'login_period', $this->merchantID ), true );
      $currentTime = strtotime( date( 'Y-m-d h:iA' ) );
      if ( $loginPeriod->status == 'active' ) {
        $loginTimeStatus = TRUE;
        $startTime = ( !empty( $loginPeriod->ohour ) && !empty( $loginPeriod->ominutes ) && !empty( $loginPeriod->operiod ) )?
                      $loginPeriod->ohour . ':' . $loginPeriod->ominutes . $loginPeriod->operiod : LOGIN_TIME_START;
        $endTime = ( !empty( $loginPeriod->chour ) && !empty( $loginPeriod->cminutes ) && !empty( $loginPeriod->cperiod ) )?
                      $loginPeriod->chour . ':' . $loginPeriod->cminutes . $loginPeriod->cperiod : LOGIN_TIME_END;
      } else if ( $loginPeriod->status == 'inactive' ) {
        $loginTimeStatus = FALSE;
      } else {
        $loginTimeStatus = LOGIN_TIME;
        $startTime = LOGIN_TIME_START;
        $endTime = LOGIN_TIME_END;
      }
      $morningTime = strtotime( date( 'Y-m-d ' ) . $startTime );
      $eveningTime = strtotime( date( 'Y-m-d ' ) . $endTime );
      // echo $currentTime. ' '. $morningTime;
      if ( $this->is_superuser == TRUE ) {
        $loginTimeStatus = FALSE;
      }
      return ( ( ( $loginTimeStatus === TRUE ) && ( $currentTime < $morningTime || $currentTime > $eveningTime ) ) )? FALSE : TRUE;
    }


    private function register_session() {
      $sessionData = '';
      $sessionData = array(
        "sessionID" => $this->sessionID,
        "ID" => $this->userID,
        "branchID" => $this->branchID,
        "accessLevel" => $this->accessLevel,
        "lastLogin" => $this->lastLogin,
        "superUser" => ( $this->is_superuser)? '1' : '0',
        "logout" => '0'
        );

      $this->db->generate_session_id();
      $this->db->register_session( $sessionData, $this->sessionName );
    }

    private function update_login( $status = NULL ) {
      switch ( $status ) {
        case 'login':
          $updateData = array(
            "user_session" => $this->sessionID,
            "user_ip_address" => $this->ipaddress,
            "user_last_login" => $this->lastLogin,
            "user_is_online" => '1'
            );

          if ( $this->is_superuser ) {
            $updated = $this->db->update(
                $this->adminTableName,
                $updateData,
                "WHERE `user_id` = '" . $this->db->escape( $this->userID ) . "'"
              );
          } else {
            $updated = $this->db->update(
                $this->userTableName,
                $updateData,
                "WHERE `user_id` = '" . $this->db->escape( $this->userID ) . "'"
              );
          }

          return ( $updated )? TRUE : FALSE;
          break;
        case 'logout':
          $updateData = array(
            "user_session" => '',
            "user_last_login" => $this->lastLogin,
            "user_is_online" => '0'
            );

          if ( $this->is_superuser ) {
            $updated = $this->db->update(
                  $this->adminTableName,
                  $updateData,
                  "WHERE `user_id` = '" . $this->db->escape( $this->userID ) . "'"
                );
          } else {
            $updated = $this->db->update(
                $this->userTableName,
                $updateData,
                "WHERE `user_id` = '" . $this->db->escape( $this->userID ) . "'"
              );
          }

          return ( $updated ) ? TRUE : FALSE;
          break;
        case 'suspend':
          $userData = array(
            "user_is_active" => '2'
            );

          if ( $this->is_superuser ) {
            $users = $this->db->update(
                $this->adminTableName,
                $userData,
                "WHERE `user_name` = '" . $this->db->escape( $this->username ) . "'"
              );
          } else {
            $users = $this->db->update(
                $this->userTableName,
                $userData,
                "WHERE `user_name` = '" . $this->db->escape( $this->username ) . "'"
              );
          }

          if ( $users ) {
            $attemptData = array(
              "user_attempts" => '0',
              );

            $attempts = $this->db->update(
                $this->failedTableName,
                $attemptData,
                "WHERE `user_ipaddrs` = '" . $this->db->escape( $this->ipaddress ) . "' AND `user_name` = '" . $this->db->escape( $this->username ) . "'"
              );
          }
          return ( $attempts )? TRUE : FALSE;
          break;
        case 'unsuspend':
          $userData = array(
            "user_is_active" => '1'
            );

          if ( $this->is_superuser ) {
            $users = $this->db->update(
                $this->adminTableName,
                $userData,
                "WHERE `user_name` = '" . $this->db->escape( $this->username ) . "'"
              );
          } else {
            $users = $this->db->update(
                $this->userTableName,
                $userData,
                "WHERE `user_name` = '" . $this->db->escape( $this->username ) . "'"
              );
          }
          return ( $users )? TRUE : FALSE;
          break;
        case 'resetattempt':
          $attemptData = array(
            "user_attempts" => '0',
            );

          $attempts = $this->db->update(
              $this->failedTableName,
              $attemptData,
              "WHERE `user_ipaddrs` = '" . $this->db->escape( $this->ipaddress ) . "' AND `user_name` = '" . $this->db->escape( $this->username ) . "'"
            );
          return ( $attempts )? TRUE : FALSE;
          break;
        default:
          return FALSE;
          break;
      }

    }

    private function failed_login( $systemAttempts = NULL ) {
      $query = $this->db->query("
          SELECT *
          FROM `" . $this->failedTableName . "`
          WHERE `user_ipaddrs` = '" . $this->db->escape( $this->ipaddress ). "'
          AND `user_name` = '" . $this->db->escape( $this->username ) . "'
        ");

      if ( $query ) {

        if ( $this->db->num_rows() == 0 ) {

          $this->add_login_attempt( 0, 'insert' );

        } else {

          if ( $fetch = $this->db->fetch() ) {

            if( $this->account_suspended() === FALSE ) {

              $loginAttempts = ( !empty( $systemAttempts ) || $systemAttempts > 0 )? $systemAttempts : LOGIN_ATTEMPTS;

              if ( $fetch->user_attempts >= $loginAttempts ) {

                $this->update_login( 'suspend' );

              } else {

                $this->add_login_attempt( $fetch->user_attempts );

              }

            } else {

              $this->add_login_attempt( $fetch->user_attempts );

            }

          }

        }
        return FALSE;
      }
      return FALSE;
    }

    public function username_exist( $username = NULL ) {
      if ( empty( $username ) )
        $username = $this->username;

      if ( $this->is_superuser ) {
        $query = $this->db->query("
          SELECT *
          FROM `" . $this->adminTableName . "`
          WHERE user_name = '" . $this->db->escape( $username ) . "'
          LIMIT 1
        ");
      } else {
        $query = $this->db->query("
          SELECT *
          FROM `" . $this->userTableName . "` AS u
          INNER JOIN `gfk_merchant_branch` AS b
          INNER JOIN gfk_merchant AS m
          ON u.user_name = '" . $this->db->escape( $username ) . "'
          AND b.branch_merchant_id = '" . $this->db->escape( $this->merchantID ) . "'
          AND b.branch_id = u.user_branch_id
          AND m.merchant_id = '" . $this->db->escape( $this->merchantID ) . "'
          LIMIT 1
        ");
      }



      if ( $this->db->numRows > 0 )
        return TRUE;
      else
        return FALSE;
    }

    private function set_cookie() {
      $value = $this->username . '*' . $this->encryptPassword . '*';
      $cookieValue = $this->db->_encrypt( ( $value . ' ' . $_SERVER['HTTP_USER_AGENT'] ), ENCRYPT );
      setcookie( $this->cookieName, $cookieValue, time() + $this->cookieTime, $this->cookiePath );
    }

    private function account_suspended( $ID = NULL ) {
      if ( empty( $ID ) ) {
        $ID = ( !empty( $this->userID ) )? $this->userID : $this->username;
      }

      $query = $this->db->query("
          SELECT user_is_active
          FROM `" . $this->userTableName . "`
          WHERE `user_id` = '" . $this->db->escape( $ID ) . "'
          OR `user_name` = '" . $this->db->escape( $ID ) . "'
        ");

      $fetch = $this->db->fetch();

      if ( $this->db->numRows > 0 ) {
        return ( $fetch->user_is_active == '2' )? TRUE : FALSE;
      }

    }

    private function add_login_attempt( $value = 0, $action = 'update' ) {
      $loginAttempts = 0;
      $loginAttempts = $value + 1;

      switch ( $action ) {
        case 'update':

          if ( $loginAttempts == 3 ) {

            $updateData = array(
              "user_attempts" => $loginAttempts,
              "user_datetime" => $this->lastLogin
              );

          } else {

            $updateData = array(
              "user_attempts" => $loginAttempts
              );

          }

          $updated = $this->db->update(
              $this->failedTableName,
              $updateData,
              "WHERE `user_ipaddrs` = '" . $this->db->escape( $this->ipaddress ) . "' AND `user_name` = '" . $this->db->escape( $this->username ) . "'"
            );

          return ( $updated )? TRUE : FALSE;

          break;
        case 'insert':

            $hostName = ( gethostname() )? gethostname() : '0';
            $hostIP = ( gethostbyname( $hostName ) )? gethostbyname( $hostName ) : '0';

            $insertData = array(
              "user_ipaddrs" => $this->ipaddress,
              "user_hostip" => $hostIP,
              "user_hostname" => $hostName,
              "user_name" => $this->username,
              "user_attempts" => $loginAttempts,
              "user_datetime" => $this->lastLogin
              );

          $inserted = $this->db->insert( $this->failedTableName, $insertData );

          return ( $inserted )? TRUE : FALSE;

          break;
        default:
          return FALSE;
          break;
      }
    }

    public function set_url( $url = NULL ) {
      $baseUrl = ( !empty( $url ) )? $url : $this->get( 'user_baseurl' );
      // $this->db->set_session( $this->sessionName, $baseUrl, 'redirectUrl' );
      $this->redirectUrl = page( $baseUrl, FALSE );
    }

    public function redirect( $url = NULL ) {
      if ( !empty( $url ) && !headers_sent() )
        header( 'Location: ' . $url );
      else
        header( 'Location: ' . $this->redirectUrl );
    }

    public function check_logout() {
      if ( $this->db->get_session( $this->sessionName, 'logout' ) == 1 ) {
        $sessionData = array( "logout" );
        $this->db->clear_session( $sessionData, $this->sessionName );
        return TRUE;
      }
      return FALSE;
    }

    public function is_logout( $key ) {
      if ( !$this->check_logout() ) {
        return FALSE;
      }

      switch ( $key ) {
        case 'logout':
          $this->confirmation = 'logout_success';
          return TRUE;
          break;
        case 'reset':
          $this->confirmation = 'logout_reset';
          return TRUE;
          break;
      }
      return FALSE;
    }

    public function logout() {
      // Remove to enable logout of the current
      // sessions due to url hack
      // if ( !$this->is_login() )
      //   return FALSE;

      // Added due to url hack
      // Added to enable logout & update on the users table
      $this->userID = $this->db->get_session( $this->sessionName, 'ID' );

      if ( $this->db->get_session( $this->sessionName, 'superUser' ) == 1 )
        $this->is_superuser = TRUE;

      if ( $this->update_login( 'logout' ) ) {

        if ( isset( $_COOKIE[$this->cookieName] ) ) {
          if( !setcookie( $this->cookieName, NULL, time() - $this->cookieTime, $this->cookiePath ) ) { // If system time not correct
            setcookie ( $this->cookieName, "", 1 );
          }
          setcookie ( $this->cookieName, false );
          unset( $_COOKIE[$this->cookieName] );
        }

        $sessionData = array(
          "sessionID",
          "ID",
          "accessLevel",
          "lastLogin",
          "superUser",
          "redirectUrl"
        );

        $this->db->clear_session( $sessionData, $this->sessionName );
        $this->db->set_session( $this->sessionName, '1', 'logout' );
        $this->access = FALSE;
        $this->is_superuser = FALSE;
        return TRUE;
      }
      return FALSE;

    }

    private function check_attempts() {
      $query = $this->db->query("
          SELECT *
          FROM `" . $this->failedTableName . "`
          WHERE `user_name` = '" . $this->db->escape( $this->username ) . "'
          AND `user_ipaddrs` = '" . $this->db->escape( $this->ipaddress ) . "'
          AND `user_attempts` > 0
        ");

      if ( $this->db->numRows > 0 ) {
        return TRUE;
      }
      return FALSE;
    }

    public function user_access() {
      $access = $this->settings->access_level( $this->get( 'user_access_level' ) );
      return $access['access_name'];
    }

    public function is_user_loaded() {
      return ( empty( $this->userID ) ) ? FALSE : TRUE;
    }

    public function is( $userProp ) {
      return ( $this->get( $userProp ) ) ? TRUE : FALSE;
    }

    public function get( $property ) {
      if ( !$this->is_user_loaded() )
        return FALSE;

      if ( !isset( $this->userData[$property] ) )
        return FALSE;

      return $this->userData[$property];
    }

    public function load_user_data( $userID ) {
      // if ( $this->db->get_session( $this->sessionName, 'superUser' ) == 1 )
      //   $this->is_superuser = TRUE;

      if ( $this->is_superuser ) {
        $query = $this->db->query("
            SELECT *
            FROM `" . $this->adminTableName . "`
            WHERE `user_id` = '" . $this->db->escape( $userID ) . "' LIMIT 1
          ");
      } else {
        $query = $this->db->query("
            SELECT *
            FROM `" . $this->userTableName . "`
            WHERE `user_id` = '" . $this->db->escape( $userID ) . "' LIMIT 1
          ");
      }

      if ( $this->db->numRows == 0 )
        return FALSE;

      $this->userData = $this->db->fetch_array();
      $this->userID = $userID;
      return TRUE;

    }

    public function is_active() {
      return ( $this->userData['user_is_active'] == '1' ) ? TRUE : FALSE;
    }

    public function activate_account() {
      if ( !$this->is_user_loaded() ) {
        $this->errors[] .= 'no_user_loaded';
        return FALSE;
      }

      if ( $this->is_active() ) {
        $this->errors[] .= 'user_already_active';
        return FALSE; // echo User account already activated
      }

      $updateData = array(
          "user_is_active" => '1'
        );

      $updated = $this->db->update(
          $this->userTableName,
          $updateData,
          "WHERE `user_id` = '" . $this->db->escape( $this->userID ) . "' LIMIT 1
        ");

      if ( $this->db->affected_rows() == 1 ) {
        $this->userData['user_is_active'] = 1;
        return TRUE;
      }
      return FALSE;
    }

    public function get_session_data( $data ) {
      if ( !$this->is_login() ) {
        return FALSE;
      }

      switch ( $data ) {
        case 'sessionid':
          return $this->db->get_session( $this->sessionName, 'sessionID' );
          break;
        case 'id':
          return $this->db->get_session( $this->sessionName, 'ID' );
          break;
        case 'branchid':
          return $this->db->get_session( $this->sessionName, 'branchID' );
          break;
        case 'privilege':
          return $this->db->get_session( $this->sessionName, 'accessLevel' );
          break;
        case 'logintime':
          return $this->db->get_session( $this->sessionName, 'lastLogin' );
          break;
      }

    }

    public function user_id() {
      if ( !$this->is_user_loaded() )
        return FALSE;

      return $this->userID;
    }

    public function grant_access( $forbidden = NULL, $accepted = NULL ) {
      if ( !$this->is( 'user_access_level' ) )
        return FALSE;

      $userAccess = array( 1, 2, 3, 4, 5 );

      if ( !empty( $forbidden ) ) {

        $arrayForbidden = explode( ',', $forbidden );
        $count = count( $arrayForbidden );

        for ( $i = 0; $i < $count; $i++ ) {
          if ( ( $key = array_search( $arrayForbidden[$i], $userAccess ) ) !== FALSE ) {
            unset( $userAccess[$key] );
          }
        }

      }

      if ( in_array( $this->get('user_access_level'), $userAccess ) ) {
        return TRUE;
      }
      return FALSE;

    }

    public function restricted_page( $forbidden, $URL = NULL ) {
      if ( $this->grant_access( $forbidden ) === FALSE ) {
        $url = ( !empty( $URL ) ) ? '/' . $URL : '/302';
        $realURL = merchant( 'url', FALSE ) . $url;
        $this->redirect( $realURL );
      }
    }

    public function is_user() {
      if ( !$this->is( 'user_access_level' ) )
        return FALSE;

      if ( $this->get('user_access_level') == '5' )
        return TRUE;
      else
        return FALSE;
    }

    public function is_badmin() {
      if ( !$this->is( 'user_access_level' ) )
        return FALSE;

      if ( $this->get('user_access_level') == '4' )
        return TRUE;
      else
        return FALSE;
    }

    public function is_admin() {
      if ( !$this->is( 'user_access_level' ) )
        return FALSE;

      if ( $this->get('user_access_level') == '3' )
        return TRUE;
      else
        return FALSE;
    }

    public function is_gkuser() {
      if ( !$this->is( 'user_access_level' ) )
        return FALSE;

      if ( $this->get('user_access_level') == '2' )
        return TRUE;
      else
        return FALSE;
    }

    public function is_superadmin() {
      if ( !$this->is( 'user_access_level' ) )
        return FALSE;

      if ( $this->get('user_access_level') == '1' )
        return TRUE;
      else
        return FALSE;
    }

    // Check new password and confirm password credentials
    private function check_password( $password, $confirmPassword ) {
      try {

        if ( !preg_match( "/^[a-zA-z0-9|{}().@$]{". LOGIN_DATA_LENGTH .",30}$/", $password ) )
          throw new Exception( 'invalid_password_character' );

        if ( $password != $confirmPassword )
          throw new Exception( 'password_match' );

      } catch ( Exception $e ) {
        $this->errors[] .= $e->getMessage();
        return FALSE;
      }
      return TRUE;
    }

    // Password reset authocode check
    public function is_authcode_valid( $data ) {
      $encryptAuthCode = $this->db->_encrypt( $data['authcode'], ENCRYPT );
      $sql = $this->db->query("
        SELECT *
        FROM " . $this->userTableName . " AS u
        INNER JOIN gfk_merchant_branch AS b
        INNER JOIN gfk_merchant AS m
        ON u.user_auth_code = '" . $this->db->escape( $encryptAuthCode ) . "'
        AND b.branch_merchant_id = '" . $this->db->escape( $data['merchant'] ) . "'
        AND b.branch_id = u.user_branch_id
        AND b.branch_merchant_id = m.merchant_id
      ");

      if ( $this->db->numRows == 0 ) {
        $this->errors[] .= 'invalid_auth_code';
        return FALSE;

      }

      $this->userData = $this->db->fetch_array();
      $this->userID = $this->userData['user_id'];
      return TRUE;
    }

    // if true prompt user to change password
    // used after login process, to validation user credential
    public function is_temp_pass() {
      $this->userID = $this->db->get_session( $this->sessionName, 'ID' );
      if ( !empty( $this->userID ) ) {
        if ( $this->load_user_data( $this->userID ) ) {
          $sql = $this->db->query("
            SELECT *
            FROM " . $this->userTableName . "
            WHERE user_id = '" . $this->db->escape( $this->userID ) . "'
            AND user_pass = '" . $this->db->escape( $this->userData['user_temp_pass'] ) . "'
          ");

          if ( $this->db->numRows > 0 )
            return TRUE;
          else
            return FALSE;
        }
      }
    }

    // if true notify user that their password needs to be changed
    // used after login process, to validation user credential
    public function is_old_pass() {
      $this->userID = $this->db->get_session( $this->sessionName, 'ID' );
      if ( !empty( $this->userID ) ) {
        if ( $this->load_user_data( $this->userID ) ) {
          $sql = $this->db->query("
            SELECT *
            FROM " . $this->userTableName . "
            WHERE user_id = '" . $this->db->escape( $this->userID ) . "'
            AND user_pass = '" . $this->db->escape( $this->userData['user_old_pass'] ) . "'
          ");

          if ( $this->db->numRows > 0 )
            return TRUE;
          else
            return FALSE;
        }
      }
    }

    // For password change; check if the old password is correct
    private function check_old_password( $oldPassword ) {
      $branchID = $this->userData['user_branch_id'];

      if ( !empty( $branchID ) ) {
        $whereClause = "
        SELECT *
        FROM " . $this->userTableName . "
        WHERE user_branch_id = '" . $this->db->escape( $branchID ) . "'
        AND user_id = '" . $this->db->escape( $this->userID ) . "'
        AND user_pass = '" . $this->db->escape( $oldPassword ) . "'
      ";
      } else {
        $whereClause = "
        SELECT *
        FROM " . $this->adminTableName . "
        WHERE user_id = '" . $this->db->escape( $this->userID ) . "'
        AND user_pass = '" . $this->db->escape( $oldPassword ) . "'
      ";
      }

      $sql = $this->db->query( $whereClause );

      if ( $this->db->numRows == 0 ) {
        $this->errors[] .= 'invalid_db_password';
        return FALSE;
      } else {
        return TRUE;
      }
    }

    // CHANGE PASSWORD; either via admin, password reset, and user prompt
    public function change_password( $data, $action = 'reset' ) {
      if ( isset( $data['token'] ) )
        $this->token = $this->db->filter( $data['token'] );

      $userID = ( !empty( $data['uid'] ) ) ? $data['uid'] : $this->userID;

      if ( !$this->load_user_data( $userID ) )
        return FALSE;

      $encryptOldPassword = $this->db->_encrypt( $data['oldpassword'], ENCRYPT );

      if ( !$this->is_token_valid() ) {
        $this->errors[] .= 'invalid_submission';
        return FALSE;
      } else if ( is_array( $data ) && ( $action == 'user' && empty( $data['oldpassword'] ) ) ) {
        $this->errorInput['oldpassword'] = '1';
        $this->errors[] .= 'empty_old_password';
        return FALSE;
      } else if ( $action == 'user' AND !$this->check_old_password( $encryptOldPassword ) ) {
        $this->errorInput['oldpassword'] = '1';
        return FALSE;
      } else if ( is_array( $data ) && ( empty( $data['newpassword'] ) ) ) {
        $this->errorInput['newpassword'] = '1';
        $this->errors[] .= 'empty_new_password';
        return FALSE;
      } else if ( is_array( $data ) && ( empty( $data['confirmpassword'] ) ) ) {
        $this->errorInput['confirmpassword'] = '1';
        $this->errors[] .= 'empty_confirm_password';
        return FALSE;
      } else if ( !$this->check_password( $data['newpassword'], $data['confirmpassword'] ) ) {
        $this->errorInput['newpassword'] = '1';
        $this->errorInput['confirmpassword'] = '1';
        return FALSE;
      } else {

        $encryptNewPassword = $this->db->_encrypt( $data['newpassword'], ENCRYPT );

        $branchID = $this->userData['user_branch_id'];

        if ( !empty( $branchID ) ) {
          $dbTable = $this->userTableName;
          $whereClause = "WHERE `user_branch_id` = '" . $this->db->escape( $branchID ) . "' AND `user_id` = '" . $this->db->escape( $userID ) . "'";
        } else {
          $dbTable = $this->adminTableName;
          $whereClause = "WHERE `user_id` = '" . $this->db->escape( $userID ) . "'";
        }

        if ( $action == 'admin' ) {

          $updateData = array(
            "user_pass" => $encryptNewPassword,
            "user_old_pass" => $encryptNewPassword,
            );

          $where = "WHERE `user_branch_id` = '" . $this->db->escape( $branchID ) . "'
            AND `user_id` = '" . $this->db->escape( $userID ) . "'";

        } else if ( $action == 'reset' ) {

          $updateData = array(
            "user_pass" => $encryptNewPassword,
            "user_auth_code" => "",
            "user_temp_pass" => "",
            );

        } else if ( $action == 'user' ) {

          $updateData = array(
            "user_pass" => $encryptNewPassword,
            );

        }

        $updated = $this->db->update(
            $dbTable,
            $updateData,
            $whereClause
          );

        if ( $updated ) {
          $this->confirmation = 'password_changed';

          if ( $action == 'reset' || $action == 'user' ) {
            // $randEncrypt = $this->db->_encrypt( $this->db->code_generator( 60 ), ENCRYPT );
            $randEncrypt = $this->db->_encrypt( 'password_changed', ENCRYPT );
            $this->db->set_session( $this->sessionName, $randEncrypt, 'sessionID' );
          }
          return TRUE;
        } else {
          return FALSE;
        }
        $this->errors[] .= 'internal_db_update_error';
      }
    }

    public function is_password_change() {
      $sessionID = $this->db->get_session( $this->sessionName, 'sessionID' );
      $passwordKey = $this->db->_encrypt( 'password_changed', ENCRYPT );
      if ( $sessionID == $passwordKey ) {
        return TRUE;
      }
      return FALSE;
    }

    // FORGET PASSWORD PROCESS START
    public function process_reset( $data, $type = 'link' ) {
      $this->password_reset( $data, $type );
      return $this->access;
    }

    // FORGET PASSWORD; authentication code validation & password update
    // $auth->update_reset_password( $authCode );
    public function update_reset_password( $data ) {
      if ( $this->is_authcode_valid( $data ) ) {
        $updateData = array(
          "user_pass" => $this->userData[' user_temp_pass '],
          );

        $branchID = $this->userData['user_branch_id'];

        if ( !empty( $branchID ) ) {
          $dbTable = $this->userTableName;
          $whereClause = "WHERE `user_branch_id` = '" . $this->db->escape( $this->userData['user_branch_id'] ) . "' AND `user_id` = '" . $this->db->escape( $this->userData['user_id'] ) . "'";
        } else {
          $dbTable = $this->adminTableName;
          $whereClause = "WHERE `user_id` = '" . $this->db->escape( $this->userData['user_id'] ) . "'";
        }

        $updated = $this->db->update(
            $dbTable,
            $updateData,
            $whereClause
          );
        return TRUE;
      }
      return FALSE;
    }

    // Forget password email validation
    private function password_reset( $data, $type = 'link' ) {
      if ( isset( $data['token'] ) )
        $this->token = $this->db->filter( $data['token'] );

      $this->access = FALSE;

      try {
        if ( !$this->is_token_valid() )
          throw new Exception( 'invalid_submission' );

        if ( empty( $data['email'] ) )
          throw new Exception( 'empty_reset' );

        if ( $this->db->is_email_valid( $data['email'] ) && !$this->check_user_email( $data ) )
          throw new Exception( 'email_not_found' );

        if ( !$this->db->is_email_valid( $data['email'] ) && !$this->check_user_email( $data ) )
          throw new Exception( 'user_not_found' );

        if ( $type == 'link' && !$this->send_reset_link( $data['email'] ) )
          throw new Exception( 'email_not_sent' );

        if ( $type == 'password' && !$this->generate_new_password( $data['email'] ) )
          throw new Exception( 'email_not_sent' );

      } catch( Exception $e ) {
        $this->errors[] .= $e->getMessage() . $this->userID;
        return FALSE;
      }

    }

    // forget password; send authentication code & generated password
    private function generate_new_password( $email = NULL ) {
      $message = $this->settings->email_notification( 'forget_password_reset', 'email_content' );
      $subject = $this->settings->email_notification( 'forget_password_reset', 'email_subject' );
      $from = $this->settings->email_notification( 'forget_password_link', 'email_from' );
      $siteName = $this->settings->get( 'sitename' );
      $website = $this->settings->get( 'website' );
      $merchant = $this->userData['merchant_uniqname'];
      $name = $this->userData['user_fname'];
      $fullname = $this->userData['user_fname'] . $this->userData['user_lname'];
      $password = $this->db->code_generator( LOGIN_DATA_LENGTH + 2 );
      $authCode = $this->db->code_generator( 20, array( 'special' => FALSE ) );
      $encryptAuthCode = $this->db->_encrypt( $authCode, ENCRYPT );
      $encryptPassword = $this->db->_encrypt( $password, ENCRYPT );
      $keys = array( $name => '{name}', $password => '{password}', $website => '{website}', $merchant => '{merchant-uname}', $authCode => '{auth-code}', $siteName => '{site-name}', $subject => '{subject}' );
      foreach( $keys as $replace => $find ) {
        $message = str_replace( $find, $replace, $message );
      }

      $fromName = $siteName . "-" . $merchant;

      $updateData = array(
        "user_auth_code" => $encryptAuthCode,
        "user_temp_pass" => $encryptPassword,
        );

      $branchID = $this->userData['user_branch_id'];

      if ( !empty( $branchID ) ) {
        $dbTable = $this->userTableName;
        $whereClause = "WHERE `user_branch_id` = '" . $this->db->escape( $this->userData['user_branch_id'] ) . "' AND `user_id` = '" . $this->db->escape( $this->userData['user_id'] ) . "'";
      } else {
        $dbTable = $this->adminTableName;
        $whereClause = "WHERE `user_id` = '" . $this->db->escape( $this->userData['user_id'] ) . "'";
      }

      $updated = $this->db->update(
          $dbTable,
          $updateData,
          $whereClause
        );

      $email = array(
        "to" => $fullname,
        "toemail" => $email,
        "from" => $fromName,
        "fromemail" => $from,
        "subject" => $subject,
        "message" => $message,
        );

      if ( $this->db->send_email( $email ) ) {
        $this->access = TRUE;
        $this->confirmation = 'password_reset_sent';
        return TRUE;
      } else {
        $this->errors[] .= 'email_not_sent';
        return FALSE;
      }
    }

    // forget password; send authentication code
    private function send_reset_link( $email = NULL ) {
      $message = $this->settings->email_notification( 'forget_password_link', 'email_content' );
      $subject = $this->settings->email_notification( 'forget_password_link', 'email_subject' );
      $from = $this->settings->email_notification( 'forget_password_link', 'email_from' );
      $siteName = $this->settings->get( 'sitename' );
      $website = $this->settings->get( 'website' );
      $merchant = $this->userData['merchant_uniqname'];
      $name = $this->userData['user_fname'];
      $fullname = $this->userData['user_fname'] . ' ' . $this->userData['user_lname'];
      $authCode = $this->db->code_generator( 20, array( 'special' => FALSE ) );
      $encryptAuthCode = $this->db->_encrypt( $authCode, ENCRYPT );
      $keys = array( $name => '{name}', $website => '{website}', $merchant => '{merchant-uname}', $authCode => '{auth-code}', $siteName => '{site-name}' );
      foreach( $keys as $replace => $find ) {
        $message = str_replace( $find, $replace, $message );
      }

      $fromName = $siteName . "-" . $merchant;

      $updateData = array(
        "user_auth_code" => $encryptAuthCode,
        );

      $branchID = $this->userData['user_branch_id'];

      if ( !empty( $branchID ) ) {
        $dbTable = $this->userTableName;
        $whereClause = "WHERE `user_branch_id` = '" . $this->db->escape( $this->userData['user_branch_id'] ) . "' AND `user_id` = '" . $this->db->escape( $this->userData['user_id'] ) . "'";
      } else {
        $dbTable = $this->adminTableName;
        $whereClause = "WHERE `user_id` = '" . $this->db->escape( $this->userData['user_id'] ) . "'";
      }

      $updated = $this->db->update(
          $dbTable,
          $updateData,
          $whereClause
        );

      $email = array(
        "to" => $fullname,
        "toemail" => $email,
        "from" => $fromName,
        "fromemail" => $from,
        "subject" => $subject,
        "message" => $message,
        );

      if ( $this->db->send_email( $email ) ) {
        $this->access = TRUE;
        $this->confirmation = 'password_reset_sent';
        return TRUE;
      } else {
        $this->errors[] .= 'email_not_sent';
        return FALSE;
      }

    }

    // Forget password; verify user email address
    private function check_user_email( $data ) {

      if ( isset( $data['admin'] ) != NULL || $data['admin'] == TRUE ) {
        $queryString = "
                  SELECT *
                  FROM " . $this->adminTableName . "2
                  WHERE user_name = '" . $this->db->escape( $data['email'] ) . "'
                  OR user_email = '" . $this->db->escape( $data['email'] ) . "'
                ";
                $userID = 'admin';
      } else {
        $queryString = "
                    SELECT *
                    FROM " . $this->userTableName . " AS u
                    INNER JOIN " . $this->tablePrefix . "merchant_branch AS b
                    INNER JOIN " . $this->tablePrefix . "merchant AS m
                    ON ( u.user_name = '" . $this->db->escape( $data['email'] ) . "'
                    OR u.user_email = '" . $this->db->escape( $data['email'] ) . "' )
                    AND b.branch_merchant_id = '" . $this->db->escape( $data['merchant'] ) . "'
                    AND b.branch_id = u.user_branch_id
                    AND b.branch_merchant_id = m.merchant_id
                  ";
                  $userID = 'user';
      }

      $sql = $this->db->query( $queryString );

      if ( $this->db->numRows == 0 )
        return FALSE;

      $this->userData = $this->db->fetch_array();
      $this->userID = $userID;
      return TRUE;
    }

    public function remove_user( $query, $admin = FALSE ) {
      if ( is_admin() || is_superadmin() ) {

        $dbTable = ( $admin == TRUE ) ? $this->adminTableName : $this->userTableName;

        return $this->db->delete( $dbTable, $query );
      }
    }

    public function view_user( $query, $admin = FALSE ) {
      if( !empty( $query ) ) {
        if( substr( strtoupper( trim( $query ) ), 0, 5 ) != 'WHERE' ) {
          $where = " WHERE ". $query;
        } else {
          $where = " ". trim( $query );
        }
      }

      $dbTable = ( $admin == TRUE ) ? $this->adminTableName : $this->userTableName;

      $sql = $this->db->query("
        SELECT *
        FROM `" . $dbTable . "` " . $where . "
      ");

      if ( $this->db->numRows == 0 )
        return FALSE;

      $userData = $this->db->fetch_array();
      return $userData;
    }

    public function all_users( $query, $admin = FALSE ) {
      if( !empty( $query ) ) {
        if( substr( strtoupper( trim( $query ) ), 0, 5 ) != 'WHERE' ) {
          $where = " WHERE ". $query;
        } else {
          $where = " ". trim( $query );
        }
      }

      $dbTable = ( $admin == TRUE ) ? $this->adminTableName : $this->userTableName;

      $query = $this->db->query("
        SELECT *
        FROM `" . $dbTable . "` " . $where . "
      ");

      if ( $this->db->numRows > 0 ) {
        if ( $fetch = $this->db->fetch_all( $query ) ) {
          return $fetch;
        }
      }
      return FALSE;
    }

    public function branch_users( $data, $query = NULL ) {
      $sql = $this->db->query("
        SELECT *
        FROM `" . $this->userTableName . "`
        WHERE `user_branch_id` = '" . $data['branch'] . "'
        " . $query . "
      ");

      if ( $this->db->numRows == 0 )
        return FALSE;

      $userData = $this->db->fetch_array();
      return $userData;
    }

    public function merchant_users( $merchantID, $branchID = NULL ) {
      if ( !empty( $branchID ) ) {
        $sql = "SELECT *
        FROM " . $this->userTableName . " AS u
        INNER JOIN " . $this->tablePrefix . "merchant_branch AS b
        ON b.branch_id = '" . $this->db->escape( $branchID ) . "'
        AND b.branch_merchant_id = '" . $this->db->escape( $merchantID ) . "'
        AND b.branch_id = u.user_branch_id";
      } else {
        $sql = "SELECT *
        FROM " . $this->userTableName . " AS u
        INNER JOIN " . $this->tablePrefix . "merchant_branch AS b
        ON b.branch_merchant_id = '" . $this->db->escape( $merchantID ) . "'
        AND b.branch_id = u.user_branch_id";
      }

      $queried = $this->db->query( $sql );

      if ( $this->db->numRows == 0 )
        return FALSE;

      $userData = $this->db->fetch_array();
      return $userData;
    }

    private function verify_register_user( $data ) {
      $this->access = FALSE;

      try {

        if ( !$this->is_token_valid() )
          throw new Exception( 'invalid_submission' );

        if ( empty( $data['username'] ) )
          throw new Exception( 'empty_username' );

        if ( empty( $data['password'] ) || empty( $data['confirmpass'] ) )
          throw new Exception( 'empty_password' );

        if ( !$this->check_password( $data['password'], $data['confirmpass'] ) )
          return FALSE;

        if ( empty( $data['firstname'] ) )
          throw new Exception( 'empty_firstname' );

        if ( empty( $data['lastname'] ) )
          throw new Exception( 'empty_lastname' );

        if ( empty( $data['email'] ) )
          throw new Exception( 'empty_email' );

        if ( !$this->db->is_email_valid( $data['email'] ) )
          throw new Exception( 'invalid_email' );

        if ( $this->check_user_email( $data ) )
          throw new Exception( 'email_exist' );

        $this->register_user( $data );

      } catch ( Exception $e ) {
        $this->access = FALSE;
        $this->errors[] .= $e->getMessage();
      }

    }

    public function add_user( $data ) {
      if ( isset( $data['token'] ) )
        $this->token = $this->db->filter( $data['token'] );

      $this->verify_register_user( $data );
      return $this->access;
    }

    public function register_user( $data ) {
      $encryptPassword = $this->db->_encrypt( $data['password'], ENCRYPT );
      $displayName = $data['firstname'] . ' ' . $data['lastname'];

      $insertData = array(
        "user_name" => $data['username'],
        "user_pass" => $encryptPassword,
        "user_old_pass" => $encryptPassword,
        "user_fname" => $data['firstname'],
        "user_lname" => $data['lastname'],
        "user_display_name" => $displayName,
        "user_access_level" => $data['access'],
        "user_email" => $data['email'],
        "user_created" => date( DB_DATE_TIME ),
        );

      if ( isset( $data['admin'] ) || $data['admin'] == TRUE ) {
        $inserted = $this->db->insert( $this->adminTableName, $insertData );
      } else {
        $inserted = $this->db->insert( $this->userTableName, $insertData );
      }

      if ( $inserted ) {
        $this->access = TRUE;
      } else {
        $this->access = FALSE;
      }
    }

    public function errors(){
      foreach( $this->errors as $key => $value )
        return $value;
    }

    public function error_input() {
      foreach($this->errorInput as $key => $value)
        return $value;
    }

    public function success(){
      return $this->confirmation;
    }

    public function _encode( $value, $type ) {
      return $this->db->output( $value, $type );
    }

	}

?>