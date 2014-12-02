<?php

include('core/crawler/CrawlerCPasBien.php');

$mongo = new MongoClient();
$db = $mongo->selectDB("torrents");
CrawlerCPasBien::crawlNew($db);

