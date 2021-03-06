<?php

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

function xorCrypt($text, $key) {
    $key = md5($key);
    
     // Our output text
    $outText = '';
    
    // Iterate through each character
    for($i=0;$i<strlen($text);) {
        for($j=0;($j<strlen($key) && $i<strlen($text));$j++,$i++) {
            $outText .= $text{$i} ^ $key{$j};
        }
    }  
    return $outText;
}

class Uniav
{
    public  $email;
    private $config = array();
  
    public function __construct()
    {
        if (! file_exists('./config.php')) {
            die('Create config.php to run app.');
        }
	
        $this->config = require('./config.php');
        // 1
        if (isset($_GET['e'])) {
            $email = strtolower(trim(xorCrypt(base64_decode($_GET['e']), $this->config['cryptKey'])));
            
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header("HTTP/1.0 500 Internal Server Error");
                die('Wrong parameter e.');
            } else {
                $this->email = $email;
            }
        } else {
            header("HTTP/1.0 500 Internal Server Error");
            die('Required parameter missing.');
        }
    }
    
    static public function create()
    {
        return new self;
    }
    
    public function run()
    {
    	$emailHash = md5($this->email);
    	
        // 2.a
        if (! file_exists('./data/' . $emailHash . '.dat')) {
            // 3.a
            if ($image = $this->getFacebookPhoto()) {
                // 5.a
                if ($imageUrl = $this->uploadToAws($image, $emailHash . '.jpg')) {
                    //6
                    $data = $this->email . "\n";
                    $data .= $imageUrl . "\n";
                    
                    file_put_contents('./data/' . $emailHash . '.dat', $data);
                    header("Location:" . $imageUrl);
                } else { //7.b
                    $this->redirectGravatar();
                }
            } else { //7.b
                $this->redirectGravatar();
            }
            
        } else {
            // 7.a
            $data = file('./data/' . $emailHash . '.dat');
            header("Location:" . $data[1]);
        }
    }
    
    public function getCurl($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
    
    public function getFacebookPhoto()
    {
        $url  = 'http://www.facebook.com/search.php?init=s:email&q=' . strtolower(trim($this->email)) . '&type=users';
        $output = $this->getCurl($url);

        if (substr_count($output, 'detailedsearch_result') === 1) {
            preg_match("/<a.?href=['\"](.+?facebook.com.+?)['\"].+?>/i", $output, $match);
            $id = substr( $match[1], strrpos( $match[1], '/' ) +1);
            return $this->getCurl('https://graph.facebook.com/'. $id .'/picture/?type=large');
        } else {
            return false;
        }
    }
    
    public function uploadToAws($image, $keyname)
    {
        $s3 = S3Client::factory(array(
            'key'    => $this->config['awsKey'],
            'secret' => $this->config['awsSecret'],
        ));
        
        try {
            $result = $s3->getCommand('PutObject')
                ->set('Bucket', $this->config['awsBucket'])
                ->set('Key', $keyname)
                ->set('Body', $image)
                ->set('ACL', 'public-read')
                ->set('ContentType', 'image/jpeg')
                ->getResult();
                
            return $result['ObjectURL'];
        } catch (S3Exception $e) {
            echo $e->getMessage();
        }
    }
    
    public function redirectGravatar()
    {
        header("Location:http://www.gravatar.com/avatar/" . md5(strtolower(trim($this->email))) . "?d=mm&s=400");
    }
}

Uniav::create()->run();
