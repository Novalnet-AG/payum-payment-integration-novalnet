<?php
namespace Payum\Novalnet;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Novalnet\Action\ObtainTokenAction;

class NovalnetCreditCardGatewayFactory extends NovalnetGatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults(
            [
            'payum.factory_name' => 'novalnet_creditcard',
            'payum.factory_title' => 'novalnet creditcard',
            'payment_data' => [
                    'action' => 'capture',
                    'inline' => 1
                ],
            'payum.template.obtain_token' => '@PayumNovalnet/Action/novalnet_creditcard.html.twig',
            'payum.action.obtain_token' => function (ArrayObject $config) {
                return new ObtainTokenAction($config['payum.template.obtain_token']);
            }
            ]
        );

        parent::populateConfig($config);
    }
}
