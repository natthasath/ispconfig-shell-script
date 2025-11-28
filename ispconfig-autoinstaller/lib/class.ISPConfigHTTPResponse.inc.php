<?php

/**
 * HTTP methods
 *
 * @author croydon
 */
class ISPConfigHTTPResponse {
	private $headers = null;
	private $body = null;
	private $json_body = null;
	private $http_code = false;
	
	/**
	 * @param int $http_code
	 * @param array $headers
	 * @param string $body
	 */
	public function __construct($http_code, $headers, $body) {
		$this->headers = $headers;
		$this->body = $body;
		$this->http_code = $http_code;
		
		$content_type = $this->getHeader('Content-Type');
		if(!$content_type) {
			$content_type = '';
		}
		if(strpos($content_type, ';') !== false) {
			list($content_type, ) = explode(';', $content_type);
		}
		$content_type = strtolower($content_type);
		if(preg_match('/^application\/(?:x-)?json/', $content_type)) {
			$this->json_body = json_decode($body, true);
			if(!$this->json_body) {
				$this->json_body = null;
			}
		}
	}
	
	public function __toString() {
		return serialize($this);
	}
	
	/**
	 * @return int
	 */
	public function getHTTPCode() {
		return $this->http_code;
	}
	
	/**
	 * @return array
	 */
	public function getHeaders() {
		return $this->headers;
	}
	
	/**
	 * @param string $name
	 * @return string
	 */
	public function getHeader($name) {
		$name = strtolower($name);
		
		if(isset($this->headers[$name])) {
			return $this->headers[$name];
		} else {
			return null;
		}
	}
	
	/**
	 * @return string
	 */
	public function getResponse() {
		return $this->body;
	}
	
	/**
	 * @return array
	 */
	public function getJSON() {
		return $this->json_body;
	}
}
