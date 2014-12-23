<?php

require_once(__DIR__.'/../../crawler/CrawlerOMG.php');
require_once(__DIR__.'/../../services/utils.php');

$proxy = getProxy("http://www.omgtorrent.com");
if($proxy)
    if($result = CrawlerOMG::crawlNew("http://www.omgtorrent.com", "omg", $proxy["ip"], $proxy["port"])){
        exit(-1);
    }
else
    exit(-1);