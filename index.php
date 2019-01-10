<?php 
    require_once ('soapclient/SforcePartnerClient.php');    
        
    if(isset($_GET['PrimaryPhone'])){$PrimaryPhone = $_GET['PrimaryPhone'];}
    if(isset($_GET['Disposition'])){$Disposition = $_GET['Disposition'];}
    if(isset($_GET['Notes'])){$Notes = $_GET['Notes'];}
    if(isset($_GET['Call_Recording'])){$Call_Recording = $_GET['Call_Recording'];}
    if(isset($_GET['CallDurationInSeconds'])){$CallDurationInSeconds = $_GET['CallDurationInSeconds'];}
    if(isset($_GET['WhoId'])){$WhoId = $_GET['WhoId'];}
    if(isset($_GET['FirstName'])){$FirstName = $_GET['FirstName'];}
    if(isset($_GET['LastName'])){$LastName = $_GET['LastName'];}
    if(isset($_GET['OwnerId'])){$OwnerId = $_GET['OwnerId'];}
    if(isset($_GET['Status'])){$Status = $_GET['Status'];}

    try {
    
        define("USERNAME", "");       
        define("PASSWORD", "");
        define("SECURITY_TOKEN", "");
              
    
       require_once ('soapclient/SforceEnterpriseClient.php');
    
       $mySforceConnection = new SforceEnterpriseClient();
       $mySforceConnection->createConnection("soapclient/enterprise.wsdl.xml");
       // this sets the endpoint for a sandbox login:
       $mySforceConnection->setEndpoint('https://test.salesforce.com/services/Soap/c/32.0');
       $mySforceConnection->login(USERNAME, PASSWORD.SECURITY_TOKEN);
    
       // print_r($mylogin);
       
        if (isset($FirstName) && isset($LastName) && isset($PrimaryPhone) && isset($Disposition) && isset($Notes) && isset($Call_Recording) && isset($WhoId) && isset($OwnerId) && isset($CallDurationInSeconds) && isset($Status)){
            $Description = $FirstName . " " . $LastName . " \r\nPrimaryPhone:" . $PrimaryPhone . "\r\nDisposition:" . $Disposition . "\r\nNotes:" . $Notes . "\r\nCall Recording:" . $Call_Recording;
            $sObject = array(
                "Description" => $Description,
                "CallDurationInSeconds" => $CallDurationInSeconds,
                "WhoId" => $WhoId,
                "OwnerId" => $OwnerId,
                "Status" => $Status,
                "Type" => "Call",
                "Subject" => "Call",
                "Priority" => "Normal",
                "ActivityDate" => date('Y-m-d hh:mm:ss')
            );                        
            echo "**** Creating the following:\r\n";
            $createResponse = $mySforceConnection->create(array($sObject), 'Task');
        
            print_r($createResponse);
        }else{
            echo "Invalid parameters in Url!";
        }
    }catch (Exception $e) {
        echo $mySforceConnection->getLastRequest();
        echo $e->faultstring;
    }