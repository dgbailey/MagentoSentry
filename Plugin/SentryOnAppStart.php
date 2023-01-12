<?php

namespace MyVendor\MagentoSentry\Plugin;
use Magento\Framework\AppInterface;
use Magento\Framework\App\DeploymentConfig;

class SentryOnAppStart {
    protected $sentryOptions = [];
    public function __construct(
        DeploymentConfig $deploymentConfig
    ){
       $this->deploymentConfig = $deploymentConfig;
 
       $this->marshallConfig();
    }

    public function marshallConfig(){
        $config = $this->deploymentConfig->get('sentry');
        $keys = array_keys($config);

        foreach($keys as $k ){
            $this->sentryOptions[$k] = $this->deploymentConfig->get("sentry/$k");
        }
    }
    public function aroundLaunch(AppInterface $subject, callable $proceed){
        
        
        //https://github.com/magento/magento2/blob/2.3/lib/internal/Magento/Framework/App/DeploymentConfig.php
       
        \Sentry\init([
            'dsn' =>  $this->sentryOptions['dsn'],
            'traces_sample_rate' => $this->sentryOptions['traces_sample_rate'] 
        ]);
        
       
        return $proceed();
     
        
    }
}
