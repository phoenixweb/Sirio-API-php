<?php
/*
 * Please define all the SIRIO CONSTANTS:
 * 
 *  SIRIO_ACCOUNT
 *  SIRIO_USER
 *  SIRIO_PASS
 * 
 */

class SirioService {
	private $host = null;
	private $timeout = 30;

	protected $username = null;
	protected $password = null;
	protected $real_ip = NULL;
	protected $postdatavar = "data=";
	
	private $session_logged = false;
	private $session_cookies = array();
	
	function __construct($account = false, $username = false, $password = false) {
		$this->real_ip      = addslashes(trim(strip_tags($_SERVER['REMOTE_ADDR'])));

		if (!$account && defined("SIRIO_ACCOUNT"))		$account	= SIRIO_ACCOUNT;
		if (!$username && defined("SIRIO_USER"))		$username	= SIRIO_USER;
		if (!$password && defined("SIRIO_PASS"))		$password	= SIRIO_PASS;
		
		if ($account)	$this->setAccount($account);
		if ($username)	$this->setUsername($username, $password);
	}
	
	function __destruct() {
		$this->logout();
	}

	function setAccount($account) {
		$this->account	= $account;
		$this->setHost("https://".$account.".app.sirio.com/gate/bot.php");
	}

	function setUsername($username, $password) {
		$this->username	= $username;
		$this->password	= $password;
	}

	function setHost($new_host) {
		$this->host		= $new_host;
	}
	
	public function getTimeout() {
		return $this->timeout;
	}

	public function setTimeout($timeout) {
		if (!is_numeric($timeout) || $timeout < 0) $this->error("Timeout must be a positive number");
		$this->timeout = $timeout;
	}
	
	public function login($username = false, $password = false) {
		if ($username) $this->setUsername($username, $password);
		
		if ($this->username=="")		$this->error("Account non impostato"); 
		if ($this->password=="")	$this->error("Password non impostata"); 
		
		$url = $this->host."?login=true";
		$dataset = array(
			"username" => $this->username,
			"password" => $this->password,
		);
		$datastring = $this->postdatavar.urlencode(json_encode($dataset))."&ip=".$this->real_ip;
		$body = $this->curlOpen($url,$datastring);
		$response = $this->readResponse($body);
		if ($response["status"]) $this->session_logged = true;
		return $response;
	}
	
	public function logout() {
		if (!$this->session_logged) {
			$this->trace("login non effettuato");
			return true;
		}

		$url = $this->host."?logout=true";
		$this->curlOpen($url);
	}
	
	
	public function sendCommand($module, $action, $dataset) {
		if (!$this->session_logged) {
			$this->trace("login non ancora effettuato, lo faccio in automatico");
			$this->login();
		}

		$this->trace("Invio richiesta al server");
		$this->trace($dataset,1);
		
		$url =  $this->host."?module=".urlencode($module)."&action=".urlencode($action);
		$datastring = $this->postdatavar.urlencode(json_encode($dataset))."&ip=".$this->real_ip;
		$body = $this->curlOpen($url,$datastring);
		return $this->readResponse($body);
	}
	
	private function readResponse($body) {		
		$json_response = json_decode($body, true);

		if (json_last_error()==JSON_ERROR_NONE) {
			$this->trace("Risposta ricevuta dal server");
			$this->trace($json_response,1);
			return $json_response;
		}
		
		$this->trace("Impossibile interpretare il messaggio di risposta");
		$this->trace($body,1);
		switch(json_last_error()) {
			case JSON_ERROR_DEPTH:
				$this->error(' - Maximum stack depth exceeded');
			break;

			case JSON_ERROR_STATE_MISMATCH:
				$this->error(' - Underflow or the modes mismatch');
			break;	

			case JSON_ERROR_CTRL_CHAR:
				$this->error(' - Unexpected control character found');
			break;

			case JSON_ERROR_SYNTAX:
				$this->error(' - Syntax error, malformed JSON');
			break;

			case JSON_ERROR_UTF8:
				$this->error(' - Malformed UTF-8 characters, possibly incorrectly encoded');
			break;
		}

		$this->error('Errore nella codifica del messaggio di risposta');
	}
	
	private function curlOpen($url, $post = false) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL,				$url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,	$this->timeout);
		curl_setopt($ch, CURLOPT_HEADER,			true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,	true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,	true);
	
		//preserve cookies
		if (count($this->session_cookies)) {
			curl_setopt($ch, CURLOPT_COOKIE, implode(";",$this->session_cookies));
		}
		
		//set post data
		if ($post!=false) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}

		$this->trace("Download of the url <b>".$url."</b>");
		$response		= curl_exec($ch);
		$info			= curl_getinfo($ch);

		if ($response===false) {
			$this->error('Curl error: ' . curl_error($ch) .' '. curl_error($ch));
		}

		$this->trace("Split body and header");
		$header_size = $info["header_size"];
		$header	= substr($response, 0, $header_size);
		$body	= substr($response, $header_size);

		$cookies = [];
		if (preg_match_all('|Set-Cookie: (.*);|U', $header, $cookies)) {
			$this->session_cookies = $cookies[1];
			$this->trace($this->session_cookies,1);
		} else {
			$this->trace("Nessun Cookies trovato");
		}
	
		$this->trace("Downloaded ".strlen($body)." byte");
		return $body;
	}
	
	
	//reclass with your own Log function
	private function trace($obj) {
		
	}
	
	//reclass with your own Error function
	private function error($error) {
		throw new ErrorException($error);
	}

};
