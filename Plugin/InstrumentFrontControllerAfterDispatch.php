<?php

namespace MyVendor\MagentoSentry\Plugin;
use Magento\Framework\App\RequestInterface;
use Magento\Webapi\Controller\Rest;
use Sentry\Tracing\SpanStatus;

class InstrumentFrontControllerAfterDispatch {
    public function __construct()
    {
        
    }
    public function afterDispatch(Rest $subject, $result, RequestInterface $request){
        $activeTransaction = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($activeTransaction !== null){
           
            $exceptions = $result->getException();

            if (!empty($exceptions)) {
                //need to check for exceptions and set status here. Otherwise only using $result->getStatusCode() returns incorrect status code
                foreach ($exceptions as $exception) {
                    //what is the root of multiple exceptions here?
                    $activeTransaction->setStatus(SpanStatus::createFromHttpStatusCode($exception->getHttpCode()));
                }}
            else{
                $activeTransaction->setStatus(SpanStatus::createFromHttpStatusCode($result->getStatusCode()));
            }
         
            $activeTransaction->finish();
        }
        return $result;

    }
}