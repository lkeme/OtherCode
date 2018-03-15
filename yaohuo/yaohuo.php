<?php

/**
 * Yaohuo meat
 * User: Mudew
 * Site: https://mudew.com/
 * Repostory: https://github.com/lkeme/
 * Date: 2018/3/15
 * Time: 14:15
 * Vserion: 0.1
 */

header("Content-type:text/html;charset=utf-8");
error_reporting(E_ALL ^ E_NOTICE);
set_time_limit(0);
ini_set('max_execution_time', '0'); //设置超时 0 无限制

class Yhmeat
{
    private $_userName = '';
    private $_passWord = '';

    //自定义时间 单位秒 2-10分钟区间适宜
    private $_sleepTime = 300;

    private $_sidYaohuo = '';
    private $_guid = '';
    private $_cookie = '';
    //代理
    private $_deBug = false;
    //自定义回复 随机池 可以自定义添加
    private $_content = [
        '666', '吃', '可啪', '吃吃', '吃吃吃',
        '谢谢', '吃一个', '吃了', '腻害了',
        '支持一个', '吃肉', '...吃',
    ];
    //如果有被墙，换能访问的域名
    private $_bashUrl = 'http://yaohuo.me/';

    private $_userAgent = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.101 Safari/537.36';
    private $_headers = array(
        'Host' => 'yaohuo.me',
        'Cache-Control' => 'max-age=0',
        'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Upgrade-Insecure-Requests' => '1',
        'Origin' => 'http://yaohuo.me',
        'Content-Type' => 'application/x-www-form-urlencoded',
    );
    //init
    public function __construct($account)
    {
        foreach ($account as $value) {
            if ($value == '') {
                die('账号密码不能为空!');
            }
        }
        $this->_userName = $account['username'];
        $this->_passWord = $account['password'];

    }
    //login
    public function login()
    {
        echo '程序启动，开始登陆' . PHP_EOL;
        $url = $this->_bashUrl . 'waplogin.aspx';
        $data = [
            'logname' => $this->_userName,
            'logpass' => $this->_passWord,
            'savesid' => '0',
            'action' => 'login',
            'siteid' => '1000',
        ];
        $raw = $this->curl($url, $data, true);
        preg_match_all('/Set-Cookie: (.*);/iU', $raw, $cookies);
        $this->_guid = $cookies[1][1];
        $this->_sidYaohuo = $cookies[1][2];
        foreach ($cookies[1] as $value) {
            $this->_cookie .= $value . ';';
        }
        //TODO 暂时没做登陆失败校验
        echo '登陆成功!' . PHP_EOL;
        return true;
    }
    //start
    public function start()
    {
        $this->login();
        while (1) {
            $postIdList = $this->getPostId();
            foreach ($postIdList as $postId) {
                $info = $this->getMeatId($postId);
                if (is_array($info)) {
                    $this->eatMeat($info);
                }
            }
            echo '2分钟后进入下一轮查询！' . PHP_EOL;
            sleep($this->_sleepTime);
        }
    }
    //eatMeat
    public function eatMeat($info)
    {
        $url = $this->_bashUrl . 'bbs/book_re.aspx';
        $data = [
            "face" => "",
            "sendmsg" => "0",
            "content" => $this->_content[array_rand($this->_content, 1)],
            "action" => "add",
            "id" => $info['postid'],
            "siteid" => "1000",
            "lpage" => "1",
            "classid" => $info['cid'],
            "sid" => "$sidyaohuo",
            "g" => "快速回复",
        ];

        $raw = $this->curl($url, $data, false, $this->_cookie);
        preg_match('/<div class=\"tip\"><b>(.*?)<\/b>(.*?)<br\/>/i', $raw, $res);
        if (strpos($res[1], '成功')) {
            echo '帖子: ' . $info['postid'] . $res[2] . PHP_EOL;
            file_put_contents("postid.txt", $info['postid'] . "|\r\n", FILE_APPEND);
            return true;
        }
        print_r($res);
        echo '原因待查' . PHP_EOL;
        return true;
    }

    //getMeatId
    public function getMeatId($postId)
    {
        $url = $this->_bashUrl . 'bbs-' . $postId . '.html';
        $raw = $this->curl($url, null, false, $this->_cookie);
        preg_match("/<div\s+class=\"content\">(.*)<div\s+class=\"margin-top\"><\/div>\[/i", $raw, $meatId);
        preg_match("/<a\s+href=\"\/bbs\/book\_list\.aspx\?action=class\&classid=(.*?)\">(.*?)<\/a>/i", $raw, $cid);

        if (!empty($meatId) && !strpos($meatId[1], '余0')) {
            $rtxt = file_get_contents("postid.txt");
            $etxt = explode('|', $rtxt);
            if (in_array("$postId", $etxt)) {
                echo '帖子: ' . $postId . '已经回复过！' . PHP_EOL;
                return false;
            }
            return [
                'postid' => $postId,
                'cid' => $cid[1],
            ];
        }
        echo '帖子: ' . $postId . '么有肉!' . PHP_EOL;
        return false;

    }
    //getPostId
    public function getPostId()
    {
        $url = $this->_bashUrl . 'bbs/book_list.aspx?action=new';
        $raw = $this->curl($url, null, false, $this->_cookie);
        preg_match_all('/<a href=\"\/bbs-(.*?).html\".*?>(.*?)<\/a>/i', $raw, $postIdList);
        return $postIdList[1];

    }

    //通用curl
    private function curl($url, $data = null, $header = false, $cookie = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, $header);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_IPRESOLVE, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->_headers);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->_userAgent);
        if ($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }

        if ($this->_deBug) {
            curl_setopt($curl, CURLOPT_PROXY, "127.0.0.1"); //代理服务器地址
            curl_setopt($curl, CURLOPT_PROXYPORT, "8888"); //代理服务器端口
        }

        if (!empty($data)) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        $result = curl_exec($curl);
        curl_close($curl);
        return $result;
    }

}

//输入你的账号密码
$account = [
    'username' => '',
    'password' => '',
];

$meat = new Yhmeat($account);
$meat->start();
