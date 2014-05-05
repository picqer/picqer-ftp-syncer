<?php
namespace PicqerSync;

class PicklistToOrderConverter {

    protected $picqerclient;
    protected $ftpserver;
    protected $data;
    protected $config;
    protected $processedPicklists = array();

    public function __construct($picqerclient, $ftpserver, $data, $config)
    {
        $this->picqerclient = $picqerclient;
        $this->ftpserver = $ftpserver;
        $this->data = $data;
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function getProcessedPicklists()
    {
        return $this->processedPicklists;
    }

    public function convertPicklistsToOrders()
    {
        $toprocesss = $this->getPicklistsToProcess();
        if (count($toprocesss) > 0) {
            foreach ($toprocesss as $picklist) {
                $this->createOrderFromPicklist($picklist);
            }
        }
    }

    public function getPicklistsToProcess()
    {
        $picklists = $this->picqerclient->getAllPicklists(); // TODO: Using sinceid
        $toprocesss = array();
        foreach ($picklists['data'] as $picklist) {
            if ($picklist['status'] == 'new' && !in_array($picklist['idpicklist'], $this->data['picklists'])) {
                // Not processed picklist
                $order = $this->picqerclient->getOrder($picklist['idorder']);
                if ($order['success'] && isset($order['data'])) {
                    $picklist['order'] = $order['data'];
                }
                $toprocesss[] = $picklist;
            }
        }

        return $toprocesss;
    }

    public function createOrderFromPicklist($picklist)
    {
        $orderrules = $this->createOrderrules($picklist);
        $this->createOrderFile($picklist, $orderrules);
        $this->processedPicklists[] = $picklist['idpicklist'];
    }

    public function createOrderrules($picklist)
    {
        $orderrules = array(array(
            'Ordernumber' => 'Ordernumber',
            'Orderdate' => 'Orderdate',
            'OrderStatus' => 'OrderStatus',
            'PurchasedWebsite' => 'PurchasedWebsite',
            'PaymentMethod' => 'PaymentMethod',
            'ShippingMethod' => 'ShippingMethod',
            'Subtotal' => 'Subtotal',
            'ShippingCost' => 'ShippingCost',
            'GrandTotal' => 'GrandTotal',
            'TotalTax' => 'TotalTax',
            'TotalPaid' => 'TotalPaid',
            'TotalRefunded' => 'TotalRefunded',
            'ItemName' => 'ItemName',
            'ItemSKU' => 'ItemSKU',
            'ItemPrice' => 'ItemPrice',
            'CostPrice' => 'CostPrice',
            'ItemOrdered' => 'ItemOrdered',
            'ItemInvoiced' => 'ItemInvoiced',
            'ItemSent' => 'ItemSent',
            'CustomerID' => 'CustomerID',
            'CustomerEmail' => 'CustomerEmail',
            'BillingFirstName' => 'BillingFirstName',
            'BillingLastName' => 'BillingLastName',
            'BillingCompany' => 'BillingCompany',
            'BillingE-Mail' => 'BillingE-Mail',
            'BillingPhone' => 'BillingPhone',
            'BillingAddress1' => 'BillingAddress1',
            'BillingAddress2' => 'BillingAddress2',
            'BillingCity' => 'BillingCity',
            'BillingPostcode' => 'BillingPostcode',
            'BillingState' => 'BillingState',
            'BillingCountry' => 'BillingCountry',
            'ShippingFirstName' => 'ShippingFirstName',
            'ShippingLastName' => 'ShippingLastName',
            'ShippingCompany' => 'ShippingCompany',
            'ShippingE-Mail' => 'ShippingE-Mail',
            'ShippingPhone' => 'ShippingPhone',
            'ShippingAddress1' => 'ShippingAddress1',
            'ShippingAddress2' => 'ShippingAddress2',
            'ShippingCity' => 'ShippingCity',
            'ShippingPostcode' => 'ShippingPostcode',
            'ShippingState' => 'ShippingState',
            'ShippingCountry' => 'ShippingCountry',
            'Reference' => 'Reference'
        ));

        foreach ($picklist['products'] as $product) {
            //"100008365";"01/01/2014";"icecore_ok";"Main Website - Puur - Fit - English";"buckaroo3extended_ideal";"tablerate_bestway";"63.5400";"0.0000";"67.3500";"3.8100";"67.3500";"";"Bio Hennepolie - 500ml";"20120001";
            //"18.4000";"";"1.0000";"1.0000";"0.0000";"5488";"gmgvleerbos@home.nl";"anita";"vleerbos";"";"gmgvleerbos@home.nl";"0546-800794";"eskerplein";"159";"almelo";"7603 wh";"-";"NL";"anita";"vleerbos";"";
            //"gmgvleerbos@home.nl";"0546-800794";"eskerplein";"159";"almelo";"7603 wh";"-";"NL";

            $orderrule = array(
                'Ordernumber' => $picklist['picklistid'],
                'Orderdate' => $picklist['created'],
                'OrderStatus' => $this->config['csv-orderstatus'],
                'PurchasedWebsite' => $this->config['csv-purchasedwebsite'],
                'PaymentMethod' => $this->config['csv-paymentmethod'],
                'ShippingMethod' => $this->config['csv-shippingmethod'],
                'Subtotal' => '0',
                'ShippingCost' => '0',
                'GrandTotal' => '0',
                'TotalTax' => '0',
                'TotalPaid' => '0',
                'TotalRefunded' => '0',
                'ItemName' => $product['name'],
                'ItemSKU' => $product['productcode'],
                'ItemPrice' => $product['price'],
                'CostPrice' => '0',
                'ItemOrdered' => $product['amount'],
                'ItemInvoiced' => $product['amount'],
                'ItemSent' => '0',
                'CustomerID' => $picklist['idcustomer'],
                'CustomerEmail' => $picklist['emailaddress'],
                'BillingFirstName' => '',
                'BillingLastName' => '',
                'BillingCompany' => $picklist['deliveryname'],
                'BillingE-Mail' => $picklist['emailaddress'],
                'BillingPhone' => '',
                'BillingAddress1' => $picklist['deliveryaddress'],
                'BillingAddress2' => $picklist['deliveryaddress2'],
                'BillingCity' => $picklist['deliverycity'],
                'BillingPostcode' => $picklist['deliveryzipcode'],
                'BillingState' => '',
                'BillingCountry' => $picklist['deliverycountry'],
                'ShippingFirstName' => '',
                'ShippingLastName' => '',
                'ShippingCompany' => $picklist['deliveryname'],
                'ShippingE-Mail' => $picklist['emailaddress'],
                'ShippingPhone' => '',
                'ShippingAddress1' => $picklist['deliveryaddress'],
                'ShippingAddress2' => $picklist['deliveryaddress2'],
                'ShippingCity' => $picklist['deliverycity'],
                'ShippingPostcode' => $picklist['deliveryzipcode'],
                'ShippingState' => '',
                'ShippingCountry' => $picklist['deliverycountry']
            );

            if (isset($picklist['order']['reference'])) {
                $orderrule['Reference'] = $this->prepareReferece($picklist['order']['reference']);
            }

            $orderrules[] = $orderrule;
        }

        return $orderrules;
    }

    public function createOrderFile($picklist, $orderrules)
    {
        $filecontents = '';
        foreach ($orderrules as $orderrule) {
            $rulecontent = array();
            foreach ($orderrule as $field) {
                $rulecontent[] = '"' . $field . '"';
            }
            $filecontents .= implode(';', $rulecontent) . PHP_EOL;
        }

        $this->createFileOnFtp($picklist, $filecontents);
    }

    public function prepareReferece($reference)
    {
        $hashposition = strpos($reference, '#');
        if ($hashposition === false) {
            return $reference;
        }

        return substr($reference, $hashposition + 1);
    }

    public function createFileOnFtp($picklist, $filecontents)
    {
        logThis('Creating order for picklist ' . $picklist['idpicklist']);
        $this->ftpserver->getAdapter()->connect();
        $this->ftpserver->write($this->config['orders-directory'] . '/' . 'order-' . date('Ymd') . '-' . $picklist['idpicklist'] . '.csv', $filecontents);
    }

}