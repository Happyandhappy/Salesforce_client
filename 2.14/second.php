<?php
    require_once ('../soapclient/SforceEnterpriseClient.php');
    require_once('../soapclient/SforceHeaderOptions.php');

    $mySforceConnection = new SforceEnterpriseClient();
	$mySforceConnection->createConnection("../soapclient/enterprise.wsdl.xml");
	// $mySforceConnection->setEndpoint('https://login.salesforce.com/services/Soap/c/42.0');
    
    // define("USERNAME", "g.sandoval@chasedatacorp.com");       
    // define("PASSWORD", "1Nt3gr4t10n!_!");
    // define("SECURITY_TOKEN", "j4RUbBkGl4PosPQOUkqIxwgz0");

    define("USERNAME", "honestdev@mail.com");       
    define("PASSWORD", "ahgifrhehdejd1!");
    define("SECURITY_TOKEN", "CL1Smtpnzcni7UWGI8J6SOhzx");


    try {
    	$mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);

        echo "Sucessfully loggedin<br>";
        $leadId = "00Q1o00000Ok3AP";

        // Define constants for the web service. We'll use these later
        $parsedURL = parse_url($mySforceConnection->getLocation());
        define ("_SFDC_SERVER_", substr($parsedURL['host'],0,strpos($parsedURL['host'], '.')));
        define ("_WS_NAME_", 'Service');
        define ("_WS_WSDL_", _WS_NAME_ . '.wsdl.xml');
        define ("_WS_ENDPOINT_", 'https://' . _SFDC_SERVER_ . '.salesforce.com/services/wsdl/class/' . _WS_NAME_);
        define ("_WS_NAMESPACE_", 'http://soap.sforce.com/schemas/class/' . _WS_NAME_);



        // SOAP Client for Web Service
        $client = new SoapClient("soapclient/" ._WS_NAME_ . '.wsdl.xml');
        $sforce_header = new SoapHeader(_WS_NAMESPACE_, "SessionHeader", array("sessionId" => $mySforceConnection->getSessionId()));
        $client->__setSoapHeaders(array($sforce_header));
        
        $wrkArray = array(
                        'contactLastName'=> "Saghir",
                        'Id'=> "0011U00000Csy5PQAR"
                    );

        $response = $client->makeContact($wrkArray);
        // Output results to browser
        echo "<p><pre>" . print_r($response, true) . "</pre></p>";
    }
    catch (Exception $e) {
        echo $mySforceConnection->getLastRequest();
        echo $e->faultstring;
    }    
?>
