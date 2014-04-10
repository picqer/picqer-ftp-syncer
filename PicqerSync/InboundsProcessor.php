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
        // "20130206";"Moringa poeder";"30";"31-3-2014 14:04:35";
        $fields = explode(';', $rule);
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
                $this->picqerclient->receivePurchaseorderProduct($purchaseorderproduct['idpurchaseorder'], array(
                    'idproduct' => $purchaseorderproduct['idproduct'],
                    'amount' => $amount
                ));
                $this->purchaseorderProducts[$id]['amountreceived'] += $amount;
                $this->purchaseorderProducts[$id]['amounttoreceive'] -= $amount;
                $amountToGo -= $amount;
                logThis("Inbound product ".$purchaseorderproduct['productcode']." received");
                if ($amountToGo <= 0) {
                    break;
                }
            }
        }
    }

    public function moveInboundFile($filename)
    {
        $this->ftpserver->rename($this->config['inbounds-directory'] . '/' . $filename, $this->config['inbounds-processed-directory'] . '/' . $filename);
    }

}