<?php

// Inculde the phpcrawl-mainclass 
require_once("CrawlerTrackers.php");
require_once('libs/php-query/phpQuery.php');


// Extend the class and override the handleDocumentInfo()-method  
class CrawlerCPasBien extends CrawlerTrackers
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
            if($doc["#telecharger"] != ""){
                $data = array('slug' => $this->slugify($doc[".h2fiche > a"]->html()), 'title' => $doc['.h2fiche > a']->html(),
                    'description' => $doc['#textefiche > p:last']->html(), 'downloadLink' => $doc['#telecharger']->attr('href'),
                    'size' => $doc["#infosficher span:nth-child(2)"]->html(), 'seeds' => $doc["#infosficher .seed_ok"]->html(),
                    'leechs' => $doc["#infosficher span:last"]->html(), 'url' => $DocInfo->url, 'tracker' => 'cpasbien',
                    'category' => $doc["#ariane a:nth-child(2)"]->html());
                if($this->updateData){
                    $this->db->cpasbien->update(array("slug" => $data["slug"]), $data);
                }else{
                    var_dump($data);
                    $this->db->cpasbien->insert($data);
                }
            }
        }
        else
            $this->logger->info("Content not received: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb);

        flush();
    }

}