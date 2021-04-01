# Payum Payment Integration by Novalnet

Payum is one of the most popular bug-free solution that has over 1 000 000 downloads already. It is friendly for all top PHP frameworks and was successfully installed and tested by thousands of developers worldwide. Reduce Your Development Time with Payum integrating more than 50 payment services simultaneously.

## Advantages
-   Easy configuration for all payment methods
-   One platform for all relevant payment types and related services
-   Complete automation of all payment processes
-   More than 50 fraud prevention modules integrated to prevent risk in real-time
-   No PCI DSS certification required when using our payment module
-   Real-time monitoring of the transaction flow from the checkout to the receivables
-   Multilevel claims management with integrated handover to collection and various export functions for the accounting
-   Automated e-mail notification function concerning payment status reports
-   Clear real-time overview and monitoring of payment status
-   Automated bookkeeping report in XML, SOAP, CSV, MT940

## Supported payment methods
-   Direct Debit SEPA
-   Credit Card (3DSecure and non 3DSecure)
-   Invoice
-   Prepayment
-   Barzahlen
-   Instant Bank Transfer
-   PayPal
-   iDEAL
-   eps
-   giropay
-   Przelewy24
-   Postfinance
-   Postfinance card
-   Bancontact
-   Multibanco

## Key features
*   Secure SSL- encoded gateways
*   Seamless and fast integration of the payment module
*   Credit Card with 3D Secure
*   On-hold transaction configuration in the shop admin panel
*   Easy way of confirmation and cancellation of on-hold transactions (Cancel & Capture option) for Direct Debit SEPA, Direct Debit SEPA with payment guarantee, Credit Card,     Invoice, Invoice with payment guarantee, Prepayment & PayPal.
*   Refund option for Credit Card, Direct Debit SEPA, Direct Debit SEPA with payment guarantee, Invoice, Invoice with payment guarantee, Prepayment, Barzahlen, Instant Bank Transfer, iDEAL, eps, giropay, PayPal & Przelewy24.
*   Responsive templates

##  Installation

The preferred way to install the library is using [composer](http://getcomposer.org/).
Run composer require to add dependencies to _composer.json_:

```bash
composer require novalnet/payum php-http/guzzle6-adapter
```

## Configuration

Add to the default app/Providers/AppServiceProvider.php register method:

```php
...
public function register()
{
    $this->app->resolving('payum.builder', function(\Payum\Core\PayumBuilder $payumBuilder) {
        $payumBuilder
        ->addDefaultStorages()
        ->addGatewayFactory('novalnet', function(array $config, GatewayFactoryInterface $coreGatewayFactory) {
            return new \Payum\Novalnet\NovalnetGatewayFactory($config, $coreGatewayFactory);
        })
        ->addGateway('novalnet', [
            'factory' => 'novalnet',
            'payment_access_key' => '###YOUR_PAYMENT_ACCESS_KEY###',
            'signature' => '###YOUR_API_SIGNATURE###',
            'tariff' => '###YOUR_TARIFF_ID###'
        ]);
    });

    $this->app->resolving('payum.builder', function(\Payum\Core\PayumBuilder $payumBuilder) {
        $payumBuilder
        ->addDefaultStorages()
        ->addGatewayFactory('novalnet_creditcard', function(array $config, GatewayFactoryInterface $coreGatewayFactory) {
            return new \Payum\Novalnet\NovalnetCreditCardGatewayFactory($config, $coreGatewayFactory);
        })
        ->addGateway('novalnet_creditcard', [
            'factory' => 'novalnet_creditcard',
            'payment_access_key' => '###YOUR_PAYMENT_ACCESS_KEY###',
            'signature' => '###YOUR_API_SIGNATURE###',
            'tariff' => '###YOUR_TARIFF_ID###'
            'sandbox' => false, // (true/false) true = The payment will be processed in the test mode therefore amount for this transaction will not be charged, false = The payment will be processed in the live mode.
            'callback_debug_mode' => false, // (true/false) Please disable this option before setting your shop to LIVE mode, to avoid unauthorized calls from external parties (excl. Novalnet). For LIVE, set the value as false.
            'payment_data' => [
                'action' => 'capture', // (authorize/capture) Capture completes the transaction by transferring the funds from buyer account to merchant account. Authorize verifies payment details and reserves funds to capture it later, giving time for the merchant to decide on the order
                'client_key' => '###YOUR_CLIENT_KEY###', // A public unique key needs linked to your account. It is needed to do the client-side authentication. You can find this credential by logging into your Novalnet Admin Portal
                'enforce_3d' => false, // (true/false) By enabling this option, all payments from cards issued outside the EU will be authenticated via 3DS 2.0 SCA.
                'inline' => true, // (true/false) true = Show Inline Credit card form form, false = Show Credit Card form in multi lines.
                'container' => '', // Customize styles of the Credit Card iframe.
                'input' => '', // Customize styles of the Credit Card iframe input element.
                'label' => '', // Customize styles of the Credit Card iframe label element.
            ],
        ]);
    });
}
...
```

## Prepare payment

Lets create a controller where we prepare the payment details.

```php
<?php

namespace App\Http\Controllers;

use Payum\LaravelPackage\Controller\PayumController;
use Payum\Core\Model\ArrayObject;

class NovalnetPaymentController extends PayumController
{
    public function doPayment()
    {
        $gatewayName = 'novalnet_creditcard';

        $storage = $this->getPayum()->getStorage('Payum\Core\Model\ArrayObject');

        $details = $storage->create();

        $data['customer'] = [
                'first_name' => 'novalnet',
                'last_name' => 'tester',
                'email' => 'test@novalnet.de',
                'customer_no' => '147',
                'billing' => [
                    'street' => 'Feringastraße',
                    'house_no' => '4',
                    'city' => 'Unterföhring',
                    'zip' => '85774',
                    'country_code' => 'DE',
                ]
            ];
        $data['transaction'] = [
                'amount' => '150',
                'currency' => 'EUR',
                'order_no' => '123456',
            ];
        $data['custom'] = [
                'lang' => 'EN'
            ];

        $details['nn_request'] = $data;

        $storage->update($details);

        $notifyToken = $this->getPayum()->getTokenFactory()->createNotifyToken($gatewayName, $details);
        $data['transaction']['hook_url'] = $notifyToken->getTargetUrl();

        $details['nn_request'] = $data;

        $storage->update($details);

        $authorizeToken = $this->getPayum()->getTokenFactory()->createAuthorizeToken($gatewayName, $details, 'payment_done');

        return \Redirect::to($authorizeToken->getTargetUrl());
    }
}

```

Lets create a controller where we prepare the payment refund.

```php
<?php

namespace App\Http\Controllers;

use Payum\LaravelPackage\Controller\PayumController;
use Payum\Core\Model\ArrayObject;

class NovalnetRefundController extends PayumController
{
    public function doRefund()
    {
        $gatewayName = 'novalnet';

        $storage = $this->getPayum()->getStorage('Payum\Core\Model\ArrayObject');

        $details = $storage->create();

        $data = ['transaction' => [
                'tid' => '###NOVALNET_TID###',
                'amount' => 'XXX'
            ]
        ];

        $details['nn_request'] = $data;

        $storage->update($details);

        $refundToken = $this->getPayum()->getTokenFactory()->createRefundToken($gatewayName, $details, 'payment_done');

        return \Redirect::to($refundToken->getTargetUrl());
    }
}

```

For more information about payment integration see the [developer portal](https://developer.novalnet.de/)
Please find the relevant documentation about payment integration

Here's you may want to modify a `payment_done` route.
It is a controller where the payer will be redirected after the payment is done, whenever it is success failed or pending.
Read a [dedicated chapter](https://github.com/Payum/Payum/blob/master/docs/examples/done-script.md) about how the payment done controller may look like.

## Technical support through Novalnet
If you have any inquiries, please contact one of the following departments:

### Technical support
technic@novalnet.de <br>
+49 89 9230683-19 <br>

### Sales team
sales@novalnet.de <br>
+49 89 9230683-20 <br>

## Who is Novalnet?
[Novalnet](https://novalnet.de/) is a German payment provider offering payment gateways for online merchants and marketplaces worldwide. Our PCI DSS certified SaaS engine is designed to automate the entire payment process from checkout to debt collection – with a single integration. We cover real-time risk management; secure payments (local + international) through escrow accounts, integrate receivables management, dynamic member and subscription management as well as other customized payment solutions for all your shop systems.
