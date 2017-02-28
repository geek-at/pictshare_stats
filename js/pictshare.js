var hash = '';
var newviewdata = [];
var viewdata = [];
var trafficdata = [];
var fail = true;

$(document).ready(function() {
    getAllHits();
    if(window.location.hash) {
        var hash = window.location.hash.substring(1);
        renderData(hash);
    }
    else
        resetPage();
    
    window.onhashchange = function() {
        var hash = window.location.hash.substring(1);
        renderData(hash);
    };
    
    
});

function resetPage()
{
    $("#content").html('<input type="text" value="" id="hash" placeholder="enter hash here"/> <input type="button" value="render stats" onClick="renderData($(\'#hash\').val());" />');
}

function renderData(hash)
{
    fail = false;
    preparePage(hash);
    getViewCount(hash)
    getTrafficCount(hash)
    
    getLogData(hash);
    
    window.location.hash = hash;
}

function preparePage(hash)
{
    $("#content").html('<h1>Stats for '+hash+'</h1> <div id="views"></div> <div id="traffic"></div>');
}

function getViewCount(hash)
{
	$.ajax({
        type: "GET",
        url: "cache/"+hash+"/hits.txt?"+Math.random(),
        dataType: "text",
        success: function(data) {$("#views").html('<h2>Was seen '+data+' times</h2>');},
        error:  function(data) {fail = true}
     });
}

function getAllTrafficCount()
{
	$.ajax({
        type: "GET",
        url: "cache/traffic.txt?"+Math.random(),
        dataType: "text",
        success: function(data) {$("#all_served").append(', '+filesize(parseInt(data))+" traffic");},
        error:  function(data) {fail = true}
     });
}

function getAllHits()
{
	$.ajax({
        type: "GET",
        url: "cache/hits.txt?"+Math.random(),
        dataType: "text",
        success: function(data) {$("#all_served").html('Server stats: '+data+' views');getAllTrafficCount();},
        error:  function(data) {fail = true}
     });
}

function getTrafficCount(hash)
{
	$.ajax({
        type: "GET",
        url: "cache/"+hash+"/traffic.txt?"+Math.random(),
        dataType: "text",
        success: function(data) {$("#traffic").html('<h2>Has so far produced '+filesize(parseInt(data))+' traffic</h2>');},
        error:  function(data) {fail = true}
     });
}

function getLogData(hash)
{
	$.ajax({
        type: "GET",
        url: "cache/"+hash+"/view_log.csv",
        dataType: "text",
        success: function(data) {processLogData(data);},
        error:  function(data) {fail = true}
     });
}

function renderNewViews(data)
{
    $("#content").append('<div id="view_chart" style="width: auto; height: 500px"></div>');

        var data = google.visualization.arrayToDataTable(data);

        var options = {
          title: 'Views per interval',
          curveType: 'function',
          legend: { position: 'bottom' },
          vAxis: { 
              viewWindow:{
                min:0
              }
          }
        };

        var chart = new google.visualization.LineChart(document.getElementById('view_chart'));

        chart.draw(data, options);
}

function renderViews(data)
{
    $("#content").append('<div id="allview_chart" style="width: auto; height: 500px"></div>');

        var data = google.visualization.arrayToDataTable(data);

        var options = {
          title: 'All views as of interval',
          curveType: 'function',
          legend: { position: 'bottom' },
          vAxis: { 
              viewWindow:{
                min:0
              }
          }
        };

        var chart = new google.visualization.LineChart(document.getElementById('allview_chart'));

        chart.draw(data, options);
}

function renderTraffic(data)
{
    $("#content").append('<div id="traffic_chart" style="width: auto; height: 500px"></div>');

        var data = google.visualization.arrayToDataTable(data);

        var options = {
          title: 'Traffic as of interval',
          curveType: 'function',
          legend: { position: 'bottom' },
          vAxis: { 
              viewWindow:{
                min:0
              }
          }
        };

        var chart = new google.visualization.LineChart(document.getElementById('traffic_chart'));

        chart.draw(data, options);
}

function processLogData(allText) {
    if(fail===true)
    {
        $("#content").html('<h1>Error</h1> <p>The hash you entered was not found</p>');
        return;
    }
    var lines = allText.split(/\r\n|\n/);
    newviewdata = [];
    viewdata = [];
    trafficdata = [];
    
    newviewdata[0] = ['Time', 'New views'];
    viewdata[0] = ['Time', 'Views'];
    trafficdata[0] = ['Time', 'Traffic in MB'];
    for(i=0;i<lines.length;i++)
    {
        var entries = lines[i].split(';');
        var time = entries[0];
        var views = parseInt(entries[1]);
        var traffic = parseInt(entries[2]);
        var allviews = parseInt(entries[3]);
        
        newviewdata[newviewdata.length] = [new Date(time*1000),views];
        viewdata[viewdata.length] = [new Date(time*1000),allviews];
        trafficdata[trafficdata.length] = [new Date(time*1000),parseFloat((traffic/1000000).toFixed(2))];
    }
    
    //console.log(trafficdata);
    
    renderNewViews(newviewdata);
    renderTraffic(trafficdata);
    renderViews(viewdata);
}

function unixToString(unix_timestamp)
{
    var date = new Date(unix_timestamp*1000);
    // Hours part from the timestamp
    var hours = date.getHours();
    // Minutes part from the timestamp
    var minutes = "0" + date.getMinutes();
    // Seconds part from the timestamp
    var seconds = "0" + date.getSeconds();
    
    // Will display time in 10:30:23 format
    var formattedTime = hours + ':' + minutes.substr(-2) + ':' + seconds.substr(-2);
    
    return formattedTime;
}