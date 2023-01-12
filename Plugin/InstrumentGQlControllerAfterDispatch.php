<?php

namespace MyVendor\MagentoSentry\Plugin;
use Magento\Framework\App\RequestInterface;
use Magento\GraphQl\Controller\GraphQl;
use Sentry\Tracing\SpanStatus;
use Magento\Framework\Serialize\SerializerInterface;
use MyVendor\MagentoSentry\Utils\Logger;

class InstrumentGQlControllerAfterDispatch {
    private $logger;
    private $jsonSerializer;

    public function __construct(
        SerializerInterface $jsonSerializer,
    )
    {        
        $this->jsonSerializer = $jsonSerializer;
    }
    public function afterDispatch(GraphQL $subject, $result, RequestInterface $request){
        $activeTransaction = \Sentry\SentrySdk::getCurrentHub()->getSpan();

        if ($activeTransaction !== null){
           //might not get accurate response codes from line below 
            $activeTransaction->setStatus(SpanStatus::createFromHttpStatusCode($result->getHttpResponseCode()));
            $body = $this->jsonSerializer->unserialize($result->getBody());
           
            //A GraphQL API will return a 200 OK Status Code even in case of error.
            //https://spec.graphql.org/October2021/#sec-Errors.Error-result-format
            if(!isset($body->errors)){
                //what is standary property access syntax?
               
                foreach ($body['errors'] as $e){
                    
                    $message = $e['message'] ?? '[]';
                    $locations = $e['locations'] ?? '[]';
                    $ext = $e['extensions'] ?? '[]';
                    $data = $e['data'] ?? '[]';

                    \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($locations,$ext,$data): void {
                        $scope->setContext('Error Meta',[
                            'locations' => $locations,
                            'extensions' => $ext,
                            'data' => $data
                            ]);  
                    });
                    
                    \Sentry\captureException(new \Exception("$message"));
                }
            }
           //is there a possibility for non finished transactions? Are these removed from scope? Would they persist between requests depending on web server implementation?
            $activeTransaction->finish();
        }
        return $result;

    }

    
}