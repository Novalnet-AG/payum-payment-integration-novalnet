<?php
namespace Payum\Novalnet;

use Payum\Novalnet\Action\AuthorizeAction;
use Payum\Novalnet\Action\CancelAction;
use Payum\Novalnet\Action\CaptureAction;
use Payum\Novalnet\Action\RefundAction;
use Payum\Novalnet\Action\StatusAction;
use Payum\Novalnet\Action\NotifyAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

class NovalnetGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig(ArrayObject $config)
    {
        $config->defaults(
            [
            'payum.factory_name' => 'novalnet',
            'payum.factory_title' => 'novalnet',
            'payum.action.capture' => new CaptureAction(),
            'payum.action.authorize' => new AuthorizeAction(),
            'payum.action.notify' => new NotifyAction(),
            'payum.action.refund' => new RefundAction(),
            'payum.action.cancel' => new CancelAction(),
            'payum.action.status' => new StatusAction(),
            ]
        );

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = [
                'payment_access_key' => '',
                'signature' => '',
                'tariff' => '',
                'sandbox' => false,
                'callback_debug_mode' => false
            ];

            $config->defaults($config['payum.default_options']);
            $config['payum.required_options'] = ['payment_access_key', 'signature', 'tariff'];

            $config['payum.api'] = function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                return new Api(
                    [
                    'factory_name' => $config['payum.factory_name'],
                    'signature' => $config['signature'],
                    'payment_access_key' => $config['payment_access_key'],
                    'tariff' => $config['tariff'],
                    'sandbox' => $config['sandbox'],
                    'callback_debug_mode' => $config['callback_debug_mode'],
                    'payment_data' => (!empty($config['payment_data']) ? $config['payment_data'] : '')
                    ], $config['payum.http_client'], $config['httplug.message_factory']
                );
            };
        }

        $config['payum.paths'] = array_replace(
            [
            'PayumNovalnet' => __DIR__.'/Resources/views',
            ], $config['payum.paths'] ?: []
        );
    }
}
