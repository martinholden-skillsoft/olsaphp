# olsaphp
Example code for performing WS-Security UserNameToken authentication for OLSA Web Services

# Config
In the config.php you need to enter the OLSA endpoint url, customerid and shared secret

In PHP.INI you need to ensure the SOAP client and CURL extensions are enabled.

# SOAP Client Code Details
The [WSSUserNameTokenSoapClient.class.php](WSSUserNameTokenSoapClient.class.php) contains an implementation of a PHP SOAP client that supports WS-Security UserNameToken Password Digest.

The code also downloads and caches the WSDL and associated XSD documents locally, hardcoded currently to [skillsoft](WSSUserNameTokenSoapClient.class.php#L95) folder below current folder.

The caching code can be forced to refresh or will automatically refresh after 86,400 seconds (24 hours)

# Testing
The code has been tested on PHP 5.6.37

Run the [test.php](test.php] on the command line, it will attempt to call the SO_GetMultiActionSignOnExtended function with username [olsatest](test.php#L246) and display the returned URL to use to seamlessly log the user in.

