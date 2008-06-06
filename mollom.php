<?php
/**
 * Mollom class
 *
 * This source file can be used to communicate with mollom (http://mollom.com)
 *
 * License
 * Copyright (c) 2008, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 * - Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * - Neither the name of Tijs Verkoyen, PHP Mollom nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the copyright holders and contributors "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <mollom@verkoyen.eu>
 * @version			1.0
 *
 * @copyright		Copyright (c) 2008, Tijs Verkoyen. All rights reserved.
 * @license			http://mollom.local/license BSD License
 */

class Mollom
{
	/**
	 * Your private key
	 *
	 * Set it by calling Mollom::setPrivateKey('<your-key>');
	 *
	 * @var	string
	 */
	private static $privateKey;


	/**
	 * Your public key
	 *
	 * Set it by calling Mollom::setPublicKey('<your-key>');
	 *
	 * @var	string
	 */
	private static $publicKey;


	/**
	 * The default server
	 *
	 * No need to change
	 *
	 * @var	string
	 */
	private static $serverHost = 'xmlrpc.mollom.com';


	/**
	 * The cache for the serverlist
	 *
	 * No need to change
	 *
	 * @var	array
	 */
	private static $serverList = array();


	/**
	 * Default timeout
	 *
	 * @var	int
	 */
	private static $timeout = 10;


	/**
	 * The default user-agent
	 *
	 * Change it by calling Mollom::setUserAgent('<your-user-agent>');
	 *
	 * @var	string
	 */
	private static $userAgent = 'MollomPHP/0.1';


	/**
	 * The current Mollom-version
	 *
	 * No need to change
	 *
	 * @var	string
	 */
	private static $version = '1.0';


	/**
	 * Build the value so we can use it in XML-RPC requests
	 *
	 * @return	string
	 * @param	mixed $value
	 */
	private function buildValue($value)
	{
		// get type
		$type = gettype($value);

		// build value
		switch ($type)
		{
			case 'string':
				return '<value><string><![CDATA['. $value .']]></string></value>'."\n";

			case 'array':
				// init struct
				$struct = '<value>'."\n";
				$struct .= '	<struct>'."\n";

				// loop array
				foreach ($value as $key => $value) $struct .= str_replace("\n", '', '<member>'. "\n" .'<name>'. $key .'</name>'. self::buildValue($value) .'</member>') . "\n";

				$struct .= '	</struct>'."\n";
				$struct .= '</value>'."\n";

				// return
				return $struct;

			default:
				return '<value>'. $value .'</value>'."\n";
		}
	}


	/**
	 * Validates the answer for a CAPTCHA
	 *
	 * When the answer is false, you should request a new image- or audio-CAPTCHA, make sure your provide the current Mollom-sessionid.
	 * The sessionid will be used to track spambots that try to solve CAPTHCA's by brute force.
	 *
	 * @return	bool
	 * @param	string $sessionId
	 * @param	string $solution
	 */
	public static function checkCaptcha($sessionId, $solution)
	{
		// redefine
		$sessionId = (string) $sessionId;
		$solution = (string) $solution;

		// set autor ip
		$authorIp = null;
		$authorIp = (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != '') ? $_SERVER['REMOTE_ADDR'] : null;
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '') $authorIp = array_pop(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

		// set parameters
		$parameters['session_id'] = $sessionId;
		$parameters['solution'] = $solution;
		if($authorIp != null) $parameters['author_ip'] = (string) $authorIp;

		// do the call
		$responseString = self::doCall('checkCaptcha', $parameters);
		// validate
		if(!isset($responseString->params->param->value->boolean)) throw new Exception('Invalid response in checkCapthca.');

		// return
		if((string) $responseString->params->param->value->boolean == '1') return true;

		// fallback
		return false;
	}


	/**
	 * Check if the data is spam or not, and gets an assessment of the datas quality
	 *
	 * This function will be used most. The more data you submit the more accurate the claasification will be.
	 * If the spamstatus is 'unsure', you could send the user an extra check (eg. a captcha).
	 *
	 * REMARK: the Mollom-sessionid is NOT related to HTTP-session, so don't send 'session_id'.
	 *
	 * The function will return an array with 3 elements:
	 * - spam			the spam-status (unknown/ham/spam/unsure)
	 * - quality		an assessment of the content's quality (between 0 and 1)
	 * - session_id		Molloms session_id
	 *
	 * @return	array
	 * @param	string[optional] $sessionId
	 * @param	string[optional] $postTitle
	 * @param	string[optional] $postBody
	 * @param	string[optional] $authorName
	 * @param	string[optional] $authorUrl
	 * @param	string[optional] $authorEmail
	 * @param	string[optional] $authorOpenId
	 * @param	string[optional] $authorId
	 */
	public static function checkContent($sessionId = null, $postTitle = null, $postBody = null, $authorName = null, $authorUrl = null, $authorEmail = null, $authorOpenId = null, $authorId = null)
	{
		// validate
		if($sessionId === null && $postTitle === null && $postBody === null && $authorName === null && $authorUrl === null && $authorEmail === null && $authorOpenId === null && $authorId === null) throw new Exception('Specify at least on argument');

		// init var
		$parameters = array();
		$aReturn = array();

		// add parameters
		if($sessionId !== null) $parameters['session_id'] = (string) $sessionId;
		if($postTitle !== null) $parameters['post_title'] = (string) $postTitle;
		if($postBody !== null) $parameters['post_body'] = (string) $postBody;
		if($authorName !== null) $parameters['author_name'] = (string) $authorName;
		if($authorUrl !== null) $parameters['author_url'] = (string) $authorName;
		if($authorEmail !== null) $parameters['author_mail'] = (string) $authorEmail;
		if($authorOpenId != null) $parameters['author_openid'] = (string) $authorOpenId;
		if($authorId != null) $parameters['author_id'] = (string) $authorId;

		// set autor ip
		$authorIp = null;
		$authorIp = (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != '') ? $_SERVER['REMOTE_ADDR'] : null;
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '') $authorIp = array_pop(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
		if($authorIp != null) $parameters['author_ip'] = (string) $authorIp;

		// do the call
		$responseString = self::doCall('checkContent', $parameters);

		// validate
		if(!isset($responseString->params->param->value->struct->member)) throw new Exception('Invalid response in checkContent.');

		// loop parts
		foreach ($responseString->params->param->value->struct->member as $part)
		{
			// get key
			$key = $part->name;

			// get value
			switch ($part->name)
			{
				case 'spam':
					$value = (string) $part->value->int;

					switch($value)
					{
						case '0':
							$aReturn['spam'] = 'unknown';
						break;

						case '1':
							$aReturn['spam'] = 'ham';
						break;

						case '2':
							$aReturn['spam'] = 'spam';
						break;

						case '3':
							$aReturn['spam'] = 'unsure';
						break;
					}
				break;

				case 'quality':
					$aReturn['quality'] = (float) $part->value->double;
				break;

				case 'session_id':
					$aReturn['session_id'] = (string) $part->value->string;
				break;
			}
		}

		// return
		return $aReturn;
	}


	/**
	 * Make the call
	 *
	 * @return	SimpleXMLElement
	 * @param	string $method
	 * @param	array[optional] $parameters
	 */
	private static function doCall($method, $parameters = array(), $server = null, $counter = 0)
	{
		// count available servers
		$countServerList = count(self::$serverList);

		if($server === null && $countServerList == 0) throw new Exception('No servers found, populate the serverlist. See setServerList().');

		// validate counter
		if($counter >= $countServerList && $countServerList != 0);

		// redefine var
		$method = (string) $method;
		$parameters = (array) $parameters;

		// possible methods
		$aPossibleMethods = array('checkCaptcha', 'checkContent', 'getAudioCaptcha', 'getImageCaptcha', 'getServerList', 'getStatistics', 'sendFeedback', 'verifyKey');

		// check if method is valid
		if(!in_array($method, $aPossibleMethods)) throw new Exception('Invalid method. Only '. implode(', ', $aPossibleMethods) .' are possible methods.');

		// check if public key is set
		if(self::$publicKey === null) throw new Exception('Public key wasn\'t set.');

		// check if private key is set
		if(self::$privateKey === null) throw new Exception('Private key wasn\'t set.');

		// still null
		if($server === null) $server = self::$serverList[$counter];

		// cleanup server string
		$server = str_replace(array('http://', 'https://'), '', $server);

		// create timestamp
		$time = gmdate("Y-m-d\TH:i:s.\\0\\0\\0O", time());

		// create nonce
		$nonce = md5(time());

		// create has
		$hash = base64_encode(
					pack("H*", sha1((str_pad(self::$privateKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
					pack("H*", sha1((str_pad(self::$privateKey, 64, chr(0x00)) ^ (str_repeat(chr(0x36), 64))) .
					$time . ':'. $nonce .':'. self::$privateKey))))
				);

		// add parameters
		$parameters['public_key'] = self::$publicKey;
		$parameters['time'] = $time;
		$parameters['hash'] = $hash;
		$parameters['nonce'] = $nonce;

		// build request
		$requestBody = '<?xml version="1.0"?>' ."\n";
		$requestBody .= '<methodCall>' ."\n";
		$requestBody .= '	<methodName>mollom.'. $method .'</methodName>' ."\n";
		$requestBody .= '	<params>' ."\n";
		$requestBody .= '		<param>'."\n";
		$requestBody .= '			'. self::buildValue($parameters) ."\n";
		$requestBody .= '		</param>'."\n";
		$requestBody .= '	</params>' ."\n";
		$requestBody .= '</methodCall>' ."\n";

		// build headers
		$requestHeaders = 'POST /'. self::$version .' HTTP/1.0'."\n";
		$requestHeaders .= 'User-Agent: '. self::$userAgent ."\n";
		$requestHeaders .= 'Host: '. $server ."\n";
		$requestHeaders .= 'Content-Type: text/xml'."\n";
		$requestHeaders .= 'Content-length: '. strlen($requestBody) ."\n";
		$requestHeaders .= "\n";
		//echo(Options::get( 'mollom:private_key' ).Options::get( 'mollom:public_key' ).self::$publicKey."\n\n".$requestHeaders.$requestBody); exit;
		// create socket
		$socket = @fsockopen($server, 80);
		if($socket === false) throw new Exception('The client couldn\'t connect to '. $server .'.');

		// set timeout
		@stream_set_timeout($socket, self::$timeout);

		// write to socket
		$write = @fwrite($socket, $requestHeaders.$requestBody);
		if($write === false) throw new Exception('The client couldn\'t send data.');

		// get response
		$response = @stream_get_contents($socket);
		if($response === false) throw new Exception('The client couldn\'t read the response data.');

		// get meta
		$info = @stream_get_meta_data($socket);

		// close socket
		@fclose($socket);

		// if connection times out do call again
		if($info['timed_out'] && !isset(self::$serverList[$counter]) && $countServerList != 0) throw new Exception('No more servers available, try to increase the timeout.');

		if($info['timed_out'] && isset(self::$serverList[$counter])) self::doCall($method, $parameters, self::$serverList[$counter], $counter++);

		// process response
		$parts = explode("\r\n\r\n", $response);

		// validate
		if(!isset($parts[0]) || !isset($parts[1])) throw new Exception('Invalid response in doCall.');

		// get headers
		$headers = $parts[0];

		// rebuild body
		array_shift($parts);
		$body = implode('', $parts);

		// validate header
		$aValidHeaders = array('HTTP/1.0 200', 'HTTP/1.1 200');
		if(!in_array(substr($headers, 0, 12), $aValidHeaders)) throw new Exception('Invalid headers.');

		// do some validation
		$responseXML = @simplexml_load_string($body);
		if($responseXML === false) throw new Exception('Invalid body.');

		if(isset($responseXML->fault))
		{
			$code = (isset($responseXML->fault->value->struct->member[0]->value->int)) ? (int) $responseXML->fault->value->struct->member[0]->value->int : 'unknown';
			$message = (isset($responseXML->fault->value->struct->member[1]->value->string)) ? (string) $responseXML->fault->value->struct->member[1]->value->string : 'unknown';

			// handle errors
			switch ($code)
			{
				// code 1000 (Parse error or internal problem)
				case 1000:
					throw new Exception('[error '.$code .'] '. $message, $code);

				// code 1100 (Serverlist outdated)
				case 1100:
					throw new Exception('[error '.$code .'] '. $message, $code);

				// code 1200 (Server too busy)
				case 1200:
					if(self::$serverList === null) self::getServerList();

					// do call again
					self::doCall($method, $parameters, self::$serverList[$counter], $counter++);
				break;

				default:
					throw new Exception('[error '.$code .'] '. $message, $code);
			}
		}

		// return
		return $responseXML;
	}


	/**
	 * Get a CAPTCHA-mp3 generated by Mollom
	 *
	 * If your already called getAudioCaptcha make sure you provide at least the $sessionId. It will be used
	 * to identify visitors that are trying to break a CAPTCHA with brute force.
	 *
	 * REMARK: the Mollom-sessionid is NOT related to HTTP-session, so don't send 'session_id'.
	 *
	 * The function will return an array with 3 elements:
	 * - session_id		the session_id from Mollom
	 * - url			the url to the mp3-file
	 * - html			html that can be used on your website to display the CAPTCHA
	 *
	 * @return	array
	 * @param	string[optional] $sessionId
	 */
	public static function getAudioCaptcha($sessionId = null)
	{
		// init vars
		$aReturn = array();
		$parameters = array();

		// set autor ip
		$authorIp = (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != '') ? $_SERVER['REMOTE_ADDR'] : null;
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '') $authorIp = array_pop(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

		// set parameters
		if($sessionId != null) $parameters['session_id'] = (string) $sessionId;
		if($authorIp != null) $parameters['author_ip'] = (string) $authorIp;

		// do the call
		$responseString = self::doCall('getAudioCaptcha', $parameters);

		// validate
		if(!isset($responseString->params->param->value->struct->member)) throw new Exception('Invalid response in getAudioCaptcha.');

		// loop elements
		foreach ($responseString->params->param->value->struct->member as $part) $aReturn[(string) $part->name] = (string) $part->value->string;

		// add image html
		$aReturn['html'] = '<object type="audio/mpeg" data="'. $aReturn['url'] .'" width="50" height="16">'."\n"
								."\t".'<param name="autoplay" value="false" />'."\n"
								."\t".'<param name="controller" value="true" />'."\n"
							.'</object>';

		// return
		return $aReturn;
	}


	/**
	 * Get a CAPTCHA-image generated by Mollom
	 *
	 * If your already called getImageCaptcha make sure you provide at least the $sessionId. It will be used
	 * to identify visitors that are trying to break a CAPTCHA with brute force.
	 *
	 * REMARK: the Mollom-sessionid is NOT related to HTTP-session, so don't send 'session_id'.
	 *
	 * The function will return an array with 3 elements:
	 * - session_id		the session_id from Mollom
	 * - url			the url to the image
	 * - html			html that can be used on your website to display the CAPTCHA
	 *
	 * @return	array
	 * @param	string[optional] $sessionId
	 */
	public static function getImageCaptcha($sessionId = null)
	{
		// init vars
		$aReturn = array();
		$parameters = array();

		// set autor ip
		$authorIp = (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != '') ? $_SERVER['REMOTE_ADDR'] : null;
		if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '') $authorIp = array_pop(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));

		// set parameters
		if($sessionId !== null) $parameters['session_id'] = (string) $sessionId;
		if($authorIp !== null) $parameters['author_ip'] = (string) $authorIp;

		// do the call
		$responseString = self::doCall('getImageCaptcha', $parameters);

		// validate
		if(!isset($responseString->params->param->value->struct->member)) throw new Exception('Invalid response in getImageCaptcha.');

		// loop elements
		foreach ($responseString->params->param->value->struct->member as $part) $aReturn[(string) $part->name] = (string) $part->value->string;

		// add image html
		$aReturn['html'] = '<img src="'. $aReturn['url'] .'" alt="Mollom CAPTCHA" />';

		// return
		return $aReturn;
	}


	/**
	 * Obtains a list of valid servers
	 *
	 * @return	array
	 */
	public static function getServerList($counter = 0)
	{
		// do the call
		$responseString = self::doCall('getServerList', array(), self::$serverHost, $counter);

		// validate
		if(!isset($responseString->params->param->value->array->data->value)) throw new Exception('Invalid response in getServerList.');

		// loop servers
		foreach ($responseString->params->param->value->array->data->value as $server) self::$serverList[] = (string) $server->string;

		// return
		return self::$serverList;
	}


	/**
	 * Retrieve statistics from Mollom
	 *
	 * Allowed types are listed below:
	 * - total_days				Number of days Mollom has been used
	 * - total_accepted			Total of blocked spam
	 * - total_rejected			Total accepted posts (not working?)
	 * - yesterday_accepted		Amount of spam blocked yesterday
	 * - yesterday_rejected		Number of posts accepted yesterday (not working?)
	 * - today_accepted			Amount of spam blocked today
	 * - today_rejected			Number of posts accepted today (not working?)
	 *
	 * @return	int
	 * @param	string $type
	 */
	public static function getStatistics($type)
	{
		// possible types
		$aPossibleTypes = array('total_days', 'total_accepted', 'total_rejected', 'yesterday_accepted', 'yesterday_rejected', 'today_accepted', 'today_rejected');

		// redefine
		$type = (string) $type;

		// validate
		if(!in_array($type, $aPossibleTypes)) throw new Exception('Invalid type. Only '. implode(', ', $aPossibleTypes) .' are possible types.');

		// do the call
		$responseString = self::doCall('getStatistics', array('type' => $type));

		// validate
		if(!isset($responseString->params->param->value->int)) throw new Exception('Invalid response in getStatistics.');

		// return
		return (int) $responseString->params->param->value->int;
	}


	/**
	 * Send feedback to Mollom.
	 *
	 * With this method you can help train Mollom. Implement this method if possible. The more feedback is provided the more accurate
	 * Mollom will be.
	 *
	 * Allowed feedback-strings are listed below:
	 * - spam			Spam or unsolicited advertising
	 * - profanity		Obscene, violent or profane content
	 * - low-quality	Low-quality content or writing
	 * - unwanted		Unwanted, taunting or off-topic content
	 *
	 * @return	bool
	 * @param	string $sessionId
	 * @param	string $feedback
	 */
	public static function sendFeedback($sessionId, $feedback)
	{
		// possible feedback
		$aPossibleFeedback = array('spam', 'profanity', 'low-quality', 'unwanted');

		// redefine
		$sessionId = (string) $sessionId;
		$feedback = (string) $feedback;

		// validate
		if(!in_array($feedback, $aPossibleFeedback)) throw new Exception('Invalid feedback. Only '. implode(', ', $aPossibleFeedback) .' are possible feedback-strings.');

		// build parameters
		$parameters['session_id'] = $sessionId;
		$parameters['feedback'] = $feedback;

		// do the call
		$responseString = self::doCall('sendFeedback', $parameters);

		// validate
		if(!isset($responseString->params->param->value->boolean)) throw new Exception('Invalid response in sendFeedback.');

		// return
		if((string) $responseString->params->param->value->boolean == 1) return true;

		// fallback
		return false;
	}


	/**
	 * Set the private key
	 *
	 * @return	void
	 * @param	string $key
	 */
	public static function setPrivateKey($key)
	{
		self::$privateKey = (string) $key;
	}


	/**
	 * Set the public key
	 *
	 * @return	void
	 * @param	string $key
	 */
	public static function setPublicKey($key)
	{
		self::$publicKey = (string) $key;
	}


	/**
	 * Set the server list
	 *
	 * @return	void
	 * @param	array $servers
	 */
	public static function setServerList($servers)
	{
		// redefine
		$server = (array) $servers;

		// loop servers
		foreach ($servers as $server) self::$serverList[] = $server;
	}


	/**
	 * Set timeout
	 *
	 * @return	void
	 * @param	int $timeout
	 */
	public static function setTimeOut($timeout)
	{
		// redefine
		$timeout = (int) $timeout;

		// validate
		if($timeout == 0) throw new Exception('Invalid timeout. Timeout shouldn\'t be 0.');

		// set property
		self::$timeout = $timeout;
	}


	/**
	 * Set the user agent
	 *
	 * @return	void
	 * @param	string $newUserAgent
	 */
	public static function setUserAgent($newUserAgent)
	{
		self::$userAgent = (string) $newUserAgent;
	}


	/**
	 * Verifies your key
	 *
	 * Returns information about the status of your key. Mollom will respond with a boolean value (true/false).
	 * False means that your keys is disabled or doesn't exists. True means the key is enabled and working properly.
	 *
	 * @return	bool
	 */
	public static function verifyKey()
	{
		// do the call
		$responseString = self::doCall('verifyKey');

		// validate
		if(!isset($responseString->params->param->value->boolean)) throw new Exception('Invalid response in verifyKey.');

		// return
		if((string) $responseString->params->param->value->boolean == '1') return true;

		// fallback
		return false;
	}

}

?>
