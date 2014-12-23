<?php

require_once('core/crawler/CrawlerOMG.php');
require_once('core/services/utils.php');
//require_once('../../../libs/php-query/phpQuery.php');
//require_once('../../../core/services/utils.php');

$proxy = getProxy("http://www.omgtorrent.com");
if($proxy)
    CrawlerOMG::crawlNew("http://www.omgtorrent.com", "omg", $proxy["ip"], $proxy["port"]);
else
    exit(-1);