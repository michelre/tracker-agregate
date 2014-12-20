<?php
/**
 * Created by PhpStorm.
 * User: remimichel
 * Date: 13/12/14
 * Time: 20:36
 */

require_once(__DIR__."/../../libs/php-crawl/PHPCrawler.class.php");
require_once(__DIR__.'/../../libs/log4php/main/php/Logger.php');
require_once(__DIR__.'/../../libs/php-query/phpQuery.php');

class CrawlerKickass extends PHPCrawler{
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
        unlink("logs/crawler-process-id.tmp");
    }

    public static function crawlNew($baseURL, $tracker){
        $crawler = new self();
        error_reporting(E_ALL);
        $crawler->setLogger("new-data", $tracker);
        $crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_SQLITE);
        $crawler->setURL($baseURL);
        $crawler->addContentTypeReceiveRule("#text/html#");
        $crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png|torrent|exe|css|js|php)$# i");
        $crawler->addURLFilterRule("#https:\/\/kickass.so\/blog\/# i");
        $crawler->addURLFilterRule("#https:\/\/kickass.so\/user\/# i");
        $crawler->addURLFilterRule("#https:\/\/kickass.so\/community\/# i");
        $crawler->addURLFilterRule("#https:\/\/kickass.so\/faq\/# i");
        $crawler->addURLFilterRule("#https:\/\/kickass.so\/auth\/# i");

        $crawler->enableCookieHandling(true);
        $crawler->enableResumption();
        (!file_exists(__DIR__."/../../logs/crawler-process-id-kickass.tmp")) ? file_put_contents(__DIR__."/../../logs/crawler-process-id-kickass.tmp", $crawler->getCrawlerId()) :  $crawler->resume(file_get_contents(__DIR__."/../../logs/crawler-process-id-kickass.tmp"));
        $crawler->goMultiProcessed(7);
        $crawler->displayReport($report = $crawler->getProcessReport());
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
        $date = new DateTime();
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";

        // Print if the content of the document was be recieved or not
        if ($DocInfo->received == true && (int)$DocInfo->http_status_code == 200 ){
            $this->logger->info($date->format("Y-m-d-H:i") . "-Page received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);
            $doc = phpQuery::newDocumentHTML($DocInfo->content);
            if($doc[".verifTorrentButton"] != ""){
                $category = rtrim($doc[".dataList ul:nth-child(1) > li:nth-child(1) > strong"]->html(), ":");
                preg_match("/Size:(.*)<span>(.*)<\/span>/", $doc[".folderopen"], $sizeMatches);
                preg_match("/^(.*)<br>/", trim($doc['#summary > div:nth-child(1)']->html()), $descriptionMatch);


                $data = array('slug' => $this->slugify($doc[".novertmarg > a > span"]->html()), 'title' => $doc[".novertmarg > a > span"]->html(),
                    'description' => $descriptionMatch[1], 'downloadLink' => $doc['a.verifTorrentButton']->attr('href'),
                    'size' => $sizeMatches[1] . ' ' . $sizeMatches[2], 'seeds' => $doc[".seedBlock strong"]->html(),
                    'leechs' => $doc[".leechBlock strong"]->html(), 'url' => $DocInfo->url, 'tracker' => 'kickass',
                    'category' => $category);
                if($this->updateData){
                    $this->db->kickass->update(array("slug" => $data["slug"]), $data);
                }else{
                    $this->db->kickass->insert($data);
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
