<?php
namespace Payum\Novalnet;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Novalnet\Action\ObtainTokenAction;

class NovalnetInvoiceGatewayFactory extends NovalnetGatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults(
            [
            'payum.factory_name' => 'novalnet_invoice',
            'payum.factory_title' => 'novalnet invoice',
            'payment_data' => [
                'action' => 'capture'
            ]
            ]
        );

        parent::populateConfig($config);
    }
}
