<?php
namespace PicqerSync;

class ReturnsProcessor {

    protected $picqerclient;
    protected $ftpserver;
    protected $config;

    public function __construct($picqerclient, $ftpserver, $config)
    {
        $this->picqerclient = $picqerclient;
        $this->ftpserver = $ftpserver;
        $this->config = $config;
    }

    public function processReturns()
    {
        $returnfiles = $this->getReturnFiles();
        if (count($returnfiles) > 0) {
            foreach ($returnfiles as $filename => $data) {
                $this->processReturnFile($data);
                $this->moveReturnFile($filename);
            }
        }
    }

    public function getReturnFiles()
    {
        $files = array();
        $itemsOnFtp = $this->ftpserver->listContents($this->config['returns-directory']);
        foreach ($itemsOnFtp as $item) {
            if ($item['type'] == 'file') {
                $files[$item['basename']] = $this->ftpserver->read($item['path']);
            }
        }

        logThis(count($files) . ' returns files found');

        return $files;
    }

    public function processReturnFile($data)
    {
        $rules = explode(PHP_EOL, $data);
        $returns = array();
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (!empty($rule)) {
                $ruledata = $this->parseReturnRule($rule);
                if (!empty($ruledata)) {
                    $returns[$ruledata['picklistid']][] = $ruledata;
                }
            }
        }

        foreach ($returns as $picklistid => $returndata) {
            $this->processPicklistReturn($picklistid, $returndata);
        }
    }

    public function parseReturnRule($rule)
    {
        // "100009822";"20120011";"Goji bessen, tibetaanse";"3";"12-2-2014 10:34:45";
        $fields = explode(';', $rule);
        if (isset($fields[0]) && isset($fields[1]) && isset($fields[3])) {
            return array(
                'picklistid' => trim($fields[0], '"'),
                'productcode' => trim($fields[1], '"'),
                'amount' => trim($fields[3], '"')
            );
        } else {
            return null;
        }
    }

    public function processPicklistReturn($picklistid, $returndata)
    {
        $picklist = $this->getPicklistFromPicklistid($picklistid);
        if (isset($picklist['data']['idcustomer']) && !empty($picklist['data']['idcustomer'])) {
            $returnorder = array(
                'idcustomer' => $picklist['data']['idcustomer'],
                'reference' => 'Return order from picklist ' . $picklist['data']['picklistid'],
                'products' => array()
            );
            foreach ($returndata as $returnrule) {
                $returnorder['products'][] = array(
                    'idproduct' => $this->getIdproductFromProductcode($returnrule['productcode']),
                    'amount' => 0 - $returnrule['amount'] // Negatief aantal
                );
            }
            $neworder = $this->picqerclient->addOrder($returnorder);
            if ($neworder['success']) {
                $closedorder = $this->picqerclient->closeOrder($neworder['data']['idorder']);
                if ($closedorder['success']) {
                    $this->picqerclient->pickallPicklist($closedorder['data']['picklists'][0]['idpicklist']);
                }
            }
        }
    }

    public function closePicklistInPicqer($idpicklist)
    {
        $this->picqerclient->pickallPicklist($idpicklist);
    }

    public function getPicklistFromPicklistid($picklistid)
    {
        $picklist = $this->picqerclient->getPicklistByPicklistid($picklistid);
        if (isset($picklist['data']['idpicklist']) && !empty($picklist['data']['idpicklist'])) {
            return $picklist;
        }

        return null;
    }

    public function getIdproductFromProductcode($productcode)
    {
        $product = $this->picqerclient->getProductByProductcode($productcode);
        if (isset($product['data']['idproduct']) && !empty($product['data']['idproduct'])) {
            return $product['data']['idproduct'];
        }

        return null;
    }

    public function moveReturnFile($filename)
    {
        $this->ftpserver->rename($this->config['returns-directory'] . '/' . $filename, $this->config['returns-processed-directory'] . '/' . $filename . '-' . date('YmdHis') . '-' . rand(1000, 9999));
    }

}