<?php
namespace Payum\Novalnet;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Novalnet\Action\ObtainTokenAction;

class NovalnetPaypalGatewayFactory extends NovalnetGatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults(
            [
            'payum.factory_name' => 'novalnet_paypal',
            'payum.factory_title' => 'novalnet paypal',
            'payment_data' => [
                'action' => 'capture'
            ]
            ]
        );

        parent::populateConfig($config);
    }
}
