<?php
namespace Payum\Novalnet\Action;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Request\Authorize;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Reply\HttpRedirect;
use Payum\Core\Request\GetHttpRequest;
use Payum\Novalnet\Action\Api\BaseApiAwareAction;
use Payum\Novalnet\Request\Api\ObtainToken;

class AuthorizeAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param Authorize $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $this->gateway->execute($httpRequest = new GetHttpRequest());

        if (!empty($httpRequest->query['status'])) {
            if (!empty($httpRequest->query['checksum']) && !empty($httpRequest->query['tid'])
                && preg_match('/^\d{17}$/', $httpRequest->query['tid'])
            ) {
                $checksumResponse = $this->api->checksumValidate($httpRequest->query, $model['txn_secret']);

                if ($checksumResponse == true) {
                    $response = $this->api->transactionDetails($httpRequest->query['tid']);
                    $model['nn_response'] = $response;
                } else {
                    $model['nn_response'] = $httpRequest->query;
                }

                return;
            } else {
                $model['nn_response'] = $httpRequest->query;
                return;
            }
        }

        $modelArray = $model->toUnsafeArray();

        if ($this->api->paymentType == 'CREDITCARD' && empty($httpRequest->request['nn_unique_id'])
            && empty($httpRequest->request['nn_pan_hash'])
        ) {
            $obtainToken = new ObtainToken($request->getToken());
            $obtainToken->setModel($model);
            $this->gateway->execute($obtainToken);
        }

        if ($this->api->paymentType == 'DIRECT_DEBIT_SEPA' && empty($httpRequest->request['nn_sepa_iban'])) {
            $obtainToken = new ObtainToken($request->getToken());
            $obtainToken->setModel($model);
            $this->gateway->execute($obtainToken);
        }

        $response = $this->api->payment($modelArray, $request->getToken()->getTargetUrl(), $httpRequest->request);

        if (!empty($response['result']['redirect_url'])) {
            // For checksum validation
            $model['txn_secret'] = $response['transaction']['txn_secret'];
            throw new HttpRedirect($response['result']['redirect_url']);
        } else {
            $model['nn_response'] = $response;
            return;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Authorize &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
