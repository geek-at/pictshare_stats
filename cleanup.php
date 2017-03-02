<?php
error_reporting(E_ALL & ~E_NOTICE);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));
define('CLI', PHP_SAPI === 'cli');

if(!file_exists('config.inc.php')) exit('[X] Rename example.config.inc.php to config.inc.php first and set your values');
include_once('config.inc.php');
include_once('functions.php');

echo "[+] Started..\n";

if(!CLI)
    exit('[:(] This script can be called from CLI only');

if ($handle = opendir('cache/'))
{
        while (false !== ($file = readdir($handle)))
        {
            if ($file != "." && $file != "..")
            {
                $filepath = ROOT.DS.'cache'.DS.$file.DS.'view_log.csv';
                if(is_dir('cache/'.$file) && isThatAnImage($filepath))
                {
                    $lastline = '';
                    $fp = fopen($filepath, "r");
                    if ($fp)
                    {
                        while (($line = fgets($fp)) !== false) {
                            $line = trim($line);
                            if($line)
                                $lastline = $line;
                        }

                        if($lastline)
                        {
                            $time = substr($lastline,0,strpos($lastline,';'));
                            $date = date("d.m.y H:i",$time);
                            $diff = time()-$time;
                            $days = ceil($diff/86400);
                            //echo "$file\t$date\t$days days ago\n";
                            if($days > 365)
                            {
                                echo "  [i] $file removal candidate with $days\n";
                                $toremove++;
                            }
                        }

                        //echo "lastline of $filepath = $lastline\n";

                        fclose($fp);
                    }
                }
            }
        }
        closedir($handle);
}

if($toremove)
    echo "[i] $toremove can be removed\n";

echo "[+] Finished\n";