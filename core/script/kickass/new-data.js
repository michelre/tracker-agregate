var Crawler = require("crawler");
var url = require('url');
var fs = require('fs');
var parse = require('csv-parse');
var slug = require('slug')

var c = new Crawler({
    maxConnections : 10,
    // This will be called for each crawled page
    callback : function (error, result, $) {
        var d = new Date();
        var title = $("h1 span").html();
        var data = {
            "slug": slug(title).toLowerCase(),
            "title": title,
            "description" : $("#summary > div:first-child").html(),
            "downloadLink": $("a[title='Download verified torrent file']").attr("href"),
            "size": $(".torFileSize").html(),
            "seeds": $("strong[itemprop='seeders']").html(),
            "leechs": $("strong[itemprop='leechers']").html(),
            "url": result.uri,
            "tracker": "kickass",
            "category": ""
        }
        fs.writeFile("logs/"+d.getFullYear()+ d.getMonth()+d.getDate()+"-kickass.log", d.toDateString() + " " + d.toLocaleTimeString() + " - " + result.uri + "\n", null);
    }
});

var links = [];

var parser = parse({delimiter: '\n'})
var rs = fs.createReadStream('data/kickass/kickass_links.csv');
rs.on('data', function(chunk) {
    parse(chunk.toString(), null, function(err, output){
        c.queue(output);
    });
})

rs.on('error', function(err) {
    console.log(err);
});

rs.on('end', function(){
    fs.writeFile("logs/"+d.getFullYear()+ d.getMonth()+d.getDate()+"-kickass.log", d.toDateString() + " " + d.toLocaleTimeString() + " END...\n", null);
})