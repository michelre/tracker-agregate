<?php

require_once(__DIR__.'/../../crawler/CrawlerSmartorrent.php');


if(!CrawlerSmartorrent::crawlNew("http://smartorrent.com", "smartorrent")){
    exit(-1);
};
