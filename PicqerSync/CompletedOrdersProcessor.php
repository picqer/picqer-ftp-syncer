<?php
namespace PicqerSync;

class CompletedOrdersProcessor {

    protected $picqerclient;
    protected $ftpserver;
    protected $config;

    public function __construct($picqerclient, $ftpserver, $config)
    {
        $this->picqerclient = $picqerclient;
        $this->ftpserver = $ftpserver;
        $this->config = $config;
    }

    public function processCompletedOrders()
    {
        $tracktracefiles = $this->getTrackTraceFiles();
        if (count($tracktracefiles) > 0) {
            foreach ($tracktracefiles as $filename => $data) {
                $this->processTrackTraceFile($data);
                $this->moveTrackTraceFile($filename);
            }
        }
    }

    public function getTrackTraceFiles()
    {
        $files = array();
        $itemsOnFtp = $this->ftpserver->listContents($this->config['tracktrace-directory']);
        foreach ($itemsOnFtp as $item) {
            if ($item['type'] == 'file') {
                $files[$item['basename']] = $this->ftpserver->read($item['path']);
            }
        }

        logThis(count($files) . ' tracktrace files found');

        return $files;
    }

    public function processTrackTraceFile($tracktracefile)
    {
        $rules = explode(PHP_EOL, $tracktracefile);
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if (!empty($rule)) {
                $this->processTrackTraceRule($rule);
            }
        }
    }

    public function processTrackTraceRule($tracktracerule)
    {
        // 100011476;SHIPPED;3STBKX27878601;PostNL;
        $fields = explode(';', $tracktracerule);
        if (isset($fields[0]) && isset($fields[1]) && isset($fields[2]) && $fields[1] == 'SHIPPED') {
            logThis("Picklist $fields[0] found, trying to process");
            $idpicklist = $this->getIdpicklistFromPicklistid($fields[0]);
            if ( !empty($idpicklist) ) {
                $this->closePicklistInPicqer($idpicklist);
                $this->sendTrackTraceToPicqer($idpicklist, $fields[2]);
                logThis("Picklist $fields[0] processed");
            } else {
                logThis("Cannot find picklist $fields[0]");
            }
        }
    }

    public function closePicklistInPicqer($idpicklist)
    {
        $this->picqerclient->pickallPicklist($idpicklist);
    }

    public function getIdpicklistFromPicklistid($picklistid)
    {
        $picklist = $this->picqerclient->getPicklistByPicklistid($picklistid);
        if (isset($picklist['data']['idpicklist']) && !empty($picklist['data']['idpicklist'])) {
            return $picklist['data']['idpicklist'];
        }

        return null;
    }

    public function sendTrackTraceToPicqer($idpicklist, $tracktracecode)
    {
        $shipment = array(
            'idshippingprovider' => $this->config['picqer-idshippingprovider'],
            'idshippingprofile' => $this->config['picqer-idshippingprofile'],
            'extrafields' => array(
                'code' => $tracktracecode
            )
        );
        $this->picqerclient->createShipment($idpicklist, $shipment);
    }

    public function moveTrackTraceFile($filename)
    {
        $this->ftpserver->rename($this->config['tracktrace-directory'] . '/' . $filename, $this->config['tracktrace-processed-directory'] . '/' . $filename . '-' . date('YmdHis') . '-' . rand(1000, 9999));
    }

}