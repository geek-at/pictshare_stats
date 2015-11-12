# PictShare stats

PictShare stats is a statistics tool for the open source image hosting service [PictShare](https://www.pictshare.net).

This tool will analyze your webservers log files, extract requests + traffic and caches them to the server.
It will automatically parse the time from the log file so you don't have to worry about rotating log files since the tool doesn't remember which line was the last but what actual time

## Setup

1. After cloning the repo, change the two settings in analyze.php ```LOG_FILE``` and ```PICTSHARE_URL``` `
  - LOG_FILE should be the path for your webservers log file. PictShare Analyzer works out of the box with Apache and Nginx log files
  - PICTSHARE_URL is the URL for your PictShare instance. This is needed because the analyzer will ask your instance for sizes of images so traffic can be calculated right
2. Set a cron job for the analyzer to run in any interval you like. I recommend once every hour
3. Rename example.config.inc.php to config.inc.php

## Update

```bash
# to be run from the directory where your pictshare stats directory sits in
git clone https://github.com/chrisiaut/pictshare_stats.git temp
cp -r temp/* pictshare_stats/. # if your pictshare stats directory is called pictshare_stats
rm -rf temp
```