<?php

// Inculde the phpcrawl-mainclass 
include("libs/PHPCrawler.class.php");
require('libs/phpQuery.php');

// Extend the class and override the handleDocumentInfo()-method  
class CrawlerOMG extends CrawlerTrackers
{

    function handleDocumentInfo($DocInfo)
    {
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";


        // Print if the content of the document was be recieved or not
        if ($DocInfo->received == true){
            $this->logger->info("Page received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);
            $doc = phpQuery::newDocumentHTML($DocInfo->content);
            if($doc["#lien_dl"] != ""){
                preg_match("/Taille :<\/strong> (.*)<br><br><img/", $doc[".sl"]->html(), $matches);
                $data = array('slug' => $this->slugify($doc["#corps h1"]->html()), 'title' => $doc["#corps h1"]->html(),
                    'description' => $doc['.infos_fiche p']->html(), 'downloadLink' => $doc['#lien_dl']->attr('href'),
                    'size' => $matches[1], 'seeds' => $doc[".sources strong"]->html(),
                    'leechs' => $doc[".clients strong"]->html(), 'url' => $DocInfo->referer_url.$lb, 'tracker' => 'omg');
                if($this->updateData){
                    $this->db->omg->update(array("slug" => $data["slug"]), $data);
                }else{
                    var_dump($data);
                    $this->db->omg->insert($data);
                }
            }
        }
        else
            $this->logger->info("Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);

        flush();
    }

    public function setLogger($type){
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
                        'file' => 'logs/' . $date->format("Ymd") . '-omg-' . $type . '.log',
                        'append' => true
                    )
                )
            )
        ));
        $this->logger = Logger::getLogger("main");
    }

}