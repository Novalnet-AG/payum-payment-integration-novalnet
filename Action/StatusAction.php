<?php
namespace Payum\Novalnet\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

class StatusAction implements ActionInterface
{
    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $status = !empty($model['nn_response']['transaction']['status'])
            ? $model['nn_response']['transaction']['status']
            : (!empty($model['nn_response']['status']) ? $model['nn_response']['status'] : '');

        if (isset($model['nn_response']['transaction']['refund'])
            && $model['nn_response']['transaction']['status'] == 'CONFIRMED'
        ) {
            $status = 'REFUNDED';
        }

        switch ($status) {
        case 'PENDING':
            $request->markPending();
            break;
        case 'ON_HOLD':
            $request->markAuthorized();
            break;
        case 'CONFIRMED':
            $request->markCaptured();
            break;
        case 'DEACTIVATED':
            $request->markCanceled();
            break;
        case 'FAILURE':
            $request->markFailed();
            break;
        case 'REFUNDED':
            $request->markRefunded();
            break;
        default:
            $request->markUnknown();
            break;
        }

        return;
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
