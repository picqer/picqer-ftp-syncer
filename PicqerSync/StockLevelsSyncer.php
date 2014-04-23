<?php
namespace PicqerSync;

class StockLevelsSyncer {

    protected $picqerclient;
    protected $ftpserver;
    protected $config;

    public function __construct($picqerclient, $ftpserver, $config)
    {
        $this->picqerclient = $picqerclient;
        $this->ftpserver = $ftpserver;
        $this->config = $config;
    }

    public function syncStockLevels()
    {
        $files = $this->getStockUpdateFiles();
        if (count($files) > 0) {
            foreach ($files as $filename => $data) {
                $this->processStockUpdateFile($data);
                $this->moveStockUpdateFile($filename);
            }
        }
    }

    public function getStockUpdateFiles()
    {
        $files = array();
        $itemsOnFtp = $this->ftpserver->listContents($this->config['stockupdates-directory']);
        foreach ($itemsOnFtp as $item) {
            if ($item['type'] == 'file') {
                $files[$item['basename']] = $this->ftpserver->read($item['path']);
            }
        }

        logThis(count($files) . ' stock update files found');

        return $files;
    }

    public function processStockUpdateFile($data)
    {
        $rules = explode(PHP_EOL, $data);
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (!empty($rule)) {
                $ruledata = $this->parseStockUpdateRule($rule);
                $this->processStockUpdateRule($ruledata);
            }
        }
    }

    public function parseStockUpdateRule($rule)
    {
        // 20120009;140
        $fields = explode(';', $rule);
        if (isset($fields[0]) && isset($fields[1])) {
            return array(
                'productcode' => trim($fields[0], '"'),
                'stock' => trim($fields[1], '"')
            );
        } else {
            return null;
        }
    }

    public function processStockUpdateRule($stockupdatedata)
    {
        $product = $this->picqerclient->getProductByProductcode($stockupdatedata['productcode']);
        if ( ! is_null($product)) {
            $stock = $this->picqerclient->getProductStockForWarehouse($product['data']['idproduct'], $this->config['picqer-idwarehouse']);
            if ($stock['data']['stock'] != $stockupdatedata['stock']) {
                $this->picqerclient->updateProductStockForWarehouse($product['data']['idproduct'], $this->config['picqer-idwarehouse'], array('amount' => $stockupdatedata['stock'], 'reason' => 'Updated by StockSyncer'));
            }
        }
    }

    public function moveStockUpdateFile($filename)
    {
        $this->ftpserver->rename($this->config['stockupdates-directory'] . '/' . $filename, $this->config['stockupdates-processed-directory'] . '/' . $filename . '-' . date('YmdHis') . '-' . rand(1000, 9999));
    }

}