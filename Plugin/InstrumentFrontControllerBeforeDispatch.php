<?php

namespace MyVendor\MagentoSentry\Plugin;
use Magento\Framework\App\RequestInterface as AppRequestInterface;
use Magento\Webapi\Controller\Rest;
use Sentry\Tracing\TransactionContext;

class InstrumentFrontControllerBeforeDispatch {
    public function __construct()
    {
        
    }
    private function normalizePath($originalPathInfo){
        /*only normalized for numeric & alphanumeric path variables
        supporting other patterns (uuid4) might include 
       
        '([0-9A-F]{8}-[0-9A-F]{4}-[4][0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12})/ig';

        */
        return preg_replace('(\w*\d+\w*)',':var',$originalPathInfo);
    }
    public function beforeDispatch(Rest $subject, AppRequestInterface $request ){
       
        
        $sentryTraceHeader = $request->getHeader('sentry_trace');
        $baggageHeader = $request->getHeader('baggage');
        $transactionContext = TransactionContext::fromHeaders($sentryTraceHeader, $baggageHeader);
        $transactionContext->setOp('webapi');

        $routeName = $this->normalizePath($request->getOriginalPathInfo());
        $requestMethod = $request->getMethod();

        $transactionContext->setName("$requestMethod: $routeName");
        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
       
        
        return [$request];


    }
}

