picqer-ftp-syncer
=================

PHP tool to push [Picqer](http://picqer.com) picklists to external FTP and retrieve order statuses and inbound goods.

With this tool, you can sync data between your Picqer account (via the API) and a FTP server containing CSV files.

This tool is created to send orders to [Montapacking](http://www.montapacking.nl/) and get the results back and push it to Picqer. But with some modification you can use it for other FTP services as well.

Feel free to use this codebase for anything, it is MIT licenced.

## Working
This script is doing the following steps:
- Get new picklists from Picqer and creates CSV orders for it on the FTP server.
- Gets track and trace information in CSV from completed orders from the FTP server and pushes the track and trace info to Picqer. This will attach a shipping to the picklist.
- Process returns to the warehouse by getting the CSV files from the FTP and create new return orders into Picqer (and process the orders).
- Process all inbound goods to the warehouse by getting the CSV of inbound goods from the CSV and match them to the open purchase orders in Picqer.
- Finally it gets the stock levels from the warehouse by CSV and sets the stock levels accordingly in Picqer, in case we missed an inbound or other mistakes in the warehouse.

## How to start
- Download project
- Install composer and run `composer install`
- Create a config.php file, based on the [config-dist.php](https://github.com/picqer/picqer-ftp-syncer/blob/master/config-dist.php) example
- Make data directory writeable
- Run app.php and the syncing starts
