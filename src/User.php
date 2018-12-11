<?php
/**
 * Class to handle user auth
 *
 * Best Practices
 *   http://stackoverflow.com/questions/549/the-definitive-guide-to-form-based-website-authentication
 *   http://www.troyhunt.com/2012/05/everything-you-ever-wanted-to-know.html
 *   http://fishbowl.pastiche.org/2004/01/19/persistent_login_cookie_best_practice/
 */

namespace bdk;

use bdk\ArrayUtil;
use bdk\Cookie;
use bdk\Db\Mysql;
use bdk\Session;
use bdk\Str;

/**
 * @param int    $len   length of returned string
 * @param string $chars character pool
 *
 * @return string
 */
/*
function rand_string($len=12, $chars=null)
{
	$str = '';
	if ( is_array($chars) )
		$chars = implode('', $chars);
	if ( empty($chars) )
		$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	$max = strlen($chars) - 1;
	for ( $i = 0; $i < $len; $i++ ) {
		$str .= $chars[mt_rand(0, $max)];
	}
	return $str;
}
*/

/**
 * User class
 *
 * Methods
 * 			authenticate(username, password)
 *			authenticateSocial(authResponse)
 * 			create
 * 			get
 * 			getDbInstance
 * 			logout
 * 			search
 * 			setEmailToken
 * 			setPersistToken
 *			socialLink(provider, providerkey, userid)
 * 			update
 *			verifyEmailToken
 * private	genToken
 * private	hashPassword
 * protected log
 * private 	resume
 * private	verifyPassword
 * private	verifyPersistToken
 */
class User
{

	protected $cfg = array();
	protected $userid = null;
	protected $user = array();		// will be a reference to session
	protected $auth_via = null;		// cookie, form, session, social
	public $auth = 0;				// 0, 1 (weak/idle), 2 (strong)
	public $db;
	protected $debug;

	/**
	 * Constructor
	 *
	 * @param Mysql $db  database instance
	 * @param array $cfg configuration
	 */
	public function __construct($db, $cfg = array())
	{
		$this->debug = \bdk\Debug::getInstance();
		$this->debug->groupCollapsed(__METHOD__);
		if (!defined('PASSWORD_DEFAULT')) {
			require_once __DIR__.'/functions_password_hash.php';
		}
		$cfg_opts = array(
			/*
			'db' => array(
				'host'		=> 'localhost',
				'username'	=> 'username',
				'password'	=> 'password',
				'db'		=> 'websiteusers',
				// 'debug'		=> true,
			),
			'db_opts' => array(
				'collapse' => true,
			),
			*/
			'sessionPath' => 'user',
			'passwordHashAlgo' => PASSWORD_DEFAULT,
			'passwordHashOptions' => array(
				// 'cost' => 12,
			),
			'tokenLen'			=> 32,
			'maxIdle'			=> 60*15,			// 15 min
			'datetimeFormat'	=> 'M j, Y, g:i a',
			'emailTokenLifetime' => 60*60*8,		// 8 hours	// @todo
			'persistCookie' => array(
				'name'		=> 'persist_token',
				'lifetime'	=> 60*60*24*30,	// 60 days
				'httponly'	=> true,
			),
			'userForeignKeys' => array(
				'timezone' => array(
					'userCol'		=> 'timezoneid',
					'getValQuery'	=> 'SELECT `timezoneid` FROM `timezones` WHERE `timezone` = [::value::]',
					'getJoin'		=> 'LEFT JOIN `timezones` USING (`timezoneid`)',
					'ignoreKey'		=> true,
				),
			),
		);
		$this->cfg = ArrayUtil::mergeDeep($cfg_opts, $cfg);
		// $this->db = new Mysql($this->cfg['db'], $this->cfg['db_opts']);	// @todo : only create db instance when need db
		$this->db = $db;
		Session::startIf();
		$this->resume();
		// $this->debug->log('user', $this->user);
		$this->debug->info('auth', $this->auth);
		$this->debug->groupEnd();
		return;
	}

	/**
	 * Verify username/password combo
	 * does not load any user info
	 *
	 * @param string $username username or email
	 * @param string $password password (clear)
	 *
	 * @return mixed userid or false
	 */
	public function authenticate($username, $password)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = false;
		$users = $this->search(array(
			'username'	=> $username,
			'email'		=> $username,
		));
		if ($users && $this->verifyPassword($password, $users[0]['password'])) {
			$this->debug->info('login success');
			$this->log('login', $users[0]['userid']);
			$return = $users[0]['userid'];
		} else {
			$this->debug->warn('login failed');
			$userid = $users
				? $users[0]['userid']
				: null;
			$this->log('loginFail', $userid);
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * check if auth response user exists in auth_social table
	 * does not load any user info
	 *
	 * @param array $authResponse Opauth auth response array
	 *
	 * @return mixed userid or false
	 */
	public function authenticateSocial($authResponse)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = false;
		$providerkey = $authResponse['auth']['uid'];
		$query = 'SELECT * FROM `user_social` WHERE `providerkey` = '.$this->db->quoteValue($providerkey);
		$users = $this->db->query($query)[0];
		$this->debug->table($users, 'users');
		if ($users) {
			$userid = $users[0]['userid'];
			$this->log('login', $userid, array('provider'=>$authResponse['auth']['provider']));
			$return = $userid;
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Creates user in DB
	 * Does NOT load user into current session
	 *
	 * @param array $vals values
	 *
	 * @return array
	 */
	public function create($vals)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = array(
			'success' => true,
			'errorDesc' => '',
			'userid' => null,
		);
		$noQuoteCols = array(
			'createdon',
			'lastactivity',
		);
		$vals = array_change_key_case($vals);
		$vals['createdon']		= 'NOW()';
		$vals['lastactivity']	= 'NOW()';
		$valsOther = array();
		$cols = $this->db->getColProperties('user');
		$this->debug->table($cols, 'cols');
		$col_names = array_keys($cols);
		foreach ($vals as $k => $v) {
			if (isset($this->cfg['userForeignKeys'][$k])) {
				$fka = $this->cfg['userForeignKeys'][$k];
				$k_new = $fka['userCol'];
				$v_new = Str::quickTemp('('.$fka['getValQuery'].')', array('value'=>$this->db->quoteValue($v)));
				unset($vals[$k]);
				$vals[$k_new] = $v_new;
				$noQuoteCols[] = $k_new;
			} elseif (!in_array($k, $col_names)) {
				$valsOther[$k] = $v;
				unset($vals[$k]);
			}
		}
		$result = $this->db->rowMan('user', $vals, null, $noQuoteCols);
		$this->debug->log('result', $result);
		if ($result) {
			$this->debug->info('user created');
			$userid = $result;
			$this->log('userCreate', $userid);
			if (!empty($valsOther)) {
				$return = $this->update($valsOther, $userid);
			}
			$return['userid'] = $userid;
		} else {
			$return['success'] = false;
			$return['errorDesc'] = $this->db->error;
		}
		$this->debug->log('return', $return);
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Returns a datetime (or timestamp) formatted for given timezone
	 *
	 * @param integer|string $datetime datetime string or timestamp int   (defaults to current datetime)
	 * @param string         $format   date format (defaults to cfg[datetime_format])
	 * @param string         $timezone timezone string (if not passed will use _user['timezone'] followed by server's timezone)
	 *
	 * @return string
	 */
	public function datetimeLocal($datetime = null, $format = null, $timezone = null)
	{
		if (empty($datetime)) {
			$datetime = date('Y-m-d H:i:s');
		}
		if (empty($format)) {
			$format = $this->cfg['datetimeFormat'];
		}
		$tz_server_string = date_default_timezone_get();
		if (empty($timezone)) {
			$timezone = !empty($this->user['timezone'])
				? $this->user['timezone']
				: $tz_server_string;
		}
		$this->debug->groupCollapsed(__METHOD__, $datetime, $format);
		if (is_int($datetime)) {
			$datetime = '@'.$datetime;
		}
		$this->debug->log('tz_server_string', $tz_server_string);
		$tz_server	= new \DateTimeZone($tz_server_string);
		$tz_user	= new \DateTimeZone($timezone);
		$datetime	= new \DateTime($datetime, $tz_server);
		$datetime->setTimezone($tz_user);
		$str = $datetime->format($format);
		$this->debug->log('str', $str);
		$this->debug->groupEnd();
		return $str;
	}

	/**
	 * Returns user information
	 *
	 * @param integer $userid userid
	 * @param array   $opts   options
	 *
	 * @return array
	 */
	public function get($userid = null, $opts = array())
	{
		$this->debug->groupCollapsed(__METHOD__, $userid);
		$opts = array_merge(array(
			'localtime' => true,	// true: return formatted date/time string for user's timezone
		), $opts);
		$vals_datetime = array('createdon','lastactivity','previousactivity');
		if (is_null($userid) || $userid == $this->userid) {
			$this->debug->log('use current user');
			$user = $this->user;
		} else {
			$ignore = array( 'password' );
			$joins = array();
			foreach ($this->cfg['userForeignKeys'] as $fka) {
				if (!empty($fka['getJoin'])) {
					$joins[] = $fka['getJoin'];
				}
				if (!empty($fka['ignoreKey'])) {
					$ignore[] = $fka['userCol'];
				}
			}
			$joins = !empty($joins)
				? ' '.implode(' ', $joins)
				: '';
			$query = 'SELECT * FROM `user`'
				.' LEFT JOIN `user_auth` USING (`userid`)'
				.$joins
				.' WHERE `user`.`userid` = '.$this->db->quoteValue($userid);
			$users = $this->db->query($query)[0];
			$this->debug->table($users, 'users');
			$user = $users[0];
			foreach ($vals_datetime as $k) {
				if (isset($user[$k])) {
					$user[$k] = strtotime($user[$k]);
				}
			}
			$query = 'SELECT `right` FROM `user_rights` WHERE `userid` = '.$this->db->quoteValue($userid);
			$rights = $this->db->query($query)[0];
			foreach ($rights as $k => $a) {
				$rights[$k] = $a['right'];
			}
			$user['previousactivity'] = $user['lastactivity'];
			$user['rights'] = $rights;
			foreach ($ignore as $k) {
				unset($user[$k]);
			}
		}
		if ($opts['localtime']) {
			foreach ($vals_datetime as $k) {
				$user[$k] = $this->datetimeLocal($user[$k]);
			}
		}
		$this->debug->log('user', $user);
		$this->debug->groupEnd();
		return $user;
	}

	/**
	 * Log user out
	 *
	 * @return void
	 */
	public function logout()
	{
		$this->debug->groupCollapsed(__METHOD__);
		if ($this->auth) {
			$this->setPersistToken(false);	// more efficient to remove first (while session still exists)
			$this->log('logout');
		}
		ArrayUtil::path($_SESSION, $this->cfg['sessionPath'], array());	// clear session
		$this->userid = null;
		$this->auth = 0;
		$this->debug->groupEnd();
		return;
	}

	/**
	 * search for user by username, email, or userid
	 *
	 * @param array $vals values
	 *
	 * @return array rows
	 */
	public function search($vals)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$this->debug->log('vals', $vals);
		$return = array();
		if ($vals) {
			$where = array();
			$where_or = array();
			if (!empty($vals['username'])) {
				$where_or[] = '`username` = '.$this->db->quoteValue($vals['username']);
				unset($vals['username']);
			}
			if (!empty($vals['email'])) {
				$where_or[] = '`email` = '.$this->db->quoteValue($vals['email']);
				unset($vals['email']);
			}
			if ($where_or) {
				$where[] = '( '.implode(' OR ', $where_or).' )';
			}
			foreach ($vals as $k => $v) {
				$col = '`'.$k.'`';
				if ($k == 'userid') {
					$col = '`user`.'.$col;
				}
				$where[] = $col.' = '.$this->db->quoteValue($v);
			}
			$where = !empty($where)
				? ' WHERE '.implode(' AND ', $where)
				: '';
			$query = 'SELECT * FROM `user`'
				.' LEFT JOIN `user_auth` USING (`userid`)'
				// .' LEFT JOIN `specialties` USING (`specialtyid`)'
				.$where;
			$return = $this->db->query($query)[0];
		}
		$this->debug->table($return, 'return');
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Set current user by userid
	 *
	 * @param integer $userid userid
	 *
	 * @return void
	 */
	public function setCurrent($userid)
	{
		$this->debug->groupCollapsed(__METHOD__, $userid);
		Session::start();
		$user = $this->get($userid);
		// initialize session array
		ArrayUtil::path($_SESSION, $this->cfg['sessionPath'], array());
		// create link to session array
		// $this->user = &ArrayUtil::path($_SESSION, $this->cfg['sessionPath']);
        $path = $this->cfg['sessionPath'];
        if (!\is_array($path)) {
            $path = \array_filter(\preg_split('#[\./]#', $path), 'strlen');
        }
        $this->user =& $_SESSION;
        foreach ($path as $k) {
        	if (!isset($this->user[$k])) {
        		$this->user[$k] = array();
        	}
        	$this->user =& $this->user[$k];
        }
		// load session array
		$this->user = $user;
		$this->user['_ts_cur'] = time();
		$this->userid = $this->user['userid'];
		$this->update(array('lastactivity'=>'NOW()'));
		$this->debug->groupEnd();
		return;
	}

	/**
	 * Stores token in db / returns base64_encoded token
	 *
	 * @param integer $userid optional userid
	 *
	 * @return mixed false on error
	 */
	public function setEmailToken($userid = null)
	{
		$this->debug->groupCollapsed(__METHOD__);
		if (!isset($userid)) {
			$userid = $this->userid;
		}
		$this->debug->log('userid', $userid);
		$token = $this->genToken();
		/*
			remove any existing token
		*/
		$where = array( 'userid' => $userid);
		$response = $this->db->rowMan('email_tokens', null, $where);
		/*
			store hashed token in db
		*/
		$vals = array(
			'userid'		=> $userid,
			'token_hash'	=> sha1($token),
		);
		$response = $this->db->rowMan('email_tokens', $vals);
		$this->log('pwForgotTokenGen', $userid);	// @todo forgot_token_gen??
		$return = $response !== false
			? base64_encode($token)
			: false;
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * sets cookie
	 * stores unsalted SHA1 hash in db
	 *
	 * @param boolean $set true (false to remove cookie);
	 *
	 * @return mixed set: array (cookie value), remove: null
	 */
	public function setPersistToken($set = true)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$cookieParams = $this->cfg['persistCookie'];
		$return = null;
		if ($set) {
			$token = $this->genToken();	// raw binary
			$cookieParams['value'] = array(
				'username'	=> $this->user['username'],
				'token'		=> $token,
			);
			#$this->debug->log('value', $cookie['value']);
			Cookie::set($cookieParams);
			// store cookie / username combo in db
			$values = array(
				'userid'		=> $this->userid,
				'token_hash'	=> sha1($token),
			);
			$this->db->rowMan('persist_tokens', $values);
			$return = $cookieParams['value'];
		} elseif (isset($_COOKIE[ $cookieParams['name'] ])) {
			$vals = Cookie::get($cookieParams['name']);
			if (is_array($vals) && isset($vals['username']) && isset($vals['token'])) {
				// remove token from db
				// determine userid
				$userid = null;
				if (isset($this->user['username']) && $this->user['username'] === $vals['username']) {
					$userid = $this->userid;
				} else {
					$users = $this->search(array('username'=>$vals['username']));
					if ($users) {
						$userid = $users[0]['userid'];
					}
				}
				if ($userid) {
					$where = array(
						'userid'		=> $userid,
						'token_hash'	=> sha1($vals['token']),
					);
					$this->db->rowMan('persist_tokens', null, $where);
				}
				// remove cookie
				$cookieParams['value'] = null;
				Cookie::set($cookieParams);
			}
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * Link user to a authentication provider
	 *
	 * @param string  $provider    Facebook, Twiter, etc
	 * @param string  $providerKey UID provided by provider
	 * @param integer $userid      optional
	 *
	 * @todo check if providerKey linked to another account... if so remove that linkage
	 *
	 * @return void
	 */
	public function socialLink($provider, $providerKey, $userid = null)
	{
		$this->debug->groupCollapsed(__METHOD__);
		if ($userid === null) {
			$userid = $this->userid;
		}
		$vals = array(
			'userid'		=> $userid,
			'provider'		=> strtolower($provider),
			'providerkey'	=> $providerKey,
		);
		/*
			search if social is linked to another account
		*/
		$where = array(
			'provider'		=> strtolower($provider),
			'providerkey'	=> $providerKey,
		);
		/*
		foreach ($where as $k => $v) {
			unset($where[$k]);
			$where[] = '`'.$k.'`='.$this->db->quoteValue($v);
		}
		$where = implode(' AND ', $where);
		*/
		$found = $this->db->query('SELECT * FROM `user_social` '.$this->db->buildWhere($where))[0];
		if ($found) {
			$this->socialUnlink($found[0]['provider'], $found[0]['providerkey']);
		}
		$this->db->rowMan('user_social', $vals);
		$this->log('socialLink', $userid, array(
			'provider'		=> strtolower($provider),
			'providerKey'	=> $providerKey,
		));
		$this->debug->groupEnd();
		return;
	}

	/**
	 * unlink user from authentication provider
	 *
	 * @param string  $provider Facebook, Twiter, etc
	 * @param integer $userid   optional
	 *
	 * @return void
	 */
	public function socialUnlink($provider, $userid = null)
	{
		$this->debug->groupCollapsed(__METHOD__);
		if ($userid === null) {
			$userid = $this->userid;
		}
		$where = array(
			'userid'	=> $userid,
			'provider'	=> strtolower($provider),
		);
		$this->db->rowMan('user_social', null, $where);
		$this->log('sociaUnlink', $userid, array(
			'provider' => strtolower($provider),
		));
		$this->debug->groupEnd();
		return;
	}

	/**
	 * Update a user
	 *   password should be passed clear/unhashed
	 *
	 * @param array   $vals   values to update
	 * @param integer $userid optional userid
	 *
	 * @return array
	 */
	public function update($vals, $userid = null)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = array(
			'success'	=> true,
			'errorDesc'	=> '',
		);
		$vals = array_change_key_case($vals);
		$this->debug->log('vals', $vals);
		$vals_auth = array();
		$current_user = true;
		if (isset($userid) && $userid != $this->userid) {
			$current_user = false;
		} else {
			$userid = $this->userid;
		}
		foreach (array('username','password') as $k) {
			if (isset($vals[$k])) {
				$vals_auth[$k] = $vals[$k];
				unset($vals[$k]);
			}
		}
		if (!empty($vals)) {
			$noQuoteCols = array();
			foreach ($vals as $k => $v) {
				if (isset($this->cfg['userForeignKeys'][$k])) {
					$fka = $this->cfg['userForeignKeys'][$k];
					$k_new = $fka['userCol'];
					$v_new = Str::quickTemp('('.$fka['getValQuery'].')', array('value'=>$this->db->quoteValue($v)));
					unset($vals[$k]);
					$vals[$k_new] = $v_new;
					$noQuoteCols[] = $k_new;
				}
			}
			if (!empty($vals['lastactivity']) && preg_match('/\w+\(.*?\)/', $vals['lastactivity'])) {
				$noQuoteCols[] = 'lastactivity';
			}
			$this->debug->log('vals', $vals);
			$result = $this->db->rowMan('user', $vals, array('userid' => $userid), $noQuoteCols);
			if ($result === false) {
				$return['success'] = false;
				$return['errorDesc'] = $this->db->error;
				$this->debug->warn('errors', $this->db->errors);
				$this->debug->warn('error', $this->db->resource->error);
			} elseif ($current_user) {
				unset($vals['lastactivity']);	// we don't want to overwrite the current session's value
				$this->user = array_merge($this->user, $vals);
			}
		}
		if ($return['success'] && !empty($vals_auth)) {
			if (isset($vals_auth['password'])) {
				$vals_auth['password'] = $this->hashPassword($vals_auth['password']);
				$this->log('pwChange', $userid);
			}
			// try update first
			$result = $this->db->rowMan('user_auth', $vals_auth, array('userid' => $userid));
			$this->debug->log('result', $result);
			if ($result === 0) {
				// no rows affected -> try insert
				$vals_auth['userid'] = $userid;
				$result = $this->db->rowMan('user_auth', $vals_auth);
				$this->debug->log('result', $result);
			}
			if ($result === false) {
				$return['success'] = false;
				$return['errorDesc'] = $this->db->error;
			}
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * verifies a token sent via email
	 * searches for token in db
	 * if found will remove from db
	 *
	 * @param string $token token value
	 *
	 * @return mixed userid or false
	 */
	public function verifyEmailToken($token)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = false;
		$token = base64_decode($token);
		$vals = array(
			'token_hash' => $this->db->quoteValue(sha1($token)),
		);
		$query = Str::quickTemp('SELECT * FROM `email_tokens`
			WHERE `token_hash` = [::token_hash::]', $vals);
		$rows = $this->db->query($query)[0];
		$this->debug->table($rows, 'rows');
		if ($rows) {
			// found token in table
			$return = $rows[0]['userid'];
			// remove the token
			$where = $vals;
			$this->db->rowMan('email_tokens', null, $where);
			$this->log('pwForgotTokenSuccess', $rows[0]['userid']);
		} else {
			$this->log('pwForgotTokenFail');
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * generate crypto strength token
	 *
	 * @param integer $tokenLen optional token length (bytes)
	 *
	 * @return string raw binary string
	 */
	private function genToken($tokenLen = null)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$tokenLen = isset($tokenLen)
			? $tokenLen
			: $this->cfg['tokenLen'];
		$token = '';
		if (function_exists('mcrypt_create_iv') && !defined('PHALANGER')) {
			$token = mcrypt_create_iv($tokenLen, MCRYPT_DEV_URANDOM);
		}
		if (empty($token) && function_exists('openssl_random_pseudo_bytes')) {
			$token = openssl_random_pseudo_bytes($tokenLen);
		}
		if (empty($token) && is_readable('/dev/urandom')) {
			$file = fopen('/dev/urandom', 'r');
			$len = strlen($token);
			while ($len < $tokenLen) {
				$token .= fread($file, $tokenLen - $len);
				$len = strlen($token);
			}
			fclose($file);
		}
		$this->debug->log('token', $token);
		$this->debug->groupEnd();
		return $token;
	}

	/**
	 * returns hashed password
	 *
	 * @param string $pwClear clear-text password
	 *
	 * @return string hased pw
	 */
	private function hashPassword($pwClear)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$this->debug->time('hash');
		$hash = password_hash($pwClear, $this->cfg['passwordHashAlgo'], $this->cfg['passwordHashOptions']);
		$this->debug->log('hash', strlen($hash), $hash);
		$this->debug->timeEnd('hash');
		$this->debug->groupEnd();
		return $hash;
	}

	/**
	 * Log event
	 *
	 * @param string  $event          login, loginFail, etc (see db)
	 * @param integer $userid         optional
	 * @param array   $additionalData will get json encoded
	 *
	 * @return void
	 */
	protected function log($event, $userid = null, $additionalData = null)
	{
		$this->debug->groupCollapsed(__METHOD__, $event);
		if (!isset($userid)) {
			$userid = $this->userid;
		}
		$vals = array(
			'event'		=> $event,
			'ipaddress'	=> $_SERVER['REMOTE_ADDR'],
			'userid'	=> $userid,
			'additional_data' => $additionalData
				? json_encode($additionalData)
				: null,
		);
		$this->db->rowMan('log', $vals);
		$this->debug->groupEnd();
		return;
	}

	/**
	 * attempts to load user data from session or via persistant login cookie
	 *
	 * @return void
	 */
	private function resume()
	{
		$this->debug->groupCollapsed(__METHOD__);
		if (isset($_SESSION)) {
			$this->user = ArrayUtil::pathGet($_SESSION, $this->cfg['sessionPath']);
		}
		/*
			Session?
		*/
		if (!empty($this->user)) {
			$this->debug->info('resuming from session');
			if (isset($this->user['_ts_cur'])) {
				$this->user['lastactivity'] = $this->user['_ts_cur'];
			}
			$this->userid = $this->user['userid'];
			$this->auth_via = 'session';
			$this->auth = 2;
			if ($this->user['lastactivity'] + $this->cfg['maxIdle'] < time()) {
				$this->debug->warn('idle');
				$this->debug->log('lastactivity', $this->user['lastactivity']);
				$this->auth = 0;
			} else {
				$this->update(array('lastactivity'=>'NOW()'));
			}
		}
		/*
			Persist cookie?
		*/
		if (empty($this->auth) && isset($_COOKIE[ $this->cfg['persistCookie']['name'] ])) {
			$persist_vals = Cookie::get($this->cfg['persistCookie']['name']);
			$userid = isset($persist_vals['username']) && isset($persist_vals['token'])
				? $this->verifyPersistToken($persist_vals['username'], $persist_vals['token'])	// drops from db if found
				: null;
			if ($userid) {
				$this->debug->info('resuming from persist token');
				$this->setCurrent($userid);
				$this->setPersistToken();
				$this->auth_via = 'cookie';
				$this->auth = 1;
			}
		}
		/*
			Social Cookie?
		*/
		if (empty($this->auth)) {
		}
		if (!empty($this->auth)) {
			$this->user['_ts_cur'] = time();
		}
		$this->debug->groupEnd();
	}

	/**
	 * verify password
	 *
	 * @param string $pwClear password (clear)
	 * @param string $pwHash  password (encrypted)
	 *
	 * @return boolean
	 */
	private function verifyPassword($pwClear, $pwHash)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = false;
		$hashAlgo = $this->cfg['passwordHashAlgo'];
		$hashOpts = $this->cfg['passwordHashOptions'];
		$return = password_verify($pwClear, $pwHash);
		$this->debug->log('return', $return);
		if ($return && password_needs_rehash($pwHash, $hashAlgo, $hashOpts)) {
			if ($this->userid) {
				// store new hash
				$pwHash = $this->hashPassword($pwClear);
				$vals = array( 'password' => $pwHash );
				$where = array( 'userid' => $this->userid );
				$this->db->rowMan('user_auth', $vals, $where);
			}
		}
		$this->debug->groupEnd();
		return $return;
	}

	/**
	 * searches for token in db
	 * if found will drop from db
	 * DOES not create a new token/cookie
	 *
	 * @param string $username username associated with token
	 * @param string $token    auth token
	 *
	 * @return mixed userid or false
	 */
	private function verifyPersistToken($username, $token)
	{
		$this->debug->groupCollapsed(__METHOD__);
		$return = false;
		$vals = array(
			'username'		=> $this->db->quoteValue($username),
			'token_hash'	=> $this->db->quoteValue(sha1($token)),
		);
		$query = Str::quickTemp('SELECT * FROM `persist_tokens`
			LEFT JOIN `user_auth`
				ON `user_auth`.`username` = [::username::]
				AND `user_auth`.`userid` = `persist_tokens`.`userid`
			WHERE `token_hash` = [::token_hash::]
			ORDER BY `datetime` DESC', $vals);
		$rows = $this->db->query($query)[0];
		$this->debug->table($rows, 'rows');
		if ($rows) {
			// found token in table
			$return = $rows[0]['userid'];
			// remove the token
			$where = array();
			foreach (array('userid','datetime','token_hash') as $k) {
				$where[$k] = $rows[0][$k];
			}
			$this->db->rowMan('persist_tokens', null, $where);
			$this->log('persistTokenSuccess', $rows[0]['userid']);
		} else {
			$this->log('persistTokenFail');
		}
		$this->debug->groupEnd();
		return $return;
	}
}
