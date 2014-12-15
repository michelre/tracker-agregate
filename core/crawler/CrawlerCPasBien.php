<?php
/**
 * Created by PhpStorm.
 * User: remimichel
 * Date: 13/12/14
 * Time: 20:36
 */

require_once("libs/php-crawl/PHPCrawler.class.php");
require_once('libs/log4php/main/php/Logger.php');
require_once('libs/php-query/phpQuery.php');

class CrawlerCPasBien extends PHPCrawler{
    protected  $db;
    protected  $updateData;
    protected  $logger;

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
        $crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png|torrent|exe|css|js)$# i");
        $crawler->enableCookieHandling(true);
        $crawler->enableResumption();
        (!file_exists("logs/crawler-process-id.tmp")) ? file_put_contents("logs/crawler-process-id.tmp", $crawler->getCrawlerId()) :  $crawler->resume(file_get_contents("logs/crawler-process-id.tmp"));
        $crawler->goMultiProcessed(5);
        $crawler->displayReport($report = $crawler->getProcessReport());
        //$db->cpasbien->execute('ensureIndex({"slug":1}');
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
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";


        // Print if the content of the document was be recieved or not
        if ($DocInfo->received == true){
            $this->logger->info("Page received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);
            $doc = phpQuery::newDocumentHTML($DocInfo->content);
            if($doc["#telecharger"] != ""){
                $data = array('slug' => $this->slugify($doc[".h2fiche > a"]->html()), 'title' => $doc['.h2fiche > a']->html(),
                    'description' => $doc['#textefiche > p:last']->html(), 'downloadLink' => $doc['#telecharger']->attr('href'),
                    'size' => $doc["#infosficher span:nth-child(2)"]->html(), 'seeds' => $doc["#infosficher .seed_ok"]->html(),
                    'leechs' => $doc["#infosficher span:last"]->html(), 'url' => $DocInfo->url, 'tracker' => 'cpasbien',
                    'category' => $doc["#ariane a:nth-child(2)"]->html());
                if($this->updateData){
                    $this->db->cpasbien->update(array("slug" => $data["slug"]), $data);
                }else{
                    $this->db->cpasbien->insert($data);
                }
            }
        }
        else
            $this->logger->info("Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);

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
        $date = new DateTime();
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
                        'file' => 'logs/' . $date->format("Ymd") . '-' . $tracker . '-' . $type . '.log',
                        'append' => true
                    )
                )
            )
        ));
        $this->logger = Logger::getLogger("main");
    }
} 