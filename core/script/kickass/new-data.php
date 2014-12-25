<?php

require_once(__DIR__.'/../../crawler/CrawlerKickass.php');


if(!CrawlerKickass::crawlNew("https://kickass.so", "kickass")){
    exit(-1);
};
