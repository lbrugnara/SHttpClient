<?php
/**
 *	SHttpClient - Simple HTTP Client
 *	Copyright (C) 2014  Leonardo Brugnara
 *	
 *	This program is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation; either version 2 of the License, or
 *	
 *	This program is distributed in the hope that it will be useful,
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *	
 *	You should have received a copy of the GNU General Public License along
 *	with this program; if not, write to the Free Software Foundation, Inc.,
 *	51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

class SHttpClient {
	const OUTPUT_BUFFER = 8192;
	private static $verbs = array("GET", "POST", "PUT", "DELETE", "HEAD");

	private $http;
	private $body;
	private $headers;
	private $verbose;
	private $stderr;

	public function __construct(){
		$this->headers = array();
	}

	public function open($url){
		if( $this->http != null )
			throw new \Exception("Already connected to " . curl_getinfo($this->http, CURLINFO_EFFECTIVE_URL));
		$this->http = curl_init($url);
		$this->basicConfig();
	}

	/**
	 *
	 *	@param $cafile String File path or URL
	 *
	 */
	public function openSSL($url, $cafile = null, $verify_peer = true){
		if( $this->http != null )
			throw new \Exception("Already connected to " . curl_getinfo($this->http, CURLINFO_EFFECTIVE_URL));
		$this->http = curl_init($url);
		
		if( !empty($cafile) ){
			$certfile = null;
			if( file_exists($cafile) ){
				$certfile = $cafile;
			}else {
				$tmpdir = sys_get_temp_dir();
				$certfile = $tmpdir . "/cert-" . uniqid() . ".pem";
				@$content = file_get_contents($cafile);
				if( $content === false )
					throw new Exception("File/URL not found \$certfile: {$cafile}");
				file_put_contents($certfile, $content);
			}
			curl_setopt($this->http, CURLOPT_CAINFO, $certfile);
		}
		$this->basicConfig();
		curl_setopt($this->http, CURLOPT_CERTINFO, true);
		curl_setopt($this->http, CURLOPT_SSL_VERIFYPEER, $verify_peer);
		curl_setopt($this->http, CURLOPT_SSL_VERIFYHOST, 2);
	}

	protected function basicConfig(){
		curl_setopt($this->http, CURLOPT_HEADER, false);
		curl_setopt($this->http, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->http, CURLOPT_VERBOSE, false);
		curl_setopt($this->http, CURLOPT_STDERR, $this->stderr = fopen('php://temp', 'rw'));
	}

	public function close(){
		if( $this->http != null ){
			curl_close($this->http);
			$this->http = null;
		}
		$this->headers = array();
	}

	public function setVerbose($v){
		if( $this->http == null )
			throw new \Exception("Connection not established.");
		$this->verbose = $v;
		curl_setopt($this->http, CURLOPT_HEADER, $v);
		curl_setopt($this->http, CURLOPT_VERBOSE, $v);
	}

	public function setHeaders($headers){
		$this->headers = array_merge($this->headers, $headers);
	}

	public function setHeader($name, $value){
		$this->headers[$name] = $value;
	}

	protected function doRequest($verb, $body){
		if( !in_array($verb, SHttpClient::$verbs) )
			throw new \Exception("Invalid HTTP Verb $verb");

		curl_setopt($this->http, CURLOPT_CUSTOMREQUEST, $verb);
		if( !empty($this->headers) ){
			$headers = array_map(
				function($k, $v){
					return "$k: $v";
				},
				array_keys($this->headers), array_values($this->headers)
			);
			curl_setopt($this->http, CURLOPT_HTTPHEADER,  $headers);
		}

		if( $this->verbose ){
			echo "<pre><h2>Request headers:</h2>"; print_r($this->headers);echo "</pre>";
		}

		if( !empty($body) ){
			if( $verb == "POST" )
				curl_setopt($this->http, CURLOPT_POST, true);

			curl_setopt($this->http, CURLOPT_POSTFIELDS, $body);
			if( $this->verbose ){
				echo "<pre><h2>Request body:</h2>"; print_r($body);echo "</pre>";
			}
		}
		$resp = curl_exec($this->http);
		$error = curl_error($this->http);
		if( !empty($error) ){
			$errno = curl_errno($this->http);
			rewind($this->stderr);
			echo "<pre>";
			print_r(fread($this->stderr, self::OUTPUT_BUFFER));
			echo "</pre>";
			var_dump(curl_getinfo($this->http));
			$this->close();
			throw new Exception("{$errno}: {$error}");
			die();
		}

		if( $this->verbose ){			
			rewind($this->stderr);
			echo "<pre><h2>stderr:</h2>";
			print_r(fread($this->stderr, self::OUTPUT_BUFFER));
			echo "</pre>";
			echo "<pre><h2>cURL info:</h2>";
			print_r(curl_getinfo($this->http));
			echo "</pre>";
			echo "<pre><h2>Response:</h2>"; var_dump($resp);echo "</pre>";
			echo "</pre>";
		}
		$this->close();
		return $resp;
	}

	public function GET(){
		return $this->doRequest("GET", null);
	}

	public function POST($body){
		if( $body instanceof MultipartMessage ){
			$message = $body;
			$body = $message->getBody();
			$this->setHeaders($message->getHeaders());
		}else if( is_array($body) ){
			$body = http_build_query($body);
		}
		return $this->doRequest("POST", $body);
	}

	public function PUT($body){
		if( $body instanceof MultipartMessage ){
			$message = $body;
			$body = $message->getBody();
			$this->setHeaders($message->getHeaders());
		}else if( is_array($body) ){
			$body = http_build_query($body);
		}
		return $this->doRequest("PUT", $body);
	}

	public function DELETE(){
		return $this->doRequest("DELETE", null);
	}

	public function HEAD(){
		return $this->doRequest("HEAD", null);
	}
}

class MultipartMessage {
	const CRLF = "\r\n";
	private $parts;
	private $boundary;

	public function __construct(){
		$this->parts = array();
		$this->boundary = "xxx" . time() . "xxx";
	}
	public function addPart(MultipartPart $part){
		$this->parts[] = $part;
	}

	public function getBoundary(){
		return $this->boundary;
	}

	public function getLength(){
		return strlen($this->__toString());
	}

	public function getHeaders(){
		return array(
			"Content-Type" => "multipart/form-data; boundary=" . $this->getBoundary(),
			"Content-Length" => $this->getLength()
		);
	}

	public function getBody(){
		return $this->__toString();
	}

	public function __toString(){
		if( empty($this->parts) )
			return;
		$body = "";

		foreach ($this->parts as $part) {
			$body .= "--{$this->boundary}" . MultipartMessage::CRLF;
			$body .= $part->getContent();
		}
		$body .= MultipartMessage::CRLF;
		$body .= "--{$this->boundary}". MultipartMessage::CRLF . MultipartMessage::CRLF;
		return $body;
	}
}

abstract class MultipartPart {
	protected $raw;
	abstract function getContent();
}

class MultipartField extends MultipartPart {	
	public function __construct($name, $value){
        $this->raw .= "Content-Disposition: form-data; name=\"{$name}\"" . MultipartMessage::CRLF . MultipartMessage::CRLF;
        $this->raw .= "{$value}" . MultipartMessage::CRLF;
	}

	public function getContent(){
		return $this->raw;
	}
}

class MultipartFile extends MultipartPart {	
	public function __construct($name, $file){
		$ct = mime_content_type($file);
        $this->raw .= "Content-Disposition: form-data; name=\"{$name}\"; filename=\"". basename($file) ."\"". MultipartMessage::CRLF;
        $this->raw .= "Content-Type: $ct" . MultipartMessage::CRLF;
        $this->raw .= "Content-Transfer-Encoding: binary" . MultipartMessage::CRLF . MultipartMessage::CRLF;
        $this->raw .= file_get_contents($file) . MultipartMessage::CRLF;
	}

	public function getContent(){
		return $this->raw;
	}
}

?>