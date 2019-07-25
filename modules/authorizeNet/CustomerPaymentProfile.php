<?php
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
    class CustomerPaymentProfile extends AuthorizeNetConnection
    {
        private $customerProfileId;
        private $request;
        private $controller;
        private $response;

        public function __construct($profileId)
        {
            parent::__construct();
            $this->customerProfileId = $profileId;
            $this->request = new AnetAPI\GetCustomerProfileRequest();
            $this->request->setCustomerProfileId($this->customerProfileId);
            $this->request->setMerchantAuthentication($this->merchantAuthentication);
            $this->controller = new AnetController\GetCustomerProfileController($this->request);
        }
        public function getPaymentProfile()
        {
            $this->response = $this->controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
            return $this->response;
        }
    }
?>