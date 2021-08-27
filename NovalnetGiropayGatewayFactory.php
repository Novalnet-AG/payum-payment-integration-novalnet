<?php
namespace Payum\Novalnet;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Novalnet\Action\ObtainTokenAction;

class NovalnetGiropayGatewayFactory extends NovalnetGatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults(
            [
            'payum.factory_name' => 'novalnet_giropay',
            'payum.factory_title' => 'novalnet giropay',
            ]
        );

        parent::populateConfig($config);
    }
}
