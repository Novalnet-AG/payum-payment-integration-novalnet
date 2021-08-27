<?php
namespace Payum\Novalnet\Action;

use Payum\Core\ApiAwareTrait;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Reply\HttpResponse;
use Payum\Core\Request\RenderTemplate;
use Payum\Novalnet\Request\Api\ObtainToken;
use Payum\Novalnet\Action\Api\BaseApiAwareAction;
use Payum\Novalnet\Api;

class ObtainTokenAction extends BaseApiAwareAction
{
    /**
     * @var string
     */
    protected $templateName;

    /**
     * @param string $templateName
     */
    public function __construct($templateName)
    {
        $this->templateName = $templateName;

        $this->apiClass = Api::class;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $paymentData = array_merge($model['nn_request'], $this->api->paymentData);
        $this->gateway->execute(
            $renderTemplate = new RenderTemplate(
                $this->templateName, [
                    'model' => $model,
                    'action_url' => $request->getToken() ? $request->getToken()->getTargetUrl() : null,
                    'after_url' => $request->getToken() ? $request->getToken()->getAfterUrl() : null,
                    'test_mode' => $this->api->sandbox,
                    'payment_data' => json_encode($paymentData)
                ]
            )
        );

        throw new HttpResponse($renderTemplate->getResult());
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof ObtainToken &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
