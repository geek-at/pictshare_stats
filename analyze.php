<?php
error_reporting(E_ALL & ~E_NOTICE);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

if(!file_exists('config.inc.php')) exit('[X] Rename example.config.inc.php to config.inc.php first and set your values');
include_once('config.inc.php');
include_once('functions.php');

$dev = false;

echo "[+] Started..\n";

if(file_exists('cache/lasttime.txt'))
   $lasttime = trim(implode("",file('cache/lasttime.txt')));
else $lasttime=0;

//since we might want to check the logs and send them to influx more often
if(INFLUX_HOST)
{
    $influxsent = 0;
    if(file_exists('cache/lastinflux.txt'))
    $lastinflux = trim(implode("",file('cache/lastinflux.txt')));
    else $lastinflux=0;
}

if($dev) $lasttime=0;

if($lasttime)
    echo "[#] Lasttime: ".date("d.m (H:i)",$lasttime)."\n";
$stats = array();
$handle = fopen(LOG_FILE, "r");
if ($handle)
    while (($line = fgets($handle)) !== false)
    {
        $line = trim($line);
        $i++;
        $type = false;
        $hash = false;
        $line = trim($line);
        $arr = explode(' ', $line);
        $ip = $arr[0];
        $time = $arr[3];
        $request = $arr[4];
        $path = $arr[6];
        $responsecode = $arr[8];
        $referrer = str_replace('"', '', $arr[10]);
        $arr2 = explode('/', $path);
        if(count($arr2)<2){continue;}       
        $timestring = substr($time,1);
        $tarr = explode('/',$timestring);
        $day = $tarr[0];
        $month = $tarr[1];
        $tarr = explode(':',$tarr[2]);
        $year = $tarr[0];
        $hour = $tarr[1];
        $min = $tarr[2];
        $sec = $tarr[3];        
        $time = strtotime("$day-$month-$year $hour:$min:$sec");
        
        $agent = str_replace('"', '', implode(' ',array_slice($arr, 11)));      
        if($time<$lasttime || $responsecode!=200)
        {continue;}
        else $lasttime = $time;     
        if($dev) echo "Analyzing $path. Is image? ".(isThatAnImage($path)?'Yes':'No')."\n";     
        if(isThatAnImage($path))
        {            
            if(!$data[$path])
                $data[$path] = getDataFromURL($path);       
            $hash = $data[$path]['hash'];
            $size = $data[$path]['size'];       
            if(!$hash || !$size) continue;      
            if(!$dev) echo "\rGot ".++$count.' requests';
            
            if(INFLUX_HOST && $time>$lastinflux)
            {
                $influxtime = $time.'000000000';
                ++$influxsent;
                sendToInflux('hash='.$hash.',ip='.$ip.',referrer='.sanatizeStringForInflux(($referrer?$referrer:'0')).' value=1,size='.$size,$influxtime);
            }
            
            
            if(SAVE_REFERRER)
            {
                $basedir = 'cache/'.$hash;
                if(!is_dir($basedir))
                    mkdir($basedir);
                $fp = fopen($basedir.'/referrers.txt','a');
                fwrite($fp,trim($referrer)."\n");
                fclose($fp);
            }
                    
            $stats[$hash]['count']++;
            $stats[$hash]['traffic']+=$size;
            $mosttraffic[$hash]+=$size;     
            $alltraffic+=$size;
            $allhits++;
        }
    }

echo "\r[+] Done analyzing..\n\n---------------\n\n";
$lasttime = (time()-1);

$fh = fopen("cache/lasttime.txt","w");
fwrite($fh,$lasttime);
fclose($fh);

if(INFLUX_HOST)
{
    echo "\r\n[+] Sent $influxsent packages to influx\n\n---------------\n\n";
    $lastinflux = (time()-1);
    $fh = fopen("cache/lastinflux.txt","w");
    fwrite($fh,$lastinflux);
    fclose($fh);
    if($argv[1]=='onlyinflux')
        die("[X] Stopping since cli arg told me to ;)\n");
}

if(is_array($mosttraffic))
{
    arsort($mosttraffic);
    
    foreach($mosttraffic as $hash=>$traffic)
    {
        $basedir = 'cache/'.$hash;
        if(!is_dir($basedir))
            mkdir($basedir);
        $count = $stats[$hash]['count'];
        $size = $stats[$hash]['traffic'];
        $oldtraffic = trim(@file_get_contents($basedir.'/traffic.txt'));
        $oldhits = trim(@file_get_contents($basedir.'/hits.txt'));
        $newtraffic = ($oldtraffic+$size);
        $newhits = $oldhits+$count;
    
        echo "[?] $hash was viewed $count times and used ".renderSize($size)." - now produced ".renderSize(($size+$oldtraffic))." Traffic in ".($oldhits+$count)." hits\n";
    
        $fp = fopen($basedir.'/view_log.csv','a');
        fwrite($fp,time().";$count;$newtraffic;$newhits\n");
        fclose($fp);
    
        $fp = fopen($basedir.'/traffic.txt','w');
        fwrite($fp,$newtraffic."\n");
        fclose($fp);
    
        $fp = fopen($basedir.'/hits.txt','w');
        fwrite($fp,($oldhits+$count)."\n");
        fclose($fp);
    }
    
    $oldtraffic = trim(@file_get_contents('cache/traffic.txt'));
    $fp = fopen('cache/traffic.txt','w');
    fwrite($fp,($oldtraffic+$alltraffic));
    fclose($fp);
    
    $oldhits = trim(@file_get_contents('cache/hits.txt'));
    $fp = fopen('cache/hits.txt','w');
    fwrite($fp,($oldhits+$allhits));
    fclose($fp);
    
    echo "\n\n";
    echo "[!] Traffic since last analyze: ".renderSize($alltraffic)."\n";
    echo "[!] Old traffic: ".renderSize($oldtraffic)."\n";
    echo "[!] All time traffic: ".renderSize(($oldtraffic+$alltraffic))."\n\n";
    echo "-------\n\n";
    echo "[!] Hits so far (all images): ".renderSize(($oldhits+$allhits))."\n";
}
else
    echo '[!] No image accessed'."\n\n";
    
if(FILL_ZERO_VALUES)
{
    echo "[~] Adding 0 values to all images that were not viewed this time\n";
    if ($handle = opendir('cache/'))
    {
        while (false !== ($file = readdir($handle)))
        {
            if ($file != "." && $file != "..")
            {
                if(is_dir('cache/'.$file) && !is_array($stats[$file]))
                {
                    $newtraffic = trim(@file_get_contents('cache/'.$file.'/traffic.txt'));
                    $newhits = trim(@file_get_contents('cache/'.$file.'/hits.txt'));
                    
                    $fp = fopen('cache/'.$file.'/view_log.csv','a');
                    fwrite($fp,time().";0;$newtraffic;$newhits\n");
                    fclose($fp);
                    
                    if(!$dev) echo "\rAdded to ".++$j.' images';
                }
            }
        }
        closedir($handle);
    }
    echo "\n\n";
    echo "[~] Cleaning up multiple zeros in a row\n";
    cleanUp();
    echo "Done\n";
}
echo "[FIN] Exiting..\n\n";
