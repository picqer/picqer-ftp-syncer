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
        $this->picqerclient->processBackorders();
    }

}