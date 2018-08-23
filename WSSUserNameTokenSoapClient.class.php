<?PHP
/**
 * Extendes the PHP SOAPCLIENT to incorporate the USERNAMETOKEN with PasswordDigest WS-Security standard
 * http://www.oasis-open.org/committees/download.php/16782/wss-v1.1-spec-os-UsernameTokenProfile.pdf
 *
 * Extends the PHP SOAPCLIENT to use cURL as transport to allow access through proxy servers
 *
 */

class WSSUserNameTokenSoapClient extends SoapClient{

	/* ---------------------------------------------------------------------------------------------- */
	/* Constants and Private Variables                                                                */

	//Constants for use in code.
	const WSSE_NS  = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
	const WSSE_PFX = 'wsse';
	const WSU_NS   = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
	const WSU_PFX  = 'wsu';
	const PASSWORD_TYPE = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest';

	//Private variables
	private $username;
	private $password;
	
		private $proxy;

	/* ---------------------------------------------------------------------------------------------- */
	/* Helper Functions                                                                               */

	/* Generate a GUID */
	private function guid(){
		mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
		$charid = strtoupper(md5(uniqid(rand(), true)));
		$hyphen = chr(45);// "-"
		$uuid = substr($charid, 0, 8).$hyphen
		.substr($charid, 8, 4).$hyphen
		.substr($charid,12, 4).$hyphen
		.substr($charid,16, 4).$hyphen
		.substr($charid,20,12);
		return $uuid;
	}


	private function generate_header() {

		//Get the current time
		$currentTime = time();
		//Create the ISO8601 formatted timestamp
		$timestamp=gmdate('Y-m-d\TH:i:s', $currentTime).'Z';
		//Create the expiry timestamp 5 minutes later (60*5)
		$expiretimestamp=gmdate('Y-m-d\TH:i:s', $currentTime + 300).'Z';
		//Generate the random Nonce. The use of rand() may repeat the word if the server is very loaded.
		$nonce=mt_rand();
		//Create the PasswordDigest for the usernametoken
		$passdigest=base64_encode(pack('H*',sha1(pack('H*',$nonce).pack('a*',$timestamp).pack('a*',$this->password))));

		//Build the header text
		$header='
			<wsse:Security env:mustUnderstand="1" xmlns:wsse="'.self::WSSE_NS.'" xmlns:wsu="'.self::WSU_NS.'">
				<wsu:Timestamp wsu:Id="Timestamp-'.$this->guid().'">
					<wsu:Created>'.$timestamp.'</wsu:Created>
					<wsu:Expires>'.$expiretimestamp.'</wsu:Expires>
				</wsu:Timestamp>
				<wsse:UsernameToken xmlns:wsu="'.self::WSU_NS.'">
					<wsse:Username>'.$this->username.'</wsse:Username>
					<wsse:Password Type="'.self::PASSWORD_TYPE.'">'.$passdigest.'</wsse:Password>
					<wsse:Nonce>'.base64_encode(pack('H*',$nonce)).'</wsse:Nonce>
					<wsu:Created>'.$timestamp.'</wsu:Created>
				</wsse:UsernameToken>
			</wsse:Security>
			';

		$headerSoapVar=new SoapVar($header,XSD_ANYXML); //XSD_ANYXML (or 147) is the code to add xml directly into a SoapVar. Using other codes such as SOAP_ENC, it's really difficult to set the correct namespace for the variables, so the axis server rejects the xml.
		$soapheader=new SoapHeader(self::WSSE_NS, "Security" , $headerSoapVar , true);
		return $soapheader;
	}


	
	/* Helper function
	 * used for download WSDL files locally to work around poor proxy support in soapclient
	 * Downloaded files are cached and only redownloaded when cache is stale
	 *
	 * @param string $url - Full URL to download
	 * @param string $filename - Filename to save to
	 * @param string $cachefolder - Folder to store downloads
	 * @param int $cachetime - How long the saved file is valid for in seconds
	 * @param bool $forcedownload - Force the files to be downloaded
	 * @return object
	 */
	/*
	 * 13-SEP-2013 Modifications to use CACHE folder rather than UPLOAD and change name to skillsoft
	 */
	private function downloadfile($url, $filename , $forcedownload=false, $cachefolder='skillsoft' , $cachetime=86400) {

	
		$folder=$cachefolder;
		$downloadresult = new stdClass();

		/// Create cache directory if necesary
		if (!file_exists($folder)) {
		if (!mkdir($folder)) {
			//Couldn't create temp folder
			throw new Exception('Could not create WSDL Cache Folder (skillsoft): '.$folder);
		}
		}

		$fullpath = $folder.'/'.$filename;
		

		
		//Check if we have a cached copy
		if(!file_exists($fullpath) || filemtime($fullpath) < time() - $cachetime || $forcedownload == true) {
			//No copy so download
			if (!extension_loaded('curl') or ($ch = curl_init($url)) === false) {
				//No curl so error
				throw new Exception('curl not available');
			} else {
				$fp = fopen($fullpath, 'wb');
				$ch = curl_init($url);
				//Ignore SSL errors
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
				curl_setopt($ch, CURLOPT_FILE, $fp);

				//Force SSLv3 to workaround Openssl 1.0.1 issue
				//See https://bugs.launchpad.net/ubuntu/+source/curl/+bug/595415
				//curl_setopt($ch, CURLOPT_SSLVERSION, 3);

				//Force CURL to use TLSv1 or later as SSLv3 deprecated on Skillsoft servers
				//Bug Fix - http://code.google.com/p/moodle2-skillsoft-activity/issues/detail?id=17
				curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
				
				//Setup Proxy Connection

				if (isset($this->proxy)) {
							curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

							if (empty($this->proxy->proxyport)) {
								curl_setopt($ch, CURLOPT_PROXY, $this->proxy->proxyhost);
							} else {
								curl_setopt($ch, CURLOPT_PROXY, $this->proxy->proxyhost.':'.$this->proxy->proxyport);
							}

							if (!empty($this->proxy->proxyuser) and !empty($this->proxy->proxypassword)) {
								curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy->proxyuser.':'.$this->proxy->proxypassword);
								if (defined('CURLOPT_PROXYAUTH')) {
									// any proxy authentication if PHP 5.1
									curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);
								}
							}
						}

				$data = curl_exec($ch);

				// Check if any error occured
				if(!curl_errno($ch))
				{
					$downloadresult = new stdClass(); 
					$downloadresult->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$downloadresult->filename = $filename;
					$downloadresult->filepath = $folder.'/'.$filename;
					$downloadresult->error = '';
					fclose($fp);
						
						
				} else {
					fclose($fp);
					$error    = curl_error($ch);

					$downloadresult = new stdClass(); 
					$downloadresult->status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
					$downloadresult->filename = '';
					$downloadresult->filepath = '';
					$downloadresult->error = $error;
				}
			}
		} else {
			//We do so use it
			$downloadresult = new stdClass(); 
			$downloadresult->status = '200';
			$downloadresult->filename = $filename;
			$downloadresult->filepath = $fullpath;
			$downloadresult->error = '';

		}

		return $downloadresult;
	}


	/**
	 * Loads an XML file (olsa.wsdl/*.xsd) and extracts the paths to embedded scema files
	 * and recursively downloads these and stores them. Updating the XML document with new
	 * paths.
	 *
	 * This is needed as SOAPClient needs to be able to resolve all files referenced in WSDL
	 * and when accessing internet via Proxy this is not possible.
	 *
	 * @param string $filepath - Fullpath to XML to process
	 * @param string $basepath - The basepath of the XML we are processing. This is needed when
	 *  							schema reference is relative.
	 */
	private function processExternalSchema($filepath, $basepath, $forcedownload=false) {
		//This will find any embedded schemas and download them
		libxml_use_internal_errors(true);
		$simplexml = simplexml_load_file($filepath);

		if (!$simplexml) {
			throw new Exception("Failed Loading ".$filepath);
		} else {
			$simplexml->registerXPathNamespace("xsd", "http://www.w3.org/2001/XMLSchema");

			//Select the xsd:* elements with external SCHEMA
			$linkedxsd = $simplexml->xpath('//xsd:*[@schemaLocation]');

			//Loop thru the external schema
			foreach ($linkedxsd as $xsdnode) {
				$schemaurl = $xsdnode->attributes()->schemaLocation;

				//If path is "relative" we complete it using WSDL path
				if (@parse_url($schemaurl, PHP_URL_SCHEME) == false) {
					//It is relative
					$schemafilename = $schemaurl;
					$schemaurl = $basepath.$schemaurl;
				} else {
					$schemafilename = basename($schemaurl);
				}

				//Attempt to download the External schame files
				if ($content = $this->downloadfile($schemaurl,$schemafilename, $forcedownload)) {
					//Check for HTTP 200 response
					if ($content->status != 200) {
						//We have an error so throw an exception
						throw new Exception($content->error);
					} else {
						//now we update the $xsdnode
						//Shows how to change it
						$xsdnode->attributes()->schemaLocation = $content->filename;
						$this->processExternalSchema($content->filepath, $basepath, $forcedownload);
					}
				}
			}
		}
		$simplexml->asXML($filepath);
	}


	/**
	 * Retrieve and save locally the WSDL and all referenced XSD
	 * if the XSD is not relative modify WSDL
	 *
	 * @param string $wsdl - Full URL
	 * @return string - The locally saved WSDL filepath
	 */
	private function retrieveWSDL($endpoint, $forcedownload=false) {

		$wsdl = $endpoint.'?WSDL';

		//Attempt to download the WSDL
		if ($wsdlcontent = $this->downloadfile($wsdl,'olsa.wsdl',$forcedownload)) {
			//Check for HTTP 200 response
			if ($wsdlcontent->status != 200) {
				//We have an error so throw an exception
				throw new Exception($wsdlcontent->error);
			}
			else {
				$this->processExternalSchema($wsdlcontent->filepath, $endpoint.'/../',$forcedownload);
			}
		}
		return $wsdlcontent->filepath;
	}


	/*It's necessary to call it if you want to set a different user and password*/
	public function __setUsernameToken($username,$password){
		$this->username=$username;
		$this->password=$password;
	}

	public function __setProxy($proxyhost, $proxyport, $proxyuser=null, $proxypassword=null)
	{
		$this->proxy = new stdclass();
		$this->proxy->proxyhost = $proxyhost;
		$this->proxy->proxyport = $proxyport;
		$this->proxy->proxyuser = $proxyuser;
		$this->proxy->proxypassword = $proxypassword;
	}
	
	public function __construct($endpoint,$options) {
		if (array_key_exists('FORCE_DOWNLOADED_WSDL_REFRESH', $options)) 
		{
			$wsdl = $this->retrieveWSDL($endpoint,$options['FORCE_DOWNLOADED_WSDL_REFRESH']);
		} else {
			$wsdl = $this->retrieveWSDL($endpoint);
		}

		$result = parent::__construct($wsdl, $options);
		return $result;
	}

	/*Overload the original method, to use CURL for requests as SOAPClient has limited proxy support
	 */
	public function __doRequest($request, $location, $action, $version, $one_way=0) {

		$headers = array(
						'Method: POST',
						'Connection: Keep-Alive',
						'User-Agent: PHP-SOAP-CURL',
						'Content-Type: text/xml; charset=utf-8',
						'SOAPAction: "'.$action.'"'
						);
						
		$this->__last_request_headers = $headers;
		$ch = curl_init($location);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true );
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		
		//PHP CURL has an outdated CA certs and so is failing to validate the Skillsoft Cert
		//Disabling the certificate verification
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

		curl_setopt($ch, CURLOPT_SSLVERSION, 6);

		if (isset($this->proxy)) {
			curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, false);

			if (empty($this->proxy->proxyport)) {
				curl_setopt($ch, CURLOPT_PROXY, $this->proxy->proxyhost);
			} else {
				curl_setopt($ch, CURLOPT_PROXY, $this->proxy->proxyhost.':'.$this->proxy->proxyport);
			}

			if (!empty($this->proxy->proxyuser) and !empty($this->proxy->proxypassword)) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy->proxyuser.':'.$this->proxy->proxypassword);
				if (defined('CURLOPT_PROXYAUTH')) {
					// any proxy authentication if PHP 5.1
					curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC | CURLAUTH_NTLM);
				}
			}
		}

		$response = curl_exec($ch);	

		if($errno = curl_errno($ch)) {
			$error_message = curl_strerror($errno);
			throw new Exception("cURL error ({$errno}):\n {$error_message}");
		}			
		
		
		return $response;
	}

	/*Overload the original method, to use CURL for requests as SOAPClient has limited proxy support
	 *
	 */
	public function __getLastRequestHeaders() {
		return implode("\n", $this->__last_request_headers)."\n";
	}

	/*Overload the original method, and add the WS-Security Header */
	public function __soapCall($function_name,$arguments,$options=array(),$input_headers=null,&$output_headers=array()){
		$result = parent::__soapCall($function_name,$arguments,$options,$this->generate_header());

		return $result;
	}

}
?>