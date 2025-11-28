<?php

/**
 * HTTP methods
 *
 * @author croydon
 */
class ISPConfigHTTP {
	private static $options = array(
		'follow_redirects' => false,
		'user_agent' => 'ISPConfig (ISPConfigHTTP/1.0)',
		'force_ipv4' => false,
		'use_tls_1_2' => true,
		'legacy_http' => false,
		'store_in_file' => false
	);
	
	private static $_redirects_left = 0;
	
	/**
	 * @param string $url
	 * @param string $method GET|POST|PUT|DELETE
	 * @param array $add_headers
	 * @param mixed $send_data
	 * @return boolean|ISPConfigHTTPResponse
	 */
	private static function _read($url, $method = 'GET', $add_headers = array(), $send_data = null) {
		if(self::getOption('follow_redirects') === true) {
			self::$_redirects_left--;
		}
		
		if(!is_array($add_headers)) {
			$add_headers = array();
		}
		
		$method = strtoupper($method);
		if(in_array($method, array('GET', 'POST', 'PUT', 'DELETE'), true) === false) {
			return false;
		}
		
		$context = stream_context_create();
		
		ISPConfigLog::debug('Calling url ' . $url . ' with ' . $method . ' and send data ' . ($send_data ? json_encode($send_data) : '<none>'));
		
		if($method === 'GET' || $method === 'DELETE') {
			if(is_array($send_data) && !empty($send_data)) {
				$url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($send_data);
			}
		}
		
		$url_info = parse_url($url);
		$use_connection = (isset($url_info['host']) ? $url_info['host'] : '');
		if(self::getOption('force_ipv4') == true) {
			stream_context_set_option($context, "ssl", "peer_name", $url_info['host']);
			stream_context_set_option($context, "ssl", "allow_self_signed", true);
			stream_context_set_option($context, "ssl", "verify_peer", false);
			stream_context_set_option($context, "ssl", "verify_peer_name", false);
			stream_context_set_option($context, "ssl", "verify_depth", 0);

			$ipv4 = dns_get_record($url_info['host'], DNS_A);
			if($ipv4 && isset($ipv4[0]['ip'])) {
				$use_connection = $ipv4[0]['ip'];
				ISPConfigLog::debug('Force IPv4 ' . $url_info['host'] . ' -> ' . $use_connection);
			}
		}
		
		$transport = 'tls';
		if(self::getOption('use_tls_1_2')) {
			$transport = 'tlsv1.2';
		}

		$errno = 0;
		$errstr = '';
		if((isset($url_info['scheme']) && $url_info['scheme'] == 'https') || (isset($url_info['port']) && $url_info['port'] == 443)) {
			$port = isset($url_info['port']) ? $url_info['port'] : 443;
			$fp = stream_socket_client($transport . '://' . $use_connection . ':' . $port, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
		} else {
			$port = isset($url_info['port']) ? $url_info['port'] : 80;
			$fp = fsockopen($use_connection, $port, $errno, $errstr, 10);
		}

		if(!$fp) {
			return false;
		}
		
		$store_in = self::getOption('store_in_file');
		if($store_in) {
			$outfp = fopen($store_in, 'w');
			if(!$outfp) {
				return false;
			}
		}
		
		$user_agent = self::getOption('user_agent');

		stream_set_timeout($fp, 10);
		$header = $method . ' ' . (isset($url_info['path']) ? $url_info['path'] : '/') . (isset($url_info['query']) ? '?' . $url_info['query'] : '') . " HTTP/1." . (self::getOption('legacy_http') ? '0' : '1') . "\r\n";
		$header .= "Host: " . (isset($url_info['host']) ? $url_info['host'] : '') . "\r\n";
		$header .= "User-Agent: " . $user_agent . "\r\n";
		if(isset($url_info['user'])) {
				if(!array_key_exists('pass', $url_info)) {
						$url_info['pass'] = '';
				}
				$header .= "Authorization: basic " . base64_encode(rawurldecode($url_info['user']) . ':' . rawurldecode($url_info['pass'])) . "\r\n";
		}
		if(is_array($add_headers) && !empty($add_headers)) {
			foreach($add_headers as $key => $value) {
				if(is_array($value)) {
					for($v = 0; $v < count($value); $v++) {
						$header .= $key . ': ' . $value[$v] . "\r\n";
					}
				} else {
					$header .= $key . ': ' . $value . "\r\n";
				}
			}
		}

		$header .= "Accept: */*\r\n";
		$header .= "Connection: close\r\n";
		
		if($method === 'POST' || $method === 'PUT') {
			if(!is_array($add_headers) || !array_key_exists('content-type', array_change_key_case($add_headers, CASE_LOWER))) {
				$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			}
			$header .= "Content-Length: " . strlen($send_data) . "\r\n\r\n";
			$header .= $send_data . "\r\n";
		}
		$header .= "\r\n";

		fwrite($fp, $header);

		
		$response = '';
		$eoheader = false;
		$header = '';
		$tmpdata = '';
		$chunked = false;
		$chunklen = 0;
		$headers = array();

		while(!feof($fp)) {
			if(($header = fgets($fp, 1024)) !== false) {
				if($eoheader == true) {
					if($store_in) {
						fwrite($outfp, $header);
					} else {
						$response .= $header;
					}
					continue;
				}

				if($header == "\r\n") {
					$eoheader = true;
					continue;
				} else {
					$tmpdata .= $header;
					if(preg_match('/Transfer-Encoding:\s+chunked/i', $tmpdata)) {
						$chunked = true;
					}
				}

				$sc_pos = strpos($header, ':');
				if($sc_pos === false) {
					$headers['status'] = $header;
					$headers['http_code'] = intval(preg_replace('/^HTTP\/\d+\.\d+\s+(\d+)\s+.*$/', '$1', $header));
				} else {
					$label = substr($header, 0, $sc_pos);
					$value = substr($header, $sc_pos + 1);
					if(strtolower($label) === 'set-cookie' && isset($headers['set-cookie'])) {
						if(!is_array($headers['set-cookie'])) {
							$headers['set-cookie'] = array($headers['set-cookie']);
						}
						$headers['set-cookie'][] = trim($value);
					} else {
						$headers[strtolower($label)] = trim($value);
					}
				}
			}
		}

		if($chunked == true) {
			$lines = explode("\n", $response);
			$response = '';
			$chunklen = 0;
			foreach($lines as $line) {
				$line .= "\n";
				if($chunklen <= 0) {
					if(preg_match('/^([0-9a-f]+)\s*$/is', $line, $matches)) {
						$chunklen = hexdec($matches[1]);
					}
					continue;
				}

				if(strlen($line) > $chunklen) {
					//echo "Warnung: " . strlen($line) . " > " . $chunklen . "\n";
					$line = substr($line, 0, $chunklen);
				}
				$response .= $line;
				$chunklen -= strlen($line);
			}

			$start = strpos($response, '<?xml');
			$end = strrpos($response, '>');
			if($start !== false && $end !== false) {
				$response = substr($response, $start, $end - $start + 1);
			}
		}

		fclose($fp);
		if(!isset($headers)) {
			return false;
		}

		if(isset($headers['http_code']) && isset($headers['location']) && ($headers['http_code'] == 301 || $headers['http_code'] == 302) && self::getOption('follow_redirects') === true && self::$_redirects_left > 0) {
			if($store_in) {
				fclose($outfp);
			}
			ISPConfigLog::debug('Got code 301 ' . $url . ' -> ' . $headers['location']);
			return self::_read($headers['location'], 'GET', $add_headers);
		}
		
		if($store_in) {
			fclose($outfp);

			return new ISPConfigHTTPResponse($headers['http_code'], $headers, $store_in);
		} else {
			return new ISPConfigHTTPResponse($headers['http_code'], $headers, $response);
		}
	}
	
	/**
	 * @param string $key
	 * @param mixed $value
	 * @return boolean
	 */
	public static function setOption($key, $value) {
		if(array_key_exists($key, self::$options) === false) {
			return false;
		} else {
			self::$options[$key] = $value;
		}
	}
	
	/**
	 * @param string $key
	 * @return string|boolean
	 */
	public static function getOption($key) {
		if(array_key_exists($key, self::$options) === false) {
			return null;
		} else {
			return self::$options[$key];
		}
	}
	
	/**
	 * Get data
	 *
	 * Calls an url and returns a response object
	 *
	 * @param string  $url         the url to call
	 * @param array    $add_headers additional headers to send
	 * @param string $store_in store result in file instead of response object
	 * @return ISPConfigHTTPResponse
	 */
	public static function get($url, $add_headers = null, $store_in = null) {
		if(self::getOption('follow_redirects') === true) {
			self::$_redirects_left = 5;
		}
		
		if($store_in) {
			self::setOption('store_in_file', $store_in);
		} else {
			self::setOption('store_in_file', false);
		}
		
		return self::_read($url, 'GET', $add_headers);
	}

	/**
	 * Make a post request and get data
	 *
	 * Calls an url with a post request and returns the data - and optionally the header content
	 *
	 * @param string  $url         the url to call
	 * @param string  $data        the post data to send
	 * @param array    $add_headers additional headers to send
	 * @param string $store_in store result in file instead of response object
	 * @return ISPConfigHTTPResponse
	 */
	public static function post($url, $data, $add_headers = null, $store_in = null) {
		if(self::getOption('follow_redirects') === true) {
			self::$_redirects_left = 5;
		}
		
		if($store_in) {
			self::setOption('store_in_file', $store_in);
		} else {
			self::setOption('store_in_file', false);
		}
		
		return self::_read($url, 'POST', $add_headers, $data);
	}

	/**
	 * Make a post request and get data
	 *
	 * Calls an url with a post request and returns the data - and optionally the header content
	 *
	 * @param string  $url         the url to call
	 * @param string  $data        the post data to send
	 * @param array    $add_headers additional headers to send
	 * @param string $store_in store result in file instead of response object
	 * @return ISPConfigHTTPResponse
	 */
	public static function request($method, $url, $data = null, $add_headers = null, $store_in = null) {
		if(self::getOption('follow_redirects') === true) {
			self::$_redirects_left = 5;
		}
		
		if($store_in) {
			self::setOption('store_in_file', $store_in);
		} else {
			self::setOption('store_in_file', false);
		}
		
		return self::_read($url, $method, $add_headers, $data);
	}
}
