<?php
namespace PicqerSync;

class InboundsProcessor {

    protected $picqerclient;
    protected $ftpserver;
    protected $config;
    protected $purchaseorderProducts = array();

    public function __construct($picqerclient, $ftpserver, $config)
    {
        $this->picqerclient = $picqerclient;
        $this->ftpserver = $ftpserver;
        $this->config = $config;
    }

    public function processInbounds()
    {
        $this->getOpenPurchaseorderProductsFromPicqer();

        $inboundfiles = $this->getInboundFiles();
        if (count($inboundfiles) > 0) {
            foreach ($inboundfiles as $filename => $data) {
                $this->processInboundFile($data);
                $this->moveInboundFile($filename);
            }
        }
    }

    public function getOpenPurchaseorderProductsFromPicqer()
    {
        $purchaseorders = $this->picqerclient->getPurchaseorders();
        if ($purchaseorders['success']) {
            foreach ($purchaseorders['data'] as $purchaseorder) {
                if ($purchaseorder['status'] == 'finalized' && count($purchaseorder['products']) > 0) {
                    foreach ($purchaseorder['products'] as $product) {
                        $amounttoreceive = $product['amount'] - $product['amountreceived'];
                        if ($amounttoreceive != 0) {
                            $this->purchaseorderProducts[] = array(
                                'idproduct' => (int)$product['idproduct'],
                                'idpurchaseorder' => (int)$purchaseorder['idpurchaseorder'],
                                'amount' => (int)$product['amount'],
                                'amountreceived' => (int)$product['amountreceived'],
                                'amounttoreceive' => (int)$amounttoreceive,
                                'productcode' => $product['productcode']
                            );
                        }
                    }
                }
            }
            logThis('Purchase orders retrieved from Picqer');
        } else {
            logThis('Could not get purchase orders');
        }
        $this->purchaseorderProducts = array_reverse($this->purchaseorderProducts, false);
    }

    public function getInboundFiles()
    {
        $files = array();
        $itemsOnFtp = $this->ftpserver->listContents($this->config['inbounds-directory']);
        foreach ($itemsOnFtp as $item) {
            if ($item['type'] == 'file') {
                $files[$item['basename']] = $this->ftpserver->read($item['path']);
            }
        }

        logThis(count($files) . ' inbound files found');

        return $files;
    }

    public function processInboundFile($data)
    {
        $rules = explode(PHP_EOL, $data);
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (!empty($rule)) {
                $ruledata = $this->parseInboundRule($rule);
                $this->processInboundRule($ruledata);
            }
        }
    }

    public function parseInboundRule($rule)
    {
        // "20120009";"Acai bessen poeder, biologisch";"1";"10-3-2014 11:06:29";"Retour from order 100010449";
        $fields = explode(';', $rule);
        if (isset($fields[4]) && (strpos($fields[4], 'Retour') !== false || strpos($fields[4], 'Return') !== false)) {
            return null;
        }
        if (isset($fields[0]) && isset($fields[2])) {
            return array(
                'productcode' => trim($fields[0], '"'),
                'amount' => trim($fields[2], '"')
            );
        } else {
            return null;
        }
    }

    public function processInboundRule($inbounddata)
    {
        $amountToGo = $inbounddata['amount'];
        foreach ($this->purchaseorderProducts as $id => $purchaseorderproduct) {
            if ($purchaseorderproduct['productcode'] == $inbounddata['productcode'] && $purchaseorderproduct['amounttoreceive'] > 0) {
                if ($amountToGo <= $purchaseorderproduct['amounttoreceive']) {
                    $amount = $amountToGo;
                } else {
                    $amount = $purchaseorderproduct['amounttoreceive'];
                }
                $result = $this->picqerclient->receivePurchaseorderProduct($purchaseorderproduct['idpurchaseorder'], array(
                    'idproduct' => $purchaseorderproduct['idproduct'],
                    'amount' => $amount
                ));
                if (isset($result['success']) && $result['success']) {
                    logThis('Product ' . $inbounddata['productcode'] . ' found in purchase order and received');
                } else {
                    logThis('Problem with receiving product ' . $inbounddata['productcode']);
                }
                $this->purchaseorderProducts[$id]['amountreceived'] += $amount;
                $this->purchaseorderProducts[$id]['amounttoreceive'] -= $amount;
                $amountToGo -= $amount;
                if ($amountToGo <= 0) {
                    break;
                }
            }
        }
    }

    public function moveInboundFile($filename)
    {
        logThis('Moving ' . $filename);
        $this->ftpserver->getAdapter()->connect();
        $this->ftpserver->rename($this->config['inbounds-directory'] . '/' . $filename, $this->config['inbounds-processed-directory'] . '/' . $filename . '-' . date('YmdHis') . '-' . rand(1000, 9999));
    }

}