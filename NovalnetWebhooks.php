<?php
namespace Payum\Novalnet;

use Payum\Core\Reply\HttpResponse;

class NovalnetWebhooks
{
    /**
     * Allowed host from Novalnet.
     *
     * @var string
     */
    protected $novalnetHostName = 'pay-nn.de';

    /**
     * Mandatory Parameters.
     *
     * @var array
     */
    protected $mandatory = [
        'event'       => [
            'type',
            'checksum',
            'tid',
        ],
        'merchant'    => [
            'vendor',
            'project',
        ],
        'result'      => [
            'status',
        ],
        'transaction' => [
            'tid',
            'payment_type',
            'status',
        ],
    ];

    /**
     * Callback test mode.
     *
     * @var int
     */
    protected $testMode;

    /**
     * Request parameters.
     *
     * @var array
     */
    protected $eventData = [];

    /**
     * Your payment access key value
     *
     * @var string
     */
    protected $paymentAccessKey;

    /**
     * Order reference values.
     *
     * @var array
     */
    protected $orderReference = [];

    /**
     * Recived Event type.
     *
     * @var string
     */
    protected $eventType;

    /**
     * Recived Event TID.
     *
     * @var int
     */
    protected $eventTid;

    /**
     * Recived Event parent TID.
     *
     * @var int
     */
    protected $parentTid;

    /**
     * Api
     *
     * @var object
     */
    protected $api;

    /**
     * Novalnet_Webhooks constructor.
     *
     * @since 2.0.0
     */
    public function __construct($api)
    {
        $this->api = $api;

        // Authenticate request host.
        $this->authenticateEventData();

        // Get request parameters.
        $this->validateEventData();

        // Your payment access key value.
        $this->paymentAccessKey  = $this->api->paymentAccessKey;

        // Validate checksum
        $this->validateChecksum();

        // Set Event data.
        $this->eventType = $this->eventData ['event'] ['type'];
        $this->eventTid  = $this->eventData ['event'] ['tid'];
        $this->parentTid = $this->eventTid;
        if (!empty($this->eventData ['event'] ['parentTid'])) {
            $this->parentTid = $this->eventData ['event'] ['parentTid'];
        }

        // Get order reference.
        $this->orderReference = $this->getOrderReference();

        // Order number check.
        if (!empty($this->eventData ['transaction'] ['order_no']) && isset($this->orderReference ['order_no']) && $this->orderReference ['order_no'] !== $this->eventData ['transaction'] ['order_no']) {
            $this->displayMessage(['message' => 'Order reference not matching.']);
        }

        switch ($this->eventType) {
        case "PAYMENT":
            // Handle Initial PAYMENT notification (incl. communication failure, Authorization).
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->eventTid."]);
            break;

        case "TRANSACTION_CAPTURE":
            // Handle TRANSACTION_CAPTURE notification. It confirms the successful capture of the transaction.
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->eventTid."]);
            break;

        case "TRANSACTION_CANCEL":
            // Handle TRANSACTION_CANCEL notification. It confirms the successful cancelation of the transaction.
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->eventTid."]);
            break;

        case "TRANSACTION_REFUND":
            // Handle TRANSACTION_REFUND notification. It confirms the successful refund (partial/full) of the transaction.
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->eventTid."]);
            break;

        case "TRANSACTION_UPDATE":
            // Handle TRANSACTION_UPDATE notification. It confirms the successful update (payment status/amount/due date/order number) of the transaction.
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->eventTid."]);
            break;

        case "CREDIT":
            // Handle CREDIT notification. It confirms that the payment (partial/full) for the transaction was received
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->parentTid and the new reference TID was $this->eventTid"]);
            break;

        case "CHARGEBACK":
            // Handle CHARGEBACK notification. It confirms that the chargeback (for Credit Card) has been received for the transaction.
            $this->displayMessage(['message' => "The webhook notification received ($this->eventType) for the TID: $this->parentTid and the new reference TID was $this->eventTid"]);
            break;

        default:
            $this->displayMessage(['message' => "The webhook notification has been received for the unhandled EVENT type($this->eventType)"]);
        }
    }

    /**
     * Authenticate server request
     *
     * @since 12.0.0
     */
    public function authenticateEventData()
    {
        // Backend callback option.
        $this->testMode = $this->api->callbackDebugMode; // Your shop test mode value

        // Authenticating the server request based on IP.
        $requestReceivedIp = $this->api->getIpAddress();

        // Host based validation
        if (!empty($this->novalnetHostName)) {
            $novalnetHostIp  = gethostbyname($this->novalnetHostName);
            if (!empty($novalnetHostIp) && ! empty($requestReceivedIp)) {
                if ($novalnetHostIp !== $requestReceivedIp && ! $this->testMode) {
                    $this->displayMessage(['message' => 'Unauthorised access from the IP ' . $requestReceivedIp]);
                }
            } else {
                $this->displayMessage([ 'message' => 'Unauthorised access from the IP. Host/recieved IP is empty' ]);
            }
        } else {
            $this->displayMessage([ 'message' => 'Unauthorised access from the IP. Novalnet Host name is empty' ]);
        }
    }

    /**
     * Validate server request
     *
     * @return void
     */
    public function validateEventData()
    {
        try {
            $this->eventData = json_decode(file_get_contents('php://input'), true);
        } catch (Exception $e) {
            $this->displayMessage(['message' => "Received data is not in the JSON format $e"]);
        }
        if (! empty($this->eventData ['custom'] ['shop_invoked'])) {
            $this->displayMessage([ 'message' => 'Process already handled in the shop.' ]);
        }

        // Validate request parameters.
        foreach ($this->mandatory as $category => $parameters) {
            if (empty($this->eventData [ $category ])) {
                // Could be a possible manipulation in the notification data.
                $this->displayMessage([ 'message' => "Required parameter category($category) not received" ]);
            } elseif (! empty($parameters)) {
                foreach ($parameters as $parameter) {
                    if (empty($this->eventData [ $category ] [ $parameter ])) {
                        // Could be a possible manipulation in the notification data.
                        $this->displayMessage([ 'message' => "Required parameter($parameter) in the category($category) not received" ]);
                    } elseif (in_array($parameter, [ 'tid', 'parentTid' ], true) && ! preg_match('/^\d{17}$/', $this->eventData [ $category ] [ $parameter ])) {
                        $this->displayMessage([ 'message' => "Invalid TID received in the category($category) not received $parameter" ]);
                    }
                }
            }
        }
    }

    /**
     * Validate checksum
     *
     * @since 12.0.0
     */
    public function validateChecksum()
    {
        $tokenString = $this->eventData ['event'] ['tid'] . $this->eventData ['event'] ['type'] . $this->eventData ['result'] ['status'];

        if (isset($this->eventData ['transaction'] ['amount'])) {
            $tokenString .= $this->eventData ['transaction'] ['amount'];
        }
        if (isset($this->eventData ['transaction'] ['currency'])) {
            $tokenString .= $this->eventData ['transaction'] ['currency'];
        }
        if (! empty($this->paymentAccessKey)) {
            $tokenString .= strrev($this->paymentAccessKey);
        }

        $generatedChecksum = hash('sha256', $tokenString);

        if ($generatedChecksum !== $this->eventData ['event'] ['checksum']) {
            $this->displayMessage([ 'message' => 'While notifying some data has been changed. The hash check failed' ]);
        }
    }

    /**
     * Get order reference.
     *
     * @return array
     */
    public function getOrderReference()
    {
        $orderReference = [];

        // Get the transaction/order details based on the $this->tid value
        return $orderReference;
    }

    /**
     * Print the Webhook messages.
     *
     * @param array $data
     *
     * @return void
     */
    public function displayMessage($data)
    {
        throw new HttpResponse(json_encode($data), 200);
    }

}
