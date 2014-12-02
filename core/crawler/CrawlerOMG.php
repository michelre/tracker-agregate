<?php

// Inculde the phpcrawl-mainclass 
include("libs/PHPCrawler.class.php");
require('libs/phpQuery.php');

// Extend the class and override the handleDocumentInfo()-method  
class CrawlerOMG extends PHPCrawler
{
    private $db;

    function initChildProcess(){
        $mongo = new MongoClient();
        $this->db = $mongo->selectDB("torrents");
    }

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

    function handleDocumentInfo($DocInfo)
    {
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";

        // Print the URL and the HTTP-status-Code
        echo "Page requested: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb;

        // Print the refering URL
        echo "Referer-page: ".$DocInfo->referer_url.$lb;

        // Print if the content of the document was be recieved or not
        if ($DocInfo->received == true){
            $doc = phpQuery::newDocumentHTML($DocInfo->content);
            if($doc["#lien_dl"] != ""){
                preg_match("/Taille :<\/strong> (.*)<br><br><img/", $doc[".sl"]->html(), $matches);
                $data = array('slug' => $this->slugify($doc["#corps h1"]->html()), 'title' => $doc["#corps h1"]->html(),
                    'description' => $doc['.infos_fiche p']->html(), 'downloadLink' => $doc['#lien_dl']->attr('href'),
                    'size' => $matches[1], 'seeds' => $doc[".sources strong"]->html(),
                    'leechs' => $doc[".clients strong"]->html(), 'url' => $DocInfo->referer_url.$lb);
                $this->db->omg->insert($data);
            }
        }
            //echo "Content received: ".$DocInfo->bytes_received." bytes".$lb;
        else
            echo "Content not received".$lb;

        // Now you should do something with the content of the actual
        // received page or file ($DocInfo->source), we skip it in this example

        echo $lb;

        flush();
    }
}

// Now, create a instance of your class, define the behaviour 
// of the crawler (see class-reference for more options and details) 
// and start the crawling-process.

$crawler = new CrawlerOMG();

$crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_MEMORY);
// URL to crawl 
$crawler->setURL("http://www.omgtorrent.com/");

// Only receive content of files with content-type "text/html" 
$crawler->addContentTypeReceiveRule("#text/html#");

// Ignore links to pictures, dont even request pictures 
$crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png|torrent|exe|css)$# i");

// Store and send cookie-data like a browser does 
$crawler->enableCookieHandling(true);

// Set the traffic-limit to 1 MB (in bytes, 
// for testing we dont want to "suck" the whole site) 
//$crawler->setTrafficLimit(1000 * 1024);

// Thats enough, now here we go 
$crawler->go();

// At the end, after the process is finished, we print a short 
// report (see method getProcessReport() for more information) 
$report = $crawler->getProcessReport();

if (PHP_SAPI == "cli") $lb = "\n";
else $lb = "<br />";

echo "Summary:".$lb;
echo "Links followed: ".$report->links_followed.$lb;
echo "Documents received: ".$report->files_received.$lb;
echo "Bytes received: ".$report->bytes_received." bytes".$lb;
echo "Process runtime: ".$report->process_runtime." sec".$lb;  