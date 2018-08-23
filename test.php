<?php
require_once('config.php');

function SkillportSSO($username,$groupcode="skillsoft",$action="home",$assetid=null) {
	global $CFG;
	
	switch ($CFG->skillportversion) {
     case 8:
 		 return Skillport8ExtendedSSO($username,$groupcode,$action,$assetid);
         break;
     default:
         return Skillport8ExtendedSSO($username,$groupcode,$action,$assetid);
 	}
}

function getskillport8profilesoap($id, $value) {
  $txt = sprintf('<ns1:fieldValue id="%s"><ns1:value>%s</ns1:value></ns1:fieldValue>',$id,$value);
  return new SoapVar($txt,XSD_ANYXML);
}

function Skillport8ExtendedSSO($username,$groupcode="skillsoft",$action="home",$assetid=null) {
	global $CFG;
	//Include the SOAP Client code
	include('WSSUserNameTokenSoapClient.class.php');

	//Specify the WSDL using the EndPoint
	$endpoint = $CFG->endpoint;

	//Specify the SOAP Client Options
	//"FORCE_DOWNLOADED_WSDL_REFRESH" = the WSSUserNameTokenSoapClient caches a local copy of the WSDL and associated XSD files in a skillsoft folder. If true the download happens everytime
	
		$options = array(
			"trace"      => 1,
			"exceptions" => 1,
			"soap_version"   => SOAP_1_2,
			"cache_wsdl" => WSDL_CACHE_BOTH,
			"encoding"=> "UTF-8",
			"location"=> $endpoint,
			"FORCE_DOWNLOADED_WSDL_REFRESH"=>false,
			);

	 //Create a new instance of the OLSA Soap Client
	try {
		$client = new WSSUserNameTokenSoapClient($endpoint,$options);
	}
	catch (Exception $ex)
	{
		//We have an exception creating the SOAP Client
		//This could be things like networking issues, badly typed url etc
		throw $ex;
	}
	//Configure a proxy server if access needs to be via a proxy
	//$client->__setProxy("127.0.0.1","8888","martin","password");

	//Create the USERNAMETOKEN
	$client->__setUsernameToken($CFG->customerid,$CFG->sharedsecret);

	//Create the basic GetMultiActionSignOnUrlRequest
	//Format is Case Sensitive and is:
	//	parameter => value
	$GetMultiActionSignOnUrlExtendedRequest =  array(
		"customerId" => $CFG->customerid
	);

	//Add additional elements
	//The Unique Username
	//Here we are using the PHP Session Variable set by the simulated login page
	//
	$GetMultiActionSignOnUrlExtendedRequest['username'] = $username;

    //Set the SkillPort password to welcome. This only affects
    //new account creation. If a user has already been created
    //and chosen a new password in SkillPort this will not
    //overwrite the user selected password.
    $GetMultiActionSignOnUrlExtendedRequest['password'] = 'welcome';

    //We ensure the user is created/assigned to just the Skillsoft group
    //This value is the ORGCODE specified for the group in SkillPort
    //This value overrides ALL existing group membership details for the
    //specified user

    //NOTE: See exception handling below for notes on how this can be used
    //in a production environment by NOT sending this unless the user
    //is a new user. That way existing users in SkillPort with existing
    //group memberships are not affected.
    $GetMultiActionSignOnUrlExtendedRequest['groupCode'] = $groupcode;

    //The actionType defines what type of URL to generate:
    //These following actions take the user into SkillPort UI, but to
    //particular page the user is 'free' to navigate away from the page.
    // catalog = SkillPort catalogue page
    // myplan = SkillPort MyPlan page
    // home = SkillPort Home page
    // summary = Course Summary Page for selected course
    //These actions limit the user to JUST the specified assetId.
    // launch = Opens courses
	$GetMultiActionSignOnUrlExtendedRequest['actionType'] = $action;

    //assetId is needed for launch and summary actions and is the
    //coursecode on SkillPort.
    //If the user does not have access to the course an error
    //will be thrown.
	$GetMultiActionSignOnUrlExtendedRequest['assetId'] = $assetid;

	//OPTIONAL
	//This determines whether the user should be active
	//
	//$GetMultiActionSignOnUrlExtendedRequest['active'] = true;

	//OPTIONAL
	//This fields controls the user role. Valid values are:
	//	Admin
	//	Manager
	//	End-User
	//If unspecified it defaults to End-User
	//$GetMultiActionSignOnUrlExtendedRequest['authType'] = "";

	//OPTIONAL
	//This specifies the users choice of Language for the SkillPort UI
	//Valid values are:
	//en_US - US English
	//en_GB - UK English
	//fr - French
	//es - Spanish
	//it - Italian
	//de - German
	//ja - Japanese
	//pl - Polish
	//ru - Russian
	//zh - Chinese Mandarin
	//If no language is supplied, the users language is set to the language
	//configured for the SkillPort site.
	//$GetMultiActionSignOnUrlExtendedRequest['siteLanguage'] = "";

	//OPTIONAL
	//This specifies the SkillPort UserName of this users manager
	//The manager username must already exist in SkillPort
	//$GetMultiActionSignOnUrlExtendedRequest['manager'] = "";

    //OPTIONAL
    //This determines whether the user has chosen that they require
    //Section508 compliant UI and courses
	//$GetMultiActionSignOnUrlExtendedRequest['enable508'] = false;

	//OPTIONAL
	//The additional custom profile fields in Skillport 8 are defined as key/value pairs and sent as an array
	//In the request
	/*
	 *  <!--Optional:-->
         <olsa:profileFieldValues>
            <!--Zero or more repetitions:-->
            <olsa:fieldValue id="?">
               <!--Zero or more repetitions:-->
               <olsa:value>?</olsa:value>
            </olsa:fieldValue>
         </olsa:profileFieldValues>
	 */
//	$profilefields = array();
//	$profilefields[] = getskillport8profilesoap('skillportprofilefield','valuetouse');
//	$profilefields[] = getskillport8profilesoap('skillportprofilefield2','valuetouse2');
//	
//	$GetMultiActionSignOnUrlExtendedRequest['profileFieldValues'] = $profilefields;
	 
	//Call the WebService and stored result in $result
	try {
		$result=$client->__soapCall('SO_GetMultiActionSignOnUrlExtended',array('parameters'=>$GetMultiActionSignOnUrlExtendedRequest));
	}
	catch (SoapFault $fault)
	{
		//These capture exceptions from the SOAP response
		 if (!stripos($fault->getmessage(), "the security token could not be authenticated or authorized") == false)
		{
			//The OLSA Credentials specified could not be authenticated
			//Check the values in the web.config are correct for OLSA.CustomerID and OLSA.SharedSecret - these are case sensitive
			//Check the time on the machine, the SOAP message is valid for 5 minutes. This means that if the time on the calling machine
			//is to slow OR to fast then the SOAP message will be invalid.
			throw $result;
		}
		elseif (!stripos($fault->getmessage(), "the property '_pathid_' or '_orgcode_' must be specified") == false)
		{
			//Captures if the USER does not exist and we have NOT SENT the _req.groupCode value.
			//This is a good methodology when the SSO process will not be aware of all groups a
			//user belongs to. This way capturing this exception means that we only need to send
			//an orgcode when we know we have to create the user.
			//This avoids the issue of overwriting existing group membership for user already in
			//SkillPort.
			//You would capture this exception and resubmit the request now including the "default"
			//orgcode.
			throw $result;
		}
		elseif (!stripos($fault->getmessage(), "invalid new username") == false)
		{
			//The username specified is not valid
			//Supported Characters: abcdefghijklmnopqrstuvwxyz0123456789@$_.~'-
			//Cannot start with apostrophe (') or dash (-)
			//Non-breaking white spaces (space, tab, new line) are not allowed in login names
			//No double-byte characters are allowed (e.g. Japanese or Chinese characters)
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "invalid password") == false)
		{
			//The password specified is not valid
			//All single-byte characters are allowed except back slash (\)
			//Non-breaking white spaces (space, tab, new line) are not allowed
			//No double-byte characters are allowed (e.g. Japanese or Chinese characters)
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "enter a valid email address") == false)
		{
			//The email address specified is not a valid SMTP email address
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "error: org code") == false)
		{
			//The single orgcode specified in the _req.groupCode is not valid
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "user group with orgcode") == false)
		{
			//One of the multiple orgcodes specified in the _req.groupCode is not valid
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "field is too long") == false)
		{
			//One of the fields specified, see full faultstring for which, is too large
			//Generally text fields can be 255 characters in length
			throw $fault;
		}
		else
		{
			echo $fault->getmessage();
			//Any other SOAP exception not handled above
			throw $fault;
		}
    }
   
    if (isset($result->olsaURL)) {
		//We have valid OLSA URL so redirect
		//header( "Location: ".$result->olsaURL );
		 return $result->olsaURL;
    } else {
    	throw new Exception('Invalid URL from OLSA');
    }
}

	$result = SkillportSSO("olsatest", "skillsoft", "home", "");
	if (isset($result)) {
		//We have valid OLSA URL so redirect
		echo "Result: ".$result;
    } else {
    	throw new Exception('Invalid URL from OLSA');
    }

?>