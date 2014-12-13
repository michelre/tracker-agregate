<?php

include('core/crawler/CrawlerCPasBien.php');

function crawl($i, $nbRows, $db)
{
    $collection = new MongoCollection($db, 'cpasbien');
    $cursor = $collection->find(array(), array('url' => true))->limit($nbRows)->skip($i * $nbRows);
    CrawlerCPasBien::crawlUpdate($db, $cursor);
    exit;
}

function nbRowsTotal($db)
{
    $result = $db->execute("return db.cpasbien.count(
    );");
    return $result["retval"];
}

function launchProcesses($total, $db)
{
    $nbProcesses = 10;
    $nbRowsPerProcess = round($total / $nbProcesses, 0, PHP_ROUND_HALF_UP);
    for ($i = 0; $i < $nbProcesses; $i++) {
        $pid = pcntl_fork();
        if (!$pid) {
            crawl($i, $nbRowsPerProcess, $db);
        }
    }
}

$mongo = new MongoClient();
$db = $mongo->selectDB("torrents");
$total = nbRowsTotal($db);
launchProcesses($total, $db);