<?php
namespace Payum\Novalnet;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Novalnet\Action\ObtainTokenAction;

class NovalnetSepaGatewayFactory extends NovalnetGatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults(
            [
            'payum.factory_name' => 'novalnet_sepa',
            'payum.factory_title' => 'novalnet sepa',
            'payment_data' => [
                'action' => 'capture'
            ],
            'payum.template.obtain_token' => '@PayumNovalnet/Action/novalnet_sepa.html.twig',
            'payum.action.obtain_token' => function (ArrayObject $config) {
                return new ObtainTokenAction($config['payum.template.obtain_token']);
            }
            ]
        );

        parent::populateConfig($config);
    }
}
