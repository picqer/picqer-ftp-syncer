<?php
namespace PicqerSync;

class BackordersProcessor {

    protected $picqerclient;

    public function __construct($picqerclient)
    {
        $this->picqerclient = $picqerclient;
    }

    public function processBackorders()
    {
        logThis('Processing backorders');
        $this->picqerclient->processBackorders();
        logThis('Backorders processed');
    }

}