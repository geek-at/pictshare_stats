# PictShare stats

PictShare stats is a statistics tool for the open source image hosting service [PictShare](https://www.pictshare.net). It's recomended that you run it from the command line as a cronjob.

![Traffic analysis tool](https://www.pictshare.net/102687fe65.gif)

[![Website for stats](https://www.pictshare.net/800x400/forcesize/ec299e08d5.jpg)](https://www.pictshare.net/ec299e08d5.jpg)

This tool will analyze your webservers log files, extract requests + traffic and caches them to the server.
It will automatically parse the time from the log file so you don't have to worry about rotating log files since the tool doesn't remember which line was the last but what actual time

## [Live example](http://stats.pictshare.net/#e7f7c6cb10.jpg)

## Setup

1. After cloning the repo, change the two settings in analyze.php ```LOG_FILE``` and ```PICTSHARE_URL``` `
  - LOG_FILE should be the path for your webservers log file. PictShare Analyzer works out of the box with Apache and Nginx log files
  - PICTSHARE_URL is the URL for your PictShare instance. This is needed because the analyzer will ask your instance for sizes of images so traffic can be calculated right
2. Set a cron job for the analyzer to run in any interval you like. I recommend once every hour. eg: ```0 * * * * cd /var/www/pictsharestats;php analyze.php```
3. Rename example.config.inc.php to config.inc.php

## Update

```bash
# to be run from the directory where your pictshare stats directory sits in
git clone https://github.com/chrisiaut/pictshare_stats.git temp
cp -r temp/* pictshare_stats/. # if your pictshare stats directory is called pictshare_stats
rm -rf temp
```

After each update make sure your config.inc.php has all values defined in example.config.inc.php, since new options might come

## Q&A

### Q: What if my pictshare instance has multiple reverse proxy gateways? How can I analyze the logs from separate log files
The way the anaylzer is designed it doesn't rely on line numbers so you can just make a script that transfers all your log files and combines them into one file.
This file can then be analyzed by the tool and it will still analyze it correctly 