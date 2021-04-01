<?php
namespace Payum\Novalnet;

use Http\Message\MessageFactory;
use Payum\Core\Exception\Http\HttpException;
use Payum\Core\Exception\RuntimeException;
use Payum\Core\HttpClientInterface;

class Api
{
    /**
     * @var HttpClientInterface
     */
    protected $client;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    public $paymentData = [];

    /**
     * @var string
     */
    public $sandbox;

    /**
     * @var string
     */
    public $callbackDebugMode;

    /**
     * @var string
     */
    public $paymentType;

    /**
     * @var string
     */
    public $paymentAccessKey;

    /**
     * @var array
     */
    protected $redirectPayments = ['IDEAL', 'ONLINE_TRANSFER', 'GIROPAY', 'PRZELEWY24', 'EPS',
            'PAYPAL', 'POSTFINANCE_CARD', 'POSTFINANCE', 'BANCONTACT'];

    /**
     * @param array               $options
     * @param HttpClientInterface $client
     * @param MessageFactory      $messageFactory
     *
     * @throws \Payum\Core\Exception\InvalidArgumentException if an option is invalid
     */
    public function __construct(array $options, HttpClientInterface $client, MessageFactory $messageFactory)
    {
        $this->options = $options;
        $this->client = $client;
        $this->messageFactory = $messageFactory;
        $this->paymentData = $options['payment_data'];
        $this->paymentAccessKey = $options['payment_access_key'];
        $this->sandbox = (!empty($options['sandbox']) && $options['sandbox'] == true) ? '1' : '0';
        $this->paymentType = !empty($options['factory_name']) ? $this->getPaymentType($options['factory_name']) : '';
        $this->callbackDebugMode = (!empty($options['callback_debug_mode']) && $options['callback_debug_mode'] == true) ? '1' : '0';
    }

    /**
     * @param array  $params
     * @param string $targetUrl
     * @param array  $request
     *
     * @return array
     */
    public function payment($params, $targetUrl, $request)
    {
        $fields = $params['nn_request'];
        // Assign merchant params
        $fields['merchant'] = [
                'signature' => $this->options['signature'],
                'tariff' => $this->options['tariff'],
            ];

        // Assign payment mode
        $fields['transaction']['test_mode'] = $this->sandbox;

        // Assign payment type
        $fields['transaction']['payment_type'] = $this->getPaymentType($this->options['factory_name']);

        // Assign IP Address
        $fields['customer']['customer_ip'] = $this->getIpAddress();
        $fields['transaction']['system_ip'] = $this->getIpAddress('SERVER_ADDR');

        // Assign payment due date
        if (!empty($this->paymentData['due_date'])) {
            $fields['transaction']['due_date'] = date('Y-m-d', strtotime('+'.$this->paymentData['due_date'].' days'));
        }

        // Assign payment form data
        if ($this->paymentType == 'CREDITCARD' && !empty($request['nn_unique_id'])
            && !empty($request['nn_pan_hash'])
        ) {
            $fields['transaction']['payment_data']['pan_hash'] = $request['nn_pan_hash'];
            $fields['transaction']['payment_data']['unique_id'] = $request['nn_unique_id'];

            if ($request['nn_do_redirect'] == 1 && isset($this->paymentData['enforce_3d'])
                && $this->paymentData['enforce_3d'] == true
            ) {
                $fields['transaction']['enforce_3d'] = 1;
            }
        }

        if ($this->paymentType == 'DIRECT_DEBIT_SEPA' && !empty($request['nn_sepa_iban'])) {
            $fields['transaction']['payment_data']['iban'] = $request['nn_sepa_iban'];
        }

        // Assign return url's
        if (in_array($this->paymentType, $this->redirectPayments)
            || ($this->paymentType == 'CREDITCARD' && $request['nn_do_redirect'] == 1)
        ) {
            $this->getRedirectPaymentDetails($fields, $targetUrl);
        }

        return $this->doRequest('payment', $fields);
    }

    /**
     * @param array  $fields
     * @param string $targetUrl
     *
     * @return void
     */
    protected function getRedirectPaymentDetails(&$fields, $targetUrl)
    {
        $fields['transaction']['return_url'] = $targetUrl;
        $fields['transaction']['error_return_url'] = $targetUrl;
    }

    /**
     * @param array  $request
     * @param string $txn_secret
     *
     * @return boolean
     */
    public function checksumValidate($request, $txn_secret)
    {
        $token_string = $request['tid'] . $txn_secret . $request['status'] . strrev($this->options['payment_access_key']);
        $generated_checksum = hash('sha256', $token_string);

        if ($generated_checksum !== $request['checksum']) {
            throw new RuntimeException('While redirecting some data has been changed. The hash check failed');
        }

        return true;
    }

    /**
     * @param string $tid
     *
     * @return array
     */
    public function transactionDetails($tid)
    {
        $params = ['transaction' => [
                'tid' => $tid
            ]
        ];

        return $this->doRequest('details', $params);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function capture($params)
    {
        return $this->doRequest('capture', $params['nn_request']);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function cancel($params)
    {
        return $this->doRequest('cancel', $params['nn_request']);
    }

    /**
     * @param array $params
     *
     * @return array
     */
    public function refund($params)
    {
        return $this->doRequest('refund', $params['nn_request']);
    }

    /**
     * @param array $fields
     *
     * @return array
     */
    protected function doRequest($method, array $fields)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Charset' => 'utf-8',
            'Accept' => 'application/json',
            'X-NN-Access-Key' => base64_encode($this->options['payment_access_key']),
        ];

        $request = $this->messageFactory->createRequest('POST', $this->getApiEndpoint($method), $headers, json_encode($fields));
        $response = $this->client->send($request);

        if (false == ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)) {
            throw HttpException::factory($request, $response);
        }

        $result = json_decode((string)$response->getBody(), true);

        return $result;
    }

    /**
     * @param string $method
     *
     * @return string
     */
    protected function getApiEndpoint($method)
    {
        if ($method == 'payment') {
            $action = (!empty($this->paymentData['action']) && $this->paymentData['action'] == 'authorize')
                ? 'authorize' : 'payment';
            return 'https://payport.novalnet.de/v2/' . $action;
        }

        return 'https://payport.novalnet.de/v2/transaction/' . $method;
    }

    /**
     * @param string $factoryName
     *
     * @return string|null
     */
    protected function getPaymentType($factoryName)
    {
        $paymentMethods = [
            'novalnet_creditcard' => 'CREDITCARD',
            'novalnet_sepa' => 'DIRECT_DEBIT_SEPA',
            'novalnet_invoice' => 'INVOICE',
            'novalnet_prepayment' => 'PREPAYMENT',
            'novalnet_ideal' => 'IDEAL',
            'novalnet_sofort' => 'ONLINE_TRANSFER',
            'novalnet_giropay' => 'GIROPAY',
            'novalnet_barzahlen' => 'CASHPAYMENT',
            'novalnet_przelewy' => 'PRZELEWY24',
            'novalnet_eps' => 'EPS',
            'novalnet_paypal' => 'PAYPAL',
            'novalnet_postfinancecard' => 'POSTFINANCE_CARD',
            'novalnet_postfinance' => 'POSTFINANCE',
            'novalnet_bancontact' => 'BANCONTACT',
            'novalnet_multibanco' => 'MULTIBANCO'
        ];

        return !empty($paymentMethods[$factoryName]) ? $paymentMethods[$factoryName] : $factoryName;
    }

    /**
     * @param string $type
     *
     * @return string
     */
    public function getIpAddress($type = 'REMOTE_ADDR')
    {
        // Check to determine the IP address type
        if ($type == 'SERVER_ADDR') {
            if (empty($_SERVER['SERVER_ADDR'])) {
                // Handled for IIS server
                $ip_address = gethostbyname($_SERVER['SERVER_NAME']);
            } else {
                $ip_address = $_SERVER['SERVER_ADDR'];
            }
        } else { // For remote address
            $ip_address = $this->getRemoteAddress();
        }

        return (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) ? '127.0.0.1' : $ip_address;
    }

    /**
     * @return string
     */
    public function getRemoteAddress()
    {
        $ip_keys = ['HTTP_X_FORWARDED_HOST', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    // trim for safety measures
                    return trim($ip);
                }
            }
        }
    }
}
