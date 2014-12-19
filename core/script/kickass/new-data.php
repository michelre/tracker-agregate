<?php

require_once(__DIR__.'../../crawler/CrawlerKickass.php');
require_once(__DIR__.'../../services/utils.php');

CrawlerKickass::crawlNew("https://www.kickass.so", "kickass");