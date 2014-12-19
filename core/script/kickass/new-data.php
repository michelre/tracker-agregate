<?php

require_once(__DIR__.'core/crawler/CrawlerKickass.php');
require_once(__DIR__.'core/services/utils.php');

CrawlerKickass::crawlNew("https://www.kickass.so", "kickass");