<?php

namespace MyVendor\MagentoSentry\Plugin;
use Magento\Framework\App\RequestInterface;
use Magento\GraphQl\Controller\GraphQl;
use Sentry\Tracing\SpanStatus;
use Magento\Framework\Serialize\SerializerInterface;

class InstrumentGQlControllerAfterDispatch {
 
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
            //A GraphQL API will return a 200 OK Status Code even in case of error.
            $activeTransaction->setStatus(SpanStatus::createFromHttpStatusCode($result->getHttpResponseCode()));
            $body = $this->jsonSerializer->unserialize($result->getBody());
           
           
            //https://spec.graphql.org/October2021/#sec-Errors.Error-result-format
            if(!isset($body->errors)){
               
                foreach ($body['errors'] as $e){
                    
                    $message = $e['message'] ?? '[]';
                    $locations = $e['locations'] ?? '[]';
                    $ext = $e['extensions'] ?? '[]';
                    $data = $e['data'] ?? '[]';
                    $path = $e['path'] ?? '[]'; 

                    \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($message,$locations,$ext,$data,$path): void {
                        $scope->setContext('GQL Error Meta',[
                            'locations' => $locations,
                            'extensions' => $ext,
                            'data' => $data,    
                            'path' => $path
                            ]);  
                        $scope->setFingerprint([$message,'{{default}}']);
                        \Sentry\captureException(new \Exception("$message"));
                    });
                    
                    
                }
            }
            $activeTransaction->finish();
        }
        return $result;

    }

    
}