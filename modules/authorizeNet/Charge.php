<?php 
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
class Charge extends AuthorizeNetConnection
{
    //Any Transaction
    private $transactionRequestType;
    private $request;
    private $controller;
    private $refI;
    //Credit Card Only
    private $creditCard;
    private $payment;
    //Payment Profile Only
    private $profileToCharge;
    private $paymentProfile;

    public function __construct()
    {
        parent::__construct();
        //Needed for any charge transaction
        $this->transactionRequestType = new AnetAPI\TransactionRequestType();
        $this->transactionRequestType->setTransactionType( "authCaptureTransaction");
        $this->request = new AnetAPI\CreateTransactionRequest();
        $this->request->setMerchantAuthentication($this->merchantAuthentication);
        $this->request->setRefId("");// $this->refId);
        $this->request->setTransactionRequest($this->transactionRequestType);
        $this->controller = new AnetController\CreateTransactionController($this->request);

        //Needed to Charge a Credit Card
        $this->creditCard = new AnetAPI\CreditCardType();
        $this->payment = new AnetAPI\PaymentType();

        //Needed to Charge a Payment Profile
        $this->profileToCharge = new AnetAPI\CustomerProfilePaymentType();
        $this->paymentProfile = new AnetAPI\PaymentProfileType();

    }
    public function amount($amount)
    {
        $this->transactionRequestType->setAmount($amount);
    }
    public function payWith($cardNumber, $exDate, $cvv)
    {
        // Create the payment data for a credit card
        $this->creditCard->setCardNumber($cardNumber);  
        $this->creditCard->setExpirationDate($exDate);
        $this->creditCard->setCardCode($cvv);

        $this->payment->setCreditCard($this->creditCard);
        $this->transactionRequestType->setPayment($this->payment);
        $this->response = $this->controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);  
        return $this->response;
    }
    public function payWithProfile($profileid, $paymentprofileid, $amount)
    {
        $this->profileToCharge->setCustomerProfileId($profileid);
        $this->paymentProfile->setPaymentProfileId($paymentprofileid);
        $this->profileToCharge->setPaymentProfile($this->paymentProfile);

        $this->transactionRequestType->setAmount($amount);
        $this->transactionRequestType->setProfile($this->profileToCharge);

        $this->response = $this->controller->executeWithApiResponse( \net\authorize\api\constants\ANetEnvironment::SANDBOX);
        return $this->response;
    }
}
?>