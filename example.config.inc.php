<?php 
//InfluxDB reporting
define('INFLUX_HOST','');
define('INFLUX_HOST_UDP_PORT','');
define('INFLUX_HOST_MEASUREMENT','pictshare');


//path to the access log. nginx and apache2 supported out of the box
define('LOG_FILE','/var/log/nginx/pictshare.access.log');

//url to your pictshare instance
define('PICTSHARE_URL','http://localhost/pictshare');

//if true, will save URLs that people used to get to your image
//in a file called referrer.txt in the directory of every image
define('SAVE_REFERRER',false);

//if set to true, will go through all previously
//cached images that were not viewd since last analyzation to produce more accurate graphs
//and add a 0 view value to it. Might make analyzation slow in large setups 
define('FILL_ZERO_VALUES',true);