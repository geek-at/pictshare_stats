<?php 

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
    $lastcount = false;
    $lasttraffic = 0;

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
            $time = $a[0];
            if($count>0 && is_numeric($traffic) && is_numeric($views))
            {
                fwrite($temp_table,$line."\n");
            }
            else if($count==0)
            {
                if($lastcount>0) //write if the last value wasn't 0
                {
                    fwrite($temp_table,$line."\n"); //because we want to save one 0 per interval
                }
                else
                    $emptycount++;
            }
            $lastviews = $views;
            $lastcount = $count;
            $lasttraffic = $traffic;
        }
            

        fclose($handle);
    }

    echo "  [~]Removed $emptycount\tzero values from $file\n";

    fclose($temp_table);

    //rename($file,$file.'.orig');
    rename($file.'.tmp',$file);
    
}