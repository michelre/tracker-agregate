<?php

require_once(__DIR__.'/../../../libs/php-query/phpQuery.php');


function slugify($str, $replace=array(), $delimiter='-') {
    if( !empty($replace) ) {
        $str = str_replace((array)$replace, ' ', $str);
    }

    $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '', $clean);
    $clean = strtolower(trim($clean, '-'));
    $clean = preg_replace("/[\/_|+ -]+/", $delimiter, $clean);

    return $clean;
}


function crawl($file, $db)
{
    $f = fopen($file, "r");
    while(($data = fgetcsv($f)) !== FALSE){
        list($id, $title, $category, $url, $downloadLink) = explode("|", $data[0]);
        $ch = curl_init($url);
        $header[0] = "Accept: text/xml,application/xml,application/xhtml+xml,";
        $header[0] .= "text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: ";
        curl_setopt($ch, CURLOPT_USERAGENT, 'Googlebot/2.1 (+http://www.google.com/bot.html)');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_REFERER, 'http://www.google.com');
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $res = curl_exec ($ch);
        $doc = phpQuery::newDocumentHTML($res);
        preg_match("/Size:(.*)<span>(.*)<\/span>/", $doc[".folderopen"], $sizeMatches);
        preg_match("/^(.*)<br>/", trim($doc['#summary > div:nth-child(1)']->html()), $descriptionMatch);
        $data = array('slug' => slugify($title), 'title' => $title,
            'description' => $descriptionMatch[1], 'downloadLink' => $downloadLink,
            'size' => $sizeMatches[1] . ' ' . $sizeMatches[2], 'seeds' => $doc[".seedBlock strong"]->html(),
            'leechs' => $doc[".leechBlock strong"]->html(), 'url' => $url, 'tracker' => 'kickass',
            'category' => $category);

        $db->kickass->insert($data);
    }

}

$mongo = new MongoClient();
$db = $mongo->selectDB("torrents");
crawl($argv[1], $db);
