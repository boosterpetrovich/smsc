<?php
namespace misterlexa\smsc;

class Sender {

    public $login;
    public $pass;
    public $is_post;
    public $is_https;
    public $charset;
    public $debug;
    public $from;

    public function sendSms($phones, $message, $translit = 0, $time = 0, $id = 0, $format = 0, $sender = false, $query = "", $files = array())
    {
    	static $formats = array(1 => "flash=1", "push=1", "hlr=1", "bin=1", "bin=2", "ping=1", "mms=1", "mail=1", "call=1");

    	$m = $this->sendCmd("send", "cost=3&phones=".urlencode($phones)."&mes=".urlencode($message).
    					"&translit=$translit&id=$id".($format > 0 ? "&".$formats[$format] : "").
    					($sender === false ? "" : "&sender=".urlencode($sender)).
    					($time ? "&time=".urlencode($time) : "").($query ? "&$query" : ""), $files);

    	if ($this->debug) {
    		if ($m[1] > 0)
    			echo "��������� ���������� �������. ID: $m[0], ����� SMS: $m[1], ���������: $m[2], ������: $m[3].\n";
    		else
    			echo "������ �", -$m[1], $m[0] ? ", ID: ".$m[0] : "", "\n";
    	}

    	return $m;
    }

    public function sendMail($phones, $message, $translit = 0, $time = 0, $id = 0, $format = 0, $sender = "")
    {
    	return mail("send@send.smsc.ru", "", $login.":".$pass.":$id:$time:$translit,$format,$sender:$phones:$message", "From: ".$from."\nContent-Type: text/plain; charset=".$charset."\n");
    }

    public function smsCost($phones, $message, $translit = 0, $format = 0, $sender = false, $query = "")
    {
    	static $formats = array(1 => "flash=1", "push=1", "hlr=1", "bin=1", "bin=2", "ping=1", "mms=1", "mail=1", "call=1");

    	$m = $this->sendCmd("send", "cost=1&phones=".urlencode($phones)."&mes=".urlencode($message).
    					($sender === false ? "" : "&sender=".urlencode($sender)).
    					"&translit=$translit".($format > 0 ? "&".$formats[$format] : "").($query ? "&$query" : ""));

    	// (cost, cnt) ��� (0, -error)

    	if ($this->debug) {
    		if ($m[1] > 0)
    			echo "��������� ��������: $m[0]. ����� SMS: $m[1]\n";
    		else
    			echo "������ �", -$m[1], "\n";
    	}

    	return $m;
    }

    public function getStatus($id, $phone, $all = 0)
    {
    	$m = $this->sendCmd("status", "phone=".urlencode($phone)."&id=".urlencode($id)."&all=".(int)$all);

    	// (status, time, err, ...) ��� (0, -error)

    	if (!strpos($id, ",")) {
    		if ($this->debug )
    			if ($m[1] != "" && $m[1] >= 0)
    				echo "������ SMS = $m[0]", $m[1] ? ", ����� ��������� ������� - ".date("d.m.Y H:i:s", $m[1]) : "", "\n";
    			else
    				echo "������ �", -$m[1], "\n";

    		if ($all && count($m) > 9 && (!isset($m[$idx = $all == 1 ? 14 : 17]) || $m[$idx] != "HLR")) // ',' � ���������
    			$m = explode(",", implode(",", $m), $all == 1 ? 9 : 12);
    	}
    	else {
    		if (count($m) == 1 && strpos($m[0], "-") == 2)
    			return explode(",", $m[0]);

    		foreach ($m as $k => $v)
    			$m[$k] = explode(",", $v);
    	}

    	return $m;
    }

    public function getBalance()
    {
    	$m = $this->sendCmd("balance"); // (balance) ��� (0, -error)

    	if ($this->debug) {
    		if (!isset($m[1]))
    			echo "����� �� �����: ", $m[0], "\n";
    		else
    			echo "������ �", -$m[1], "\n";
    	}

    	return isset($m[1]) ? false : $m[0];
    }

    protected function sendCmd($cmd, $arg = "", $files = array())
    {
    	$url = ($this->is_https ? "https" : "http")."://smsc.ru/sys/$cmd.php?login=".urlencode($this->login)."&psw=".urlencode($this->pass)."&fmt=1&charset=".$this->charset."&".$arg;

    	$i = 0;
    	do {
    		if ($i) {
    			sleep(2 + $i);

    			if ($i == 2)
    				$url = str_replace('://smsc.ru/', '://www2.smsc.ru/', $url);
    		}

    		$ret = $this->readUrl($url, $files);
    	}
    	while ($ret == "" && ++$i < 4);

    	if ($ret == "") {
    		if ($this->debug)
    			echo "������ ������ ������: $url\n";

    		$ret = ",";
    	}

    	$delim = ",";

    	if ($cmd == "status") {
    		parse_str($arg);

    		if (strpos($id, ","))
    			$delim = "\n";
    	}

    	return explode($delim, $ret);
    }

    public function readUrl($url, $files)
    {
    	$ret = "";
    	$post = $this->is_post || strlen($url) > 2000 || $files;

    	if (function_exists("curl_init"))
    	{
    		static $c = 0; // keepalive

    		if (!$c) {
    			$c = curl_init();
    			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    			curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
    			curl_setopt($c, CURLOPT_TIMEOUT, 60);
    			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
    		}

    		curl_setopt($c, CURLOPT_POST, $post);

    		if ($post)
    		{
    			list($url, $post) = explode("?", $url, 2);

    			if ($files) {
    				parse_str($post, $m);

    				foreach ($m as $k => $v)
    					$m[$k] = isset($v[0]) && $v[0] == "@" ? sprintf("\0%s", $v) : $v;

    				$post = $m;
    				foreach ($files as $i => $path)
    					if (file_exists($path))
    						$post["file".$i] = function_exists("curl_file_create") ? curl_file_create($path) : "@".$path;
    			}

    			curl_setopt($c, CURLOPT_POSTFIELDS, $post);
    		}

    		curl_setopt($c, CURLOPT_URL, $url);

    		$ret = curl_exec($c);
    	}
    	elseif ($files) {
    		if ($this->debug)
    			echo "�� ���������� ������ curl ��� �������� ������\n";
    	}
    	else {
    		if (!$is_https && function_exists("fsockopen"))
    		{
    			$m = parse_url($url);

    			if (!$fp = fsockopen($m["host"], 80, $errno, $errstr, 10))
    				$fp = fsockopen("212.24.33.196", 80, $errno, $errstr, 10);

    			if ($fp) {
    				fwrite($fp, ($post ? "POST $m[path]" : "GET $m[path]?$m[query]")." HTTP/1.1\r\nHost: smsc.ru\r\nUser-Agent: PHP".($post ? "\r\nContent-Type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($m['query']) : "")."\r\nConnection: Close\r\n\r\n".($post ? $m['query'] : ""));

    				while (!feof($fp))
    					$ret .= fgets($fp, 1024);
    				list(, $ret) = explode("\r\n\r\n", $ret, 2);

    				fclose($fp);
    			}
    		}
    		else
    			$ret = file_get_contents($url);
    	}

    	return $ret;
    }

}
