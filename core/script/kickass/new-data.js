var Crawler = require("crawler");
var url = require('url');
var fs = require('fs');
var parse = require('csv-parse');
var slug = require('slug');
var MongoClient = require('mongodb').MongoClient;

var url = 'mongodb://localhost:27017/torrents';

var c = new Crawler({
    maxConnections : 10,
    // This will be called for each crawled page
    callback : function (error, result, $) {
        var d = new Date();
        var title = $("h1 span").html();
        var data = {
            "slug": (title) ? slug(title).toLowerCase() : "",
            "title": title,
            "description" : $("#summary > div:first-child").html(),
            "downloadLink": ($("a[title='Download verified torrent file']")) ? $("a[title='Download verified torrent file']").attr("href") : "",
            "size": $(".torFileSize").html(),
            "seeds": $("strong[itemprop='seeders']").html(),
            "leechs": $("strong[itemprop='leechers']").html(),
            "url": result.uri,
            "tracker": "kickass",
            "category": ""
        }
        MongoClient.connect(url, function(err, db) {
            insertDocuments(db, data);
            fs.writeFile("logs/"+d.getFullYear()+ d.getMonth()+d.getDate()+"-kickass.log", d.toDateString() + " " + d.toLocaleTimeString() + " - " + result.uri + "\n", null);
            db.close();
        });
    }
});

var parser = parse({delimiter: '\n'})
var rs = fs.createReadStream('data/kickass/kickass_links.csv');
rs.on('data', function(chunk) {
    console.log(chunk.toString())
        /*parse(chunk.toString(), null, function(err, output){
            c.queue(output);
        });*/
})

rs.on('error', function(err) {
    console.log(err);
});

rs.on('end', function(){
    fs.writeFile("logs/"+d.getFullYear()+ d.getMonth()+d.getDate()+"-kickass.log", d.toDateString() + " " + d.toLocaleTimeString() + " END...\n", null);
})

var insertDocuments = function(db, data) {
    var collection = db.collection('kickass');
    collection.insert(data, function(){});
}