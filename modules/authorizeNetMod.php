<?php
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

function authorizeNetModRoutes()
{
    $authorizeNetModRoutes = array(
        "charge-credit-card" => array(
            "callback" => "chargeCustomerProfile",
            "files" => array("AuthorizeNetConnection.php","Charge.php")
        ),
        "get-customer-payment-profile" => array(
            "callback" => "getCustomerPaymentProfile",
            "content-type" => "json", 
            "files" => array("AuthorizeNetConnection.php","CustomerPaymentProfile.php")
        ),
        "charge-card" => array(
            "callback" => "chargeCard",
            "files" => array("AuthorizeNetConnection.php","NewCard.php","Charge.php"),
            "content-type" => "json",
            "method" => "post"
        ),

        );
    return $authorizeNetModRoutes;
}
function getCustomerPaymentProfile($contactId)//$postBody
{
    $profileId = getCustomerProfileIdFromSalesforce($contactId)->profileId;
    return getPaymentProfile($profileId);
}
//Should I be charging the credit card with the "charge credit card" or with the charge
//payment profile?  I think it is the latter.
function chargeCard($postBody)
{
    //Parse the json
    $json = json_decode($postBody);
    $contactId = $json->contactId;
    $pricebookEntryId = $json->pricebookEntryId;
    $amount = $json->amount;
    $customerPaymentProfileId = $json->customerPaymentProfileId;
    $customerProfileId = getCustomerProfileIdFromSalesforce($contactId)->profileId;



    //set up the response body
    $responseBody = new stdClass();
    $responseBody->amount = null;
    $responseBody->chargeStatusResponseCode = null;
    $responseBody->orderNumber = null;

    try
    {
        $chargeType = isNewPaymentProfile($customerPaymentProfileId);
        if($chargeType == true)
        {
            $response = addNewPaymentProfile($json);
            if(!empty($response->error))
            {
                throw new Exception($response->error);
            }
             $customerPaymentProfileId = $response->paymentProfileId;
        }
        if(empty($response->error))
        {
                     
            $response = chargeCustomerProfile($customerProfileId, $customerPaymentProfileId, $amount);
            $responseBody->chargeStatusResponseCode = $response->chargeStatusResponseCode;
            $responseBody->amount = $amount;

            if(!empty($response->error))
            {
                throw new Exception($response->error);
            }
            //Do I need an else statement here?
            
            $response = generateOrder($contactId, $pricebookEntryId );
            if(!empty($response->error))
            {
                throw new Exception($response->error);
            }
            else
            {
                $responseBody->orderNumber = $response->orderNumber;
            }
        }
    }
    catch(Exception $e)
    {
        $responseBody->error = $e->getMessage();
    }
    return json_encode($responseBody);
}
function isNewPaymentProfile($customerPaymentProfileId)
{
    if($customerPaymentProfileId == "")
    {
        return true;
    }
    else
    return false;
}

function getPaymentProfile($profileId)
{
    try
    {
        $customerPaymentProfile = new CustomerPaymentProfile($profileId);
        $response = $customerPaymentProfile->getPaymentProfile();
        $profile = $response->getProfile();
    
        $customerProfile = array(
            'customerProfileId' => $profile->getCustomerProfileId(),
            'merchantCustomerId' => $profile->getMerchantCustomerId(),
            'description' => $profile->getDescription(),
            'email' => $profile->getEmail()
        );
    
        $paymentProfiles = $profile->getPaymentProfiles();
        $jsonPaymentProfiles = array();
    
        for($i =0; $i<count($paymentProfiles); $i++){
            $CustomerPaymentProfileMaskedType = $paymentProfiles[$i];
            
            $PaymentMaskedType = $CustomerPaymentProfileMaskedType->getPayment();
            $CreditCard = $PaymentMaskedType->getCreditCard();
    
            $paymentProfile = array(
                'defaultPaymentProfile' => $CustomerPaymentProfileMaskedType->getDefaultPaymentProfile(),
                'customerPaymentProfileId' => $CustomerPaymentProfileMaskedType->getCustomerPaymentProfileId(),
                'customerProfileId' => $CustomerPaymentProfileMaskedType->getCustomerProfileId(),
                'cardType' => $CreditCard->getCardType(),
                'cardNumber' => $CreditCard->getCardNumber(),
                'expirationDate' => $CreditCard->getExpirationDate(),
            );
            $jsonPaymentProfiles[] = $paymentProfile;
        }
        // return json_encode($jsonPaymentProfiles);
        return json_encode(array('customerProfile' => $customerProfile, 'paymentProfiles' => $jsonPaymentProfiles));
    }
    catch(Exception $e)
    {
        $error = new stdclass();
        $error->error = $e->getMessage();
        return json_encode($error);
    }

}
//Add a new payment profile to a customers profile.
function addNewPaymentProfile($json)
{
    //Set up the response object
    $responseBody = new stdClass();
    $responseBody->paymentProfileId = null;
    $responseBody->duplicate = false;
    try
    {
        $existingCustomerProfileId = getCustomerProfileIdFromSalesforce($json->contactId)->profileId;
        $cardNumber = $json->cardNumber;
        $expirationDate = $json->expirationDate;
        $cardCode = $json->cardCode;
        $firstName = $json->firstName;
        $lastName = $json->lastName;
        $address = $json->address;
        $city = $json->city;
        $state = $json->state;
        $zip = $json->zip;
        $customerType = "individual";
        $validationMode = "liveMode";
    
        $newCard = new NewCard();
        $newCard->setCreditCard($cardNumber, $expirationDate,$cardCode);
        $newCard->setBillingAddress($firstName, $lastName, $address, $city, $state, $zip, $validationMode);
        $response = $newCard->addCard($existingCustomerProfileId, $customerType, $validationMode);
        
        // Set the transaction's refId
        $refId = 'ref' . time();
    
        if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") ) 
        {
            $responseBody->paymentProfileId = $response->getCustomerPaymentProfileId();
            $responseBody->status = $response->getMessages();
        }
        if($response->getMessages()->getMessage()[0]->getCode() == "E00039")
        {
            $responseBody->paymentProfileId = $response->getCustomerPaymentProfileId();
            $responseBody->status = $response->getMessages();
            $responseBody->duplicate = true;
        }
         else 
        {
            $errorMessages = $response->getMessages()->getMessage();
            throw new Exception($errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
        }
    }
    catch(Exception $e)
    {
        $responseBody->error = "ERROR ADD A NEW PAYMENT PRFILE". $e->getMessage();
    }
    return $responseBody;
}
function chargeCustomerProfile($customerProfileId, $customerPaymentProfileId, $amount)
{
    $responseBody = new stdClass();
    $responseBody->chargeStatusResponseCode = null;

    try
    {
        //Create a new charge
        $request = new Charge();
        $request->amount($amount);

        $response = $request->payWithProfile($customerProfileId, $customerPaymentProfileId, $amount); 

        if ($response != null) 
        {
            $tresponse = $response->getTransactionResponse();
            $responseBody->chargeStatusResponseCode = $tresponse->getResponseCode();

            if (($tresponse != null) && ($tresponse->getResponseCode()=="1"))
            {
                $responseBody->authorizationCode = $tresponse->getAuthCode();
            }
            else
            {
                $responseBody->error = "Charge Credit Card ERROR :  Invalid response ". $tresponse->getResponseCode();
            }
        }  
        else
        {
            $responseBody->error = "Charge Credit Card Null response returned";
        }
    }
    catch(Exception $e)
    {
        $responseBody->error = $e->getMessage();
    }
    return $responseBody;
}
?>