<?php

ini_set('display_errors', true);

require 'config.php';
require 'vendor/autoload.php';

function dd($content) {
    var_dump($content);
    die();
}

function logThis($message) {
    echo $message . PHP_EOL;
}

use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Adapter\Ftp as FtpAdapter;
use Picqer\Api\Client as PicqerClient;

// Local filesystem
$filesystem = new Filesystem(new LocalAdapter(__DIR__));

// FTP connection
$ftpserver = new Filesystem(new FtpAdapter(array(
    'host' => $config['ftp-host'],
    'username' => $config['ftp-username'],
    'password' => $config['ftp-password'],
)));

// Picqer connection
$picqerclient = new PicqerClient($config['picqer-company'], $config['picqer-apikey']);

// Get data
$datakeeper = new PicqerSync\DataKeeper($filesystem);
$data = $datakeeper->getData();

// Create FTP orders from Picqer picklists
logThis("Create orders on FTP");
$orderCreator = new PicqerSync\PicklistToOrderConverter($picqerclient, $ftpserver, $data, $config);
$orderCreator->convertPicklistsToOrders();
$processedPicklists = $orderCreator->getProcessedPicklists();

foreach ($processedPicklists as $processedPicklist) {
    $data['picklists'][] = $processedPicklist;
}

// Process picklists who are completed
logThis("Process track trace from FTP");
$completedOrdersProcessor = new PicqerSync\CompletedOrdersProcessor($picqerclient, $ftpserver, $config);
$completedOrdersProcessor->processCompletedOrders();

// Process returns from FTP
logThis("Process returns from FTP");
$returnsProcessor = new PicqerSync\ReturnsProcessor($picqerclient, $ftpserver, $config);
$returnsProcessor->processReturns();

// Process inbound products from FTP
logThis("Process inbound products from FTP");
$inboundsProcessor = new PicqerSync\InboundsProcessor($picqerclient, $ftpserver, $config);
$inboundsProcessor->processInbounds();

// Sync stock levels from FTP
logThis("Process stock updates from FTP");
$stockLevelsSyncer = new PicqerSync\StockLevelsSyncer($picqerclient, $ftpserver, $config);
$stockLevelsSyncer->syncStockLevels();

// Let Picqer process the backorders
logThis("Backorders being processed");
$backordersProcessor = new PicqerSync\BackordersProcessor($picqerclient);
$backordersProcessor->processBackorders();

// Save changed data
$datakeeper->saveData($data);

echo 'DONE';