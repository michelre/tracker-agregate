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

function getProxy(){
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

    $proxy = array_pop($proxies);

    preg_match("#^(.*):(.*)$#", $proxy, $proxyConst);
    $proxyUsed = array("ip" => $proxyConst[1], "port" => $proxyConst[2]);
    addUsedProxy($proxyUsed);

    return $proxyUsed;
}