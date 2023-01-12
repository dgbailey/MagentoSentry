<?php

namespace MyVendor\MagentoSentry\Plugin;
use Magento\Framework\App\RequestInterface as AppRequestInterface;
use Magento\GraphQl\Controller\GraphQl;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\GraphQl\Query\Fields as QueryFields;
use Sentry\Tracing\TransactionContext;

class InstrumentGQlControllerBeforeDispatch {

    private $jsonSerializer;
    public $data;
    public $variables;
    public $query;
    public $txnName;
    public $method;
    public $queryFields;
    public const TXN_OPNAME = 'gql';
   

    public function __construct(
        SerializerInterface $jsonSerializer,
        QueryFields $queryFields,
    )
    {
        $this->jsonSerializer = $jsonSerializer;
        $this->data = NULL;
        $this->variables = [];
        $this->query = [];
        $this->txnName = "GQL<No Op Name>";
        $this->method = "<unknown>";
        $this->queryFields = $queryFields;

    }
    public function beforeDispatch(GraphQl $subject, AppRequestInterface $request){
  
        //https://github.com/magento/magento2/blob/2.4-develop/app/code/Magento/GraphQl/Controller/GraphQl.php#L188

        $sentryTraceHeader = $request->getHeader('sentry_trace');
        $baggageHeader = $request->getHeader('baggage');

        //checks for sampling decision. Headers optional.
        $transactionContext = TransactionContext::fromHeaders($sentryTraceHeader, $baggageHeader);
        if($request->isPost()){
            $this->method = "POST";
            $this->data = $this->jsonSerializer->unserialize($request->getContent());
            $this->variables = isset($this->data['variables']) ? $this->data['variables'] : $this->variables;
           
        } elseif($request->isGet()){
            //not tested yet
            $this->method = "GET";
            $this->data = $request->getParams();
            $this->variables = isset($this->data['variables']) ? $this->jsonSerializer->unserialize($this->data['variables']) : '[]';
        } 

       //There is no guarantee that queries will arrive w/ operation names 
        $this->queryFields->setQuery($this->data['query'],$this->variables);

        if(isset($this->data['operationName'])){
            $this->txnName = $this->data['operationName'];
        }else{
            //if no operation name should we look elsewhere?
            // Can we guess based on traversal pattern of gql object? Could add dependencies and assumptions based on said dependencies.
        }

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) : void {
            $this->query = $this->data['query']  ?? $this->query;
            $scope->setContext('Query',['query' => $this->query]);  
            $scope->setContext('Variables',['vars' => $this->variables]); 
        });
      
        $transactionContext->setOp(self::TXN_OPNAME);
        $transactionContext->setName("$this->txnName");
        $transaction = \Sentry\startTransaction($transactionContext);
        \Sentry\SentrySdk::getCurrentHub()->setSpan($transaction);
       
       return [$request];


    }
}

