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

class CrawlerOMG extends PHPCrawler{
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

    public static function crawlNew($baseURL, $tracker, $proxyURL, $proxyPort){
        $crawler = new self();
        error_reporting(E_ALL);
        $crawler->setLogger("new-data", $tracker);
        $crawler->setUrlCacheType(PHPCrawlerUrlCacheTypes::URLCACHE_SQLITE);
        $crawler->setURL($baseURL);
        $crawler->setRequestDelay(60/100);
	$crawler->setProxy($proxyURL, $proxyPort);
        $crawler->addContentTypeReceiveRule("#text/html#");
        $crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png|torrent|exe|css|js|php)$# i");
        //ignore forum topics
        $crawler->addURLFilterRule("#\.php\?pid=[0-9]*$# i");
        //ignore download links
        $crawler->addURLFilterRule("#\.php\?id=[0-9]*$# i");
        $crawler->enableCookieHandling(true);
        $crawler->enableResumption();
        (!file_exists("logs/crawler-process-id.tmp")) ? file_put_contents("logs/crawler-process-id.tmp", $crawler->getCrawlerId()) :  $crawler->resume(file_get_contents("logs/crawler-process-id.tmp"));
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
            if($doc["#lien_dl"] != ""){
                preg_match("/Taille :<\/strong> (.*)<br><br><img/", $doc[".sl"]->html(), $matches);
                $data = array('slug' => $this->slugify($doc["#corps h1"]->html()), 'title' => $doc["#corps h1"]->html(),
                    'description' => $doc['.infos_fiche p']->html(), 'downloadLink' => $doc['#lien_dl']->attr('href'),
                    'size' => $matches[1], 'seeds' => $doc[".sources strong"]->html(),
                    'leechs' => $doc[".clients strong"]->html(), 'url' => $DocInfo->referer_url.$lb, 'tracker' => 'omg',
                    'category' => $doc["#breadcrumb div:nth-child(5) > a > span"]->html());
                if($this->updateData){
                    $this->db->omg->update(array("slug" => $data["slug"]), $data);
                }else{
                    $this->db->omg->insert($data);
                }
            }
        }
        else if((int)$DocInfo->http_status_code == 301){
            $this->logger->info($date->format("Y-m-d-H:i") . "-Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);
            //$this->killallProcesses();
        }
        else
            $this->logger->info($date->format("Y-m-d-H:i") . "-Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);

        flush();
    }

    private function killallProcesses(){
        exec("ps -ef | grep 'php -f'", $result);
        array_map(function($process){
            preg_match_all("/\w+/", $process, $processStatus);
            posix_kill((int)$processStatus[0][1], SIGKILL);
        }, $result);
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
                        'file' => 'logs/' . $this->date->format("Ymd") . '-' . $tracker . '-' . $type . '.log',
                        'append' => true
                    )
                )
            )
        ));
        $this->logger = Logger::getLogger("main");
    }
} 
