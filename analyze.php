<?php
error_reporting(E_ALL & ~E_NOTICE);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__FILE__));

if(!file_exists('config.inc.php')) exit('[X] Rename example.config.inc.php to config.inc.php first and set your values');
include_once('config.inc.php');

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






function renderSize($byte)
{
    if($byte < 1024) {
        $ergebnis = round($byte, 2). ' Byte';
    }elseif($byte < pow(1024, 2)) {
        $ergebnis = round($byte/1024, 2).' KB';
    }elseif($byte >= pow(1024, 2) and $byte < pow(1024, 3)) {
        $ergebnis = round($byte/pow(1024, 2), 2).' MB';
    }elseif($byte >= pow(1024, 3) and $byte < pow(1024, 4)) {
        $ergebnis = round($byte/pow(1024, 3), 2).' GB';
    }elseif($byte >= pow(1024, 4) and $byte < pow(1024, 5)) {
        $ergebnis = round($byte/pow(1024, 4), 2).' TB';
    }elseif($byte >= pow(1024, 5) and $byte < pow(1024, 6)) {
        $ergebnis = round($byte/pow(1024, 5), 2).' PB';
    }elseif($byte >= pow(1024, 6) and $byte < pow(1024, 7)) {
        $ergebnis = round($byte/pow(1024, 6), 2).' EB';
    }

        return $ergebnis;
}

function isThatAnImage($path)
{
    if(substr($path, 0, 10)=='/css/imgs/' || substr($path, 0, 12)=='/backend.php')
        return false;
    else if(strpos($path, '.jpg') || strpos($path, '.png') || strpos($path, '.gif') || strpos($path, '.mp4'))
        return true;
    else return false;
}

function urlToHash($url)
{
    $url = explode("/",$url);
    foreach($url as $el)
    {
        $el = strtolower($el);
        if(!$el) continue;

        if(strpos($el, '.jpg') || strpos($el, '.png') || strpos($el, '.gif'))
            return $el;
    }

    return false;
}

function getDataFromURL($path)
{
    $data = json_decode(implode(NULL, file(PICTSHARE_URL.'/backend.php?geturlinfo='.$path)),true);
    if(!is_array($data)) return false;
    if($data['status']=='ok') return $data;

    return false;
}

function getSizeOfPath($path,$hash)
{
    $basedir = 'cache/'.$hash;
    if(!is_dir($basedir))
        mkdir($basedir);
    $file = $basedir.'/size_'.sanatizeString($path).'.txt';
    if(file_exists($file))
    {
        $size = @file_get_contents($file);
        return $size?$size:0;
    }

    $data = json_decode(implode(NULL, file(PICTSHARE_URL.'/backend.php?geturlinfo='.$path)),true);
    if(!is_array($data)) return false;
    if($data['status']!='ok') return false;

    $fp = fopen($file,'w');
    fwrite($fp,$data['size']);
    fclose($fp);

    return $data['size'];
}

function sanatizeString($string)
{
    return preg_replace("/[^a-zA-Z0-9._]+/", "", $string);
}

function sanatizeStringForInflux($string)
{
    $string = trim($string);
    $string = str_replace(',','\\,',$string);
    $string = str_replace(' ','\\ ',$string);
    $string = str_replace('=','\\=',$string);
    return $string;
}

function sendToInflux($data,$time)
{
	//echo "[+] Sending to "."udp://".INFLUX_HOST.":".INFLUX_HOST_UDP_PORT.': '.INFLUX_HOST_MEASUREMENT.','.$data.' '.$time."\n"; //return;
	$socket = stream_socket_client("udp://".INFLUX_HOST.":".INFLUX_HOST_UDP_PORT);
	stream_socket_sendto($socket, INFLUX_HOST_MEASUREMENT.','.$data.' '.$time);
	stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
}

function cleanUp()
{
    if ($handle = opendir('cache/'))
    {
        while (false !== ($file = readdir($handle)))
        {
            if ($file != "." && $file != "..")
            {
                $filepath = ROOT.DS.'cache'.DS.$file.DS.'view_log.csv';
                if(is_dir('cache/'.$file) && isThatAnImage($filepath))
                {
                    removeZeroValues($filepath);
                }
            }
        }
        closedir($handle);
    }
}

function removeZeroValues($file)
{
    $temp_table = fopen($file.'.tmp','w');
    $lastviews = 0;
    $emptycount = 0;

    $handle = fopen($file,'r');
    if ($handle)
    {
        while (($line = fgets($handle)) !== false)
        {
            
            $line = trim($line);
            $a = explode(';',$line);
            $traffic = $a[2];
            $views = $a[3];
            $count = $a[1];
            if($count>0 && is_numeric($traffic) && is_numeric($views))
            {
                $lastviews = $views;
                fwrite($temp_table,$line."\n");
            }
            else if($count==0)
            {
                $emptycount++;
            }
        }
            

        fclose($handle);
    }

    echo "  [~]Removed $emptycount zero values from $file\n";

    fclose($temp_table);

    //rename($file,$file.'.orig');
    rename($file.'.tmp',$file);
    
}