<?php

require_once('core/crawler/CrawlerKickass.php');
require_once('core/services/utils.php');
//require_once('../../../libs/php-query/phpQuery.php');
//require_once('../../../core/services/utils.php');

//$proxy = getProxy("http://www.omgtorrent.com");
//if($proxy)
    CrawlerKickass::crawlNew("https://www.kickass.so", "kickass", $proxy["ip"], $proxy["port"]);