<?php

require_once(__DIR__.'/../../crawler/CrawlerOMG.php');
require_once(__DIR__.'/../../services/utils.php');

$proxy = getProxy("http://www.omgtorrent.com");
if($proxy != null){
    try{
        CrawlerOMG::crawlNew("http://www.omgtorrent.com", "omg", $proxy["ip"], $proxy["port"]);
    }catch(Exception $e){
        echo "Exception while crawling";
        exit(-1);
    }
}
else{
    var_dump($proxy);
    exit(-1);
}