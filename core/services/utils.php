<?php


function addUsedProxy($proxy){
    $mongo = new MongoClient();
    $db = $mongo->proxy;
    $db->omg->insert($proxy);
}

function isUsed($proxy){
    $db = new MongoClient();
    $collection = $db->proxy->omg;
    preg_match("#^(.*):(.*)$#", $proxy, $proxyConst);
    return $collection->count(array("ip" => $proxyConst["ip"])) > 0;
}

function proxyWorks($ip, $port, $website){
    $ch = curl_init($website);
    curl_setopt($ch, CURLOPT_PROXY, $ip.':'.$port);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_exec($ch);
    $info = curl_getinfo($ch);

    return ($info['http_code'] == 200);
}

function retrieveCorrectProxy($proxies, $webiste){
    $date = new DateTime();
    foreach($proxies as $proxy){
        preg_match("#^(.*):(.*)$#", $proxy, $proxyConst);
        $p = array("ip" => $proxyConst[1], "port" => $proxyConst[2], "date" => $date->format("Ymd-H:i"));
        if(proxyWorks($p["ip"], $p["port"], $webiste)){
            return $p;
        }
    }
    return null;
}

function getProxy($website){
    $resource = curl_init("http://proxy-list.org/french/index.php");
    curl_setopt($resource, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($resource);
    $doc = phpQuery::newDocumentHTML($response);
    $ul = $doc["#proxy-table .table ul"];
    $proxies = pq($ul)->map(function($e){
        $p = pq($e)->find('.proxy')->html();
        if(strcmp(pq($e)->find('li.https')->html(), "HTTP") == 0 && !isUsed($p)){
            return $p;
        }
    })->get();
    $proxy = retrieveCorrectProxy($proxies, $website);
    addUsedProxy($proxy);

    return $proxy;
}
