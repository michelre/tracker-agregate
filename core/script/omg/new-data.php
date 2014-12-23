<?php

require_once(__DIR__.'/../../crawler/CrawlerOMG.php');
require_once(__DIR__.'/../../services/utils.php');

$proxy = getProxy("http://www.omgtorrent.com");
if($proxy)
    CrawlerOMG::crawlNew("http://www.omgtorrent.com", "omg", $proxy["ip"], $proxy["port"]);
else
    exit(-1);