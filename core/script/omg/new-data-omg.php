<?php

require_once('core/crawler/CrawlerOMG.php');
require_once('core/services/utils.php');
//require_once('../../../libs/php-query/phpQuery.php');
//require_once('../../../core/services/utils.php');

function killallProcesses(){
    exec("ps -ef | grep 'php -f'", $result);
    array_map(function($process){
        preg_match_all("/\w+/", $process, $processStatus);
        posix_kill((int)$processStatus[0][1], SIGTERM);
    }, $result);
}

//killallProcesses();

$proxy = getProxy();

CrawlerOMG::crawlNew("http://www.omgtorrent.com", "omg", $proxy["ip"], $proxy["port"]);