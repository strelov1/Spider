<?php

require __DIR__ . '/../vendor/autoload.php';

class TwitterCrawler extends \Grab\Spider
{
    public function taskPreLogin()
    {
        $this->task('login', [
            'url' => 'https://twitter.com/'
        ]);
    }

    public function taskLogin($parser, $task, $content)
    {
        $inputs = $parser->find('.LoginForm > input');
        $authenticity_token = $inputs[4]->getAttribute('value');
        $this->task('afterlogin', [
            'url' => 'https://twitter.com/sessions',
            'method' => \Grab\Spider::POST,
            'post_data' => [
                'authenticity_token' => $authenticity_token,
                'password' => 'user',
                'username_or_email' => 'pass',
            ]
        ]);
    }

    public function taskAfterLogin()
    {
        $this->task('post', [
            'url' => 'https://twitter.com/'
        ]);
    }

    public function taskPost($parser, $tack, $content)
    {
        $posts = $parser->find('.tweet-text');
        foreach ($posts as $post) {
            echo $post->text() . PHP_EOL;
        }
    }
}

$bot = new TwitterCrawler();
$bot->saveCookie = true;
$bot->loadProxy([
    '193.219.103.113:8080',
    '180.250.113.98:8080',
    '122.189.21.2:8118',
    '202.152.39.66:8080',
    '171.38.41.57:8123',
    '193.219.103.114:8080',
    '153.149.29.116:3128',
    '153.149.155.207:3128',
    '47.88.189.216:3128',
    '123.133.116.197:8998',
    '188.166.185.212:8080',
    '182.253.236.74:8080',
    '153.149.162.47:3128',
    '153.149.158.98:3128',
    '47.88.106.82:3128',
    '60.21.209.114:8080',
    '123.110.58.10:8998',
    '202.27.212.138:8080',
    '103.66.232.246:8080',
    '183.63.110.202:3128',
    '218.106.205.145:8080',
    '211.79.61.8:3128',
    '58.181.220.74:3128',
    '209.198.197.165:80',
    '47.88.102.99:3128',
    '27.11.88.77:8118',
    '49.212.149.221:8080',
    '202.106.16.36:3128',
    '39.79.105.28:8888',
    '47.88.195.233:3128',
]);
$bot->setCurlSetting([
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
]);
$bot->run();