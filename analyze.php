<?php
error_reporting(E_ALL & ~E_NOTICE);

if(!file_exists('config.inc.php')) exit('[X] Rename example.config.inc.php to config.inc.php first and set your values');
include_once('config.inc.php');

$dev = false;

echo "[+] Started..\n";

$lines = file(LOG_FILE,FILE_SKIP_EMPTY_LINES);
if(file_exists('cache/lasttime.txt'))
   $lasttime = trim(implode("",file('cache/lasttime.txt')));
else $lasttime=0;

if($dev) $lasttime=0;

if($lasstime)
    echo "[#] Lasttime: ".date("d.m (H:i)",$lasttime)."\n";
$stats = array();
foreach($lines as $line)
{
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
        }
}

echo "\r[+] Done analyzing..\n\n---------------\n\n";
$lasttime = (time()-1);

$fh = fopen("cache/lasttime.txt","w");
fwrite($fh,$lasttime);
fclose($fh);

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
    
    echo "\n\n";
    echo "[!] Traffic since last analyze: ".renderSize($alltraffic)."\n";
    echo "[!] Old traffic: ".renderSize($oldtraffic)."\n";
    echo "[!] All time traffic: ".renderSize(($oldtraffic+$alltraffic))."\n\n";
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
    else if(strpos($path, '.jpg') || strpos($path, '.png') || strpos($path, '.gif'))
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
