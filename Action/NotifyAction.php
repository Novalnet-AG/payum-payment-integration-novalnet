<?php
namespace Payum\Novalnet\Action;

use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Notify;
use Payum\Core\Reply\HttpResponse;
use Payum\Novalnet\Action\Api\BaseApiAwareAction;
use Payum\Novalnet\NovalnetWebhooks;

class NotifyAction extends BaseApiAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param Notify $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        new NovalnetWebhooks($this->api);

        throw new HttpResponse('OK', 200);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Notify &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
