<?php

require_once(__DIR__."/../../libs/php-crawl/PHPCrawler.class.php");
require_once(__DIR__.'/../../libs/log4php/main/php/Logger.php');
require_once(__DIR__.'/../../libs/php-query/phpQuery.php');

class CrawlerSmartorrent extends PHPCrawler{
    protected  $db;
    protected  $updateData;
    protected  $logger;
    protected  $date;
    protected  $processId;

    private function displayReport($report){
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";

        $this->logger->info("Summary:".$lb);
        $this->logger->info("Links followed: ".$report->links_followed.$lb);
        $this->logger->info("Documents received: ".$report->files_received.$lb);
        $this->logger->info("Bytes received: ".$report->bytes_received." bytes".$lb);
        $this->logger->info("Process runtime: ".$report->process_runtime." sec".$lb);
        unlink(__DIR__."/../../logs/crawler-process-id-smartorrent.tmp");
    }

    public static function crawlNew($baseURL, $tracker){
        $crawler = new self();
        error_reporting(E_ALL);
        $crawler->setLogger("new-data", $tracker);
        $crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_SQLITE);
        $crawler->setURL("http://smartorrent.com/torrent/Torrent-Django-Reinhardt--Tr?sors--CD-4--[-FLAC-]/238623/");
        $crawler->addContentTypeReceiveRule("#text/html#");
        $crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png|torrent|exe|css|js|php)$# i");
        $crawler->addURLFilterRule("#http:\/\/smartorrent.com\/dmca\/# i");
        $crawler->addURLFilterRule("#https:\/\/smartorrent.com\/user\/# i");
        $crawler->addURLFilterRule("#https:\/\/smartorrent.com\/forum\/# i");
        $crawler->addURLFilterRule("#https:\/\/smartorrent.com\/faq\/# i");
        $crawler->addURLFilterRule("#fichiers\/$# i");
        $crawler->addURLFilterRule("#similaires\/$# i");
        $crawler->addURLFilterRule("#nfo\/$# i");
        $crawler->addURLFilterRule("#commentaires\/$# i");
        $crawler->enableResumption();
        (!file_exists(__DIR__."/../../logs/crawler-process-id-smartorrent.tmp")) ? file_put_contents(__DIR__."/../../logs/crawler-process-id-smartorrent.tmp", $crawler->getCrawlerId()) :  $crawler->resume(file_get_contents(__DIR__."/../../logs/crawler-process-id-smartorrent.tmp"));
        $crawler->goMultiProcessed(3);
        $report = $crawler->getProcessReport();
        if(!$report->memory_peak_usage){
            $crawler->displayReport($report);
            return true;
        }
        return false;
    }

    public static function crawlUpdate($db, $cursor, $tracker){
        $crawler = new self();
        $crawler->setLogger("update-data", $tracker);
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

    function handleDocumentInfo($DocInfo)
    {
        $DocInfo->url = iconv("ISO-8859-1", "UTF-8", $DocInfo->url);
        $DocInfo->content = iconv("ISO-8859-1", "UTF-8", $DocInfo->content);
        $date = new DateTime();
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";

        // Print if the content of the document was be recieved or not
        if ($DocInfo->received == true && (int)$DocInfo->http_status_code == 200 ){
            $this->logger->info($date->format("Y-m-d-H:i") . "-Page received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);
            $doc = phpQuery::newDocumentHTML($DocInfo->content);
            if($doc["a.telechargergreen"] != ""){
                $data = $this->retrieveData($doc, $DocInfo->url);
                if($this->updateData){
                    $this->db->smartorrent->update(array("slug" => $data["slug"]), $data);
                }else{
                    $this->db->smartorrent->insert($data);
                }
            }
        }
        else if((int)$DocInfo->http_status_code == 301){
            $this->logger->info($date->format("Y-m-d-H:i") . "-Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);
        }
        else
            $this->logger->info($date->format("Y-m-d-H:i") . "-Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);

        flush();
    }

    function retrieveData($doc, $url){
        $category = $doc[".fichetorrent tr:nth-child(2) > td > a"]->html();
        preg_match("/<\/strong>(.*)<\/td>$/", trim($doc[".fichetorrent tr:nth-child(7)"]->html()), $size);
        preg_match("/<\/strong>(.*)<\/td>$/", trim($doc[".fichetorrent tr:nth-child(9)"]->html()), $seedersRow);
        list($seedersText, $leechersText, $completeText) = explode('-', $seedersRow[1]);
        list($nbSeeders, $text) = explode(" ", trim($seedersText));
        list($nbLeechers, $text) = explode(" ", trim($leechersText));
        $data = array('slug' => $this->slugify($doc[".fichetorrent h1"]->html()), 'title' => $doc[".fichetorrent h1"]->html(),
            'description' => "", 'downloadLink' => $doc['a.telechargergreen']->attr('href'),
            'size' => $size[1], 'seeds' => $nbSeeders,
            'leechs' => $nbLeechers, 'url' => $url, 'tracker' => 'smartorrent',
            'category' => $category);
        return $data;
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

    public function setDb($db){
        $this->db = $db;
    }

    public function setUpdateData($updateData){
        $this->updateData = $updateData;
    }

    public function setLogger($type, $tracker){
        $this->date = new DateTime();
        Logger::configure(array(
            'rootLogger' => array(
                'appenders' => array('default'),
            ),
            'appenders' => array(
                'default' => array(
                    'class' => 'LoggerAppenderFile',
                    'layout' => array(
                        'class' => 'LoggerLayoutSimple'
                    ),
                    'params' => array(
                        'file' => __DIR__.'/../../logs/' . $this->date->format("Ymd") . '-' . $tracker . '-' . $type . '.log',
                        'append' => true
                    )
                )
            )
        ));
        $this->logger = Logger::getLogger("main");
    }
} 
