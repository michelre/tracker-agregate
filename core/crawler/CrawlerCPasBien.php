<?php

// Inculde the phpcrawl-mainclass 
include("libs/PHPCrawler.class.php");
require('libs/phpQuery.php');

// Extend the class and override the handleDocumentInfo()-method  
class CrawlerCPasBien extends PHPCrawler
{
    private $db;
    private $updateData;

    private function displayReport($report){
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";

        echo "Summary:".$lb;
        echo "Links followed: ".$report->links_followed.$lb;
        echo "Documents received: ".$report->files_received.$lb;
        echo "Bytes received: ".$report->bytes_received." bytes".$lb;
        echo "Process runtime: ".$report->process_runtime." sec".$lb;
    }

    public static function crawlNew($db){
        $crawler = new self();
        $crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_MEMORY);
        $crawler->setURL("http://www.cpasbien.pe");
        $crawler->addContentTypeReceiveRule("#text/html#");
        $crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png|torrent|exe|css|js)$# i");
        $crawler->enableCookieHandling(true);
        //$crawler->goMultiProcessed(5);
        $crawler->displayReport($report = $crawler->getProcessReport());
        //$db->cpasbien->execute('ensureIndex({"slug":1}');
    }
    public static function crawlUpdate($db, $cursor){
        $crawler = new self();
        $crawler->setUpdateData(true);
        $crawler->setDb($db);
        $crawler->addContentTypeReceiveRule("#text/html#");
        $crawler->addURLFilterRule("#\.(.*)$# i");
        $crawler->enableCookieHandling(true);
        foreach($cursor as $obj){
            $crawler->setURL($obj["url"]);
            $crawler->go();
            $report = $crawler->getProcessReport();
            $crawler->displayReport($report);
        }
    }

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
            if($doc["#telecharger"] != ""){
                $data = array('slug' => $this->slugify($doc[".h2fiche > a"]->html()), 'title' => $doc['.h2fiche > a']->html(),
                    'description' => $doc['#textefiche > p:last']->html(), 'downloadLink' => $doc['#telecharger']->attr('href'),
                    'size' => $doc["#infosficher span:nth-child(2)"]->html(), 'seeds' => $doc["#infosficher .seed_ok"]->html(),
                    'leechs' => $doc["#infosficher span:last"]->html(), 'url' => $DocInfo->url);

                if($this->updateData){
                    $this->db->cpasbien->update(array("slug" => $data["slug"]), $data);
                }else{
                    $this->db->cpasbien->insert($data);
                }
            }
        }
        else
            echo "Content not received".$lb;
        echo $lb;

        flush();
    }

    public function setDb($db){
        $this->db = $db;
    }

    public function setUpdateData($updateData){
        $this->updateData = $updateData;
    }
}