<?php
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
    class AuthorizeNetConnection
    {
        protected $merchantAuthentication;
        protected $refId;
        public function __construct()
        {
            $this->merchantAuthentication = new AnetAPI\MerchantAuthenticationType();   
            $this->merchantAuthentication->setName(MERCHANT_LOGIN_ID);   
            $this->merchantAuthentication->setTransactionKey(MERCHANT_TRANSACTION_KEY);   
            $this->refId = 'ref' . time();
        }
    }
?>