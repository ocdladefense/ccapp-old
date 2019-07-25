<?php
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
class NewCard extends AuthorizeNetConnection
{
    private $creditCard;
    private $paymentCreditCard;
    private $billingAddress;
    private $paymentProfile;
    private $paymentProfileRequest;
    private $controller;
    private $response;

    public function __construct()
    {
        parent::__construct();
        $this->creditCard = new AnetAPI\CreditCardType();
        $this->paymentCreditCard = new AnetAPI\PaymentType();
        $this->billingAddress = new AnetAPI\CustomerAddressType();
        $this->paymentProfile = new AnetAPI\CustomerPaymentProfileType();
        $this->paymentProfileRequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
        $this->paymentProfileRequest->setMerchantAuthentication($this->merchantAuthentication);
        $this->controller = new AnetController\CreateCustomerPaymentProfileController($this->paymentProfileRequest);
    }
    public function setCreditCard($cardNumber, $expirationDate, $cardCode)
    {
        // Set credit card information for payment profile
        $this->creditCard->setCardNumber($cardNumber);
        $this->creditCard->setExpirationDate($expirationDate);
        $this->creditCard->setCardCode($cardCode);
        $this->paymentCreditCard->setCreditCard($this->creditCard);
    }
    public function setBillingAddress($firstName, $lastName, $address, $city, $state, $zip)
    {
        // Create the Bill To info for new payment type
        $this->billingAddress->setFirstName($firstName);
        $this->billingAddress->setLastName($lastName);
        $this->billingAddress->setAddress($address);
        $this->billingAddress->setCity($city);
        $this->billingAddress->setState($state);
        $this->billingAddress->setZip($zip);
    }
    public function addCard($existingCustomerProfileId, $customerType,$validationMode)
    {
        //Add the new card
        // Create a new Customer Payment Profile object
        $this->paymentProfile->setCustomerType($customerType);
        $this->paymentProfile->setBillTo($this->billingAddress);
        $this->paymentProfile->setPayment($this->paymentCreditCard);
        $this->paymentProfile->setDefaultpaymentProfile(true);

        // Assemble the complete transaction request
        $this->paymentProfileRequest->setMerchantAuthentication($this->merchantAuthentication);

        // Add an existing profile id to the request
        $this->paymentProfileRequest->setCustomerProfileId($existingCustomerProfileId);
        $this->paymentProfileRequest->setpaymentProfile($this->paymentProfile);
        $this->paymentProfileRequest->setValidationMode($validationMode);

        // Create the controller and get the response
        $this->response = $this->controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        return $this->response;
    }

}
?>