<?php

class ImgurParser
{
    const IMAGE_URL = 'https://api.imgur.com/3/image/{{imageHash}}';
    const GALLERY_URL = 'https://api.imgur.com/3/gallery/user/time/day/0?showMature=true';
    const RANDOM_URL = 'https://api.imgur.com/3/gallery/random/random/0';

    const DB_HOST = 'localhost';
    const DB_USERNAME = 'root';
    const DB_PASSWORD = 'admin123';
    const DB = 'imgur';
    const DB_CHARSET = 'utf8';

    private $_ch;
    private $_db;

    private function _init()
    {
        $this->_initCurl();
        $this->_initDb();
    }

    private function _initCurl()
    {
        $this->_ch = $ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Client-ID 15fe591ce923c54'
        ));
    }

    private function _initDb()
    {

        $username = self::DB_USERNAME;
        $password = self::DB_PASSWORD;
        $host = self::DB_HOST;
        $database = self::DB;
        $charset = self::DB_CHARSET;
        $dsn = "mysql:host=$host;dbname=$database;charset=$charset";
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $imgurDB = new PDO($dsn, $username, $password, $opt);

        $this->_db = $imgurDB->prepare("INSERT INTO image (key_image, date_created_add, hash) VALUES (?,?,?)");
    }

    public function _getImageId($length = 7) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

    public function run()
    {
        $this->_init();

        $timeStart = microtime(true);
        $memoryUsageStart = memory_get_usage();
        echo 'Starting parser' . PHP_EOL;
        for ($i = 0; $i < 100; $i++) {
//        while(true) {
            $url = str_replace('{{imageHash}}', $this->_getImageId(), self::IMAGE_URL);
            curl_setopt($this->_ch, CURLOPT_URL, $url);
            $output = curl_exec($this->_ch);
            $decoded = json_decode($output, true);

            if ($decoded['status'] != 404) {
                $image = $decoded['data'];
                $typeCond = $image['type'] == 'image/jpeg';
                $sizeCond = ($image['width'] == 479) && ($image['height'] == 799);
                $userCond = $image['account_id'] === null;
                if ($typeCond && $sizeCond && $userCond) {
                    $keyImage = $decoded['data']['id'];
                    $hash = $decoded['data']['id'];
                    $dateCreatedAdd = $decoded['data']['datetime'];

                    $this->_db->bindParam(1, $keyImage);
                    $this->_db->bindParam(2, $dateCreatedAdd);
                    $this->_db->bindParam(3, $hash);

                    $this->_db->execute();
                }
            }

            $memoryUsage = memory_get_usage() - $memoryUsageStart;
            $time = microtime(true) - $timeStart;

            echo 'Total memory usage: ' . $memoryUsage / 1024 . ' kb || ' .
                'Execution time: ' . $time . ' seconds' . "\r";
        }

        curl_close($this->_ch);
        echo PHP_EOL;
    }
}

$parser = new ImgurParser();
$parser->run();