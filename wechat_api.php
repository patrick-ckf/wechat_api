<?php

date_default_timezone_set("Asia/Hong_Kong");
include_once 'parallelcurl.php';
include_once 'cache.class.php';
//include_once 'config.php';
include_once 'config.php.working';
include_once 'google_php_api.php';
set_time_limit(0);

class wechat
{
	private $cache;
	private $appid;
	private $appsecret;
	private $folder_id;
	private $mime_type;
	private $curl_ctx;
	private $parallel_curl;
	private $client;
	private $service;
	const GET_USER_LIST = '/user/get?';
	const GET_USER_INFO = '/user/info?';
	const GET_AUTOREPLY_INFO = '/get_current_autoreply_info?';
	const API_URL_PREFIX = 'https://api.weixin.qq.com/cgi-bin';
	const API_BASE_URL_PREFIX = 'https://api.weixin.qq.com';

	static $DATACUBE_URL_ARR = array(
	        'user' => array(
	                'summary' => array (
				'link' => '/datacube/getusersummary?',
				'callback' => 'on_request_usersummary_done',
			),
	                'cumulate' => array (
				'link' => '/datacube/getusercumulate?',
				'callback' => 'on_request_usercumulate_done',
			),
	        ),
	        'article' => array(
	                'summary' => array (
				'link' => '/datacube/getarticlesummary?',
				'callback' => 'on_request_articlesummary_done',
			),
	                'total' => array (
				'link' => '/datacube/getarticletotal?',
				'callback' => 'on_request_articletotal_done',
			),
	                'read' => array (
				'link' => '/datacube/getuserread?',
				'callback' => 'on_request_userread_done',
			),
	                'readhour' => array (
				'link' => '/datacube/getuserreadhour?',
				'callback' => 'on_request_userreadhour_done',
			),
	                'share' => array (
				'link' => '/datacube/getusershare?',
				'callback' => 'on_request_usershare_done',
			),
	                'sharehour' => array (
				'link' => '/datacube/getusersharehour?',
				'callback' => 'on_request_usersharehour_done',
			),
	        ),
	        'upstreammsg' => array (
	                'summary' => array (
				'link' => '/datacube/getupstreammsg?',
				'callback' => 'on_request_upstreammsg_done',
			),
			'hour' => array (
				'link' => '/datacube/getupstreammsghour?',
				'callback' => 'on_request_upstreammsghour_done',
			),
	                'week' => array (
				'link' => '/datacube/getupstreammsgweek?',
				'callback' => 'on_request_upstreammsgweek_done',
			),
	                'month' => array (
				'link' => '/datacube/getupstreammsgmonth?',
				'callback' => 'on_request_upstreammsgmonth_done',
			),
	                'dist' => array (
				'link' => '/datacube/getupstreammsgdist?',
				'callback' => 'on_request_upstreammsgdist_done',
			),
	                'distweek' => array (
				'link' => '/datacube/getupstreammsgdistweek?',
				'callback' => 'on_request_upstreammsgdistweek_done',
			),
	               	'distmonth' => array (
				'link' => '/datacube/getupstreammsgdistmonth?',
				'callback' => 'on_reuqest_upstreammsgdistmonth_done',
			),
	        ),
	        'interface' => array(
	                'summary' => array (
				'link' => '/datacube/getinterfacesummary?',
				'callback' => 'on_request_interfacesummary_done',
			),
	                'summaryhour' => array (
				'link' => '/datacube/getinterfacesummaryhour?',
				'callback' => 'on_request_interfacesummaryhour_done',
			),
	        )
	);

	function __construct($appid, $appsecret, $mime_type, $folder_id) {
		$curl_options = array(
    		CURLOPT_SSL_VERIFYPEER => FALSE,
    		CURLOPT_SSL_VERIFYHOST => FALSE,
			CURLOPT_FORBID_REUSE => TRUE,
			CURLOPT_SSLVERSION => 1,
			CURLOPT_FORBID_REUSE => TRUE,
			CURLOPT_RETURNTRANSFER => TRUE,
		);

		$this->parallel_curl = new ParallelCurl(100, $curl_options);
		$this->appid = $appid;
		$this->appsecret = $appsecret;
		$this->mime_type = $mime_type;
		$this->folder_id = $folder_id;
		$this->cache = new Cache();
		$this->cache->setCache('cache');
		$this->get_token_from_wechat();

		if (file_exists(__DIR__."/csv/") == false) {
			mkdir (__DIR__."/csv/");
		}

		$this->client = getClient();
		$this->service = new Google_Service_Drive($this->client);
	}

	function __destruct() {
		$this->parallel_curl->finishAllRequests();
	}

	function real_post_data($url, $data = NULL) {
		$this->curl_ctx = curl_init();
		curl_setopt($this->curl_ctx, CURLOPT_URL, $url);
		curl_setopt($this->curl_ctx, CURLOPT_FORBID_REUSE, true);
		curl_setopt($this->curl_ctx, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($this->curl_ctx, CURLOPT_TIMEOUT, 4000);

		if (stripos($url,"https://") != FALSE) {
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
			curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); //CURL_SSLVERSION_TLSv1
		}

		if ($data != NULL) {
			$data_string = json_encode($data);
			// var_dump($data_string);
			curl_setopt($this->curl_ctx, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
			curl_setopt($this->curl_ctx, CURLOPT_POSTFIELDS, $data_string);                                                                  
			curl_setopt($this->curl_ctx, CURLOPT_HTTPHEADER, array(
    				'Connection: Keep-Alive',
				'Keep-Alive: 10000',
				'Content-Type: application/json',
				'Content-Length: ' . strlen($data_string))
			);
		}
		curl_setopt($this->curl_ctx, CURLOPT_RETURNTRANSFER, 1);

		$sContent = curl_exec($this->curl_ctx);
		$aStatus = curl_getinfo($this->curl_ctx);

		$curl_errno = curl_errno($this->curl_ctx);
		$curl_error = curl_error($this->curl_ctx);
	
		if ($curl_errno > 0) {
			echo __FUNCTION__."cURL Error ($curl_errno): $curl_error\n";
		}

		curl_close($this->curl_ctx);
		$json_data = json_decode($sContent);
		if(intval($aStatus["http_code"]) == 200){
			if (isset($json->errcode)) {
				echo __FUNCTION__.": network error with error code: ".$json->errcode."\n";
				if ($json->errcode == 40001) {
					echo __FUNCTION__.": get token again\n";
					$this->get_token_from_wechat();
				}
				usleep(200);
				return false;
			} else {
				usleep(200);
				echo __FUNCTION__.": done\n";
				return $sContent;
			}
		} else {
			usleep(200);
			echo __FUNCTION__.": network error with status code: ".$aStatus["http_code"]."\n";
			return false;
		}
	}

	function post_data($url, $data = NULL) {
		$ret = false;
		do {
			$ret = $this->real_post_data($url, $data);
		} while ($ret == false);
		return $ret;
	}

	function print_date_time_now() {
		$dtz = new DateTimeZone("Asia/Hong_Kong"); //Your timezone
		$now = new DateTime('NOW', $dtz);
	}

	function upload_file_to_google_drive ($title, $filename) {
		$fileId = searchFile($this->service, $title);
		if ($fileId != null) {
			echo __FUNCTION__.": updating file: ".$filename." to google drive\n";
			updateFile($this->service, $fileId, $title, $title, $this->mime_type, $filename, false);
		} else {
			echo __FUNCTION__.": uploading file: ".$filename." to google drive\n";
			insertFile($this->service, $title, $title, $this->folder_id, $this->mime_type, $filename);
		}
	}
	
	function get_user_list_from_wechat($url, &$user_list) {
		$next_openid = NULL;
		$context = $this->post_data($url);
		$json_data = json_decode($context);

		
		if (isset($json_data->count)) {
			$count = $json_data->count;
			if ($count > 0) {
				$data = $json_data->data;
				$openid_list = $data->openid;
				foreach ($openid_list as $openid) {
					$user_list[] = $openid;
				}
				if ($count >= 10000) {
					$next_openid=$json_data->next_openid;
				}
			}
		}
		return $next_openid;
	}

	function get_user_list(&$user_list) {
		$next_openid = $this->get_user_list_from_wechat(self::API_URL_PREFIX.self::GET_USER_LIST.'access_token='.$this->get_token(), $user_list);
		while($next_openid != NULL) {
			$next_openid = $this->get_user_list_from_wechat(self::API_URL_PREFIX.self::GET_USER_LIST.'access_token='.$this->get_token().'&next_openid='.$next_openid, $user_list);
		}
	}

	function on_request_user_info_done ($content, $url, $ch) {
		$json = json_decode($content, true);
		if (isset($json['subscribe'])) {
			if ($json['subscribe'] == 1) {
				if ($result->num_rows == 0) {
					$query = "INSERT INTO members (subscribe, openid, nickname, sex, city, country, province, language, headimgurl, subscribe_time) VALUES ("
						.$json['subscribe'].",'"
						.$json['openid']."','"
						.str_replace("'", "\'", $json['nickname'])."',"
						.$json['sex'].",'"
						.$json['city']."','"
						.$json['country']."','"
						.$json['province']."','"
						.$json['language']."','"
						.$json['headimgurl']."',"
						.$json['subscribe_time'].')';
				}
			}
		}
	}

	function on_request_usercumulate_done($data) {
		if (count($data) <= 0) return;
		echo __FUNCTION__.": start parsing\n";
		$title = 'output_getusercumulate-'.$data[0]['ref_date'].'.csv';
		$filename = 'csv/'.$title;
		$fp = fopen($filename, 'w');
		fputcsv($fp, array($data[0]['ref_date'], $data[0]['cumulate_user']));
		fclose($fp);
		echo __FUNCTION__.": done parsing\n";
		$this->upload_file_to_google_drive($title, $filename);
	}

	function on_request_usersummary_done($data) {
		if (count($data) <= 0) return;
		echo __FUNCTION__.": start parsing\n";
		$title = 'output_getusersummary-'.$data[0]['ref_date'].'.csv';
		$filename = 'csv/'.$title;
		$fp = fopen($filename, 'w');
		foreach ($data as $list) {
			if ($list['user_source'] == 0) {
				$new_user = 0;
				$cancel_user = 0;
			}
			$new_user += $list['new_user'];
			$cancel_user += $list['cancel_user'];
			if ($list['user_source'] == 43) {
				$temp_data = array($list['ref_date'], $new_user, $cancel_user, $new_user-$cancel_user);
				fputcsv($fp, $temp_data);
			}
		}
		fclose($fp);
		echo __FUNCTION__.": done parsing\n";
		$this->upload_file_to_google_drive($title, $filename);
	}

	function on_request_userreadhour_done($data) {
		if (count($data) <= 0) return;
		echo __FUNCTION__.": start parsing\n";
		$title = 'output_getuserreadhour-'.$data[0]['ref_date'].'.csv';
		$filename = 'csv/'.$title;
		$fp = fopen($filename, 'w');
		foreach ($data as $list) {
			if ($list['user_source'] == 0) {
				$int_page_read_user = 0;
				$int_page_read_count = 0;
				$ori_page_read_user = 0;
				$ori_page_read_count = 0;
				$share_user = 0;
				$share_count = 0;
				$add_to_fav_user = 0;
				$add_to_fav_count = 0;
			}
			$int_page_read_user += $list['int_page_read_user'];
			$int_page_read_count += $list['int_page_read_count'];
			$ori_page_read_user += $list['ori_page_read_user'];
			$ori_page_read_count += $list['ori_page_read_count'];
			$share_user += $list['share_user'];
			$share_count += $list['share_count'];
			$add_to_fav_user += $list['add_to_fav_user'];
			$add_to_fav_count += $list['add_to_fav_count'];
			if ($list['user_source'] == 5) {
				$temp_data = array($list['ref_date'], $list['ref_hour'], $int_page_read_user, $int_page_read_count, $ori_page_read_user, $ori_page_read_count, $share_user, $share_count, $add_to_fav_user);
				fputcsv($fp, $temp_data);
			}
		}
		fclose($fp);
		echo __FUNCTION__.": done parsing\n";
		$this->upload_file_to_google_drive($title, $filename);
	}

	function on_request_articletotal_done($data) {
		if (count($data) <= 0) return;
		echo __FUNCTION__.": start parsing\n";
		$file_title = 'output_getarticletotal-'.$data[0]['ref_date'].'.csv';
		$filename = 'csv/'.$file_title;
		$fp = fopen($filename, 'w');
		foreach ($data as $item) {
			$details = $item['details'];
			foreach ($details as $detail) {
				$target_user=$detail['target_user'];
				$read_count=$detail['int_page_read_user'];
				$orig_count=$detail['ori_page_read_user'];
				$share_count=$detail['share_user'];
				$favour_count=$detail['add_to_fav_user'];
			}
			$title = $item['title'];
			$temp_data = array($title, "图文页阅读人数", $read_count);
			fputcsv($fp, $temp_data);
			$temp_data = array($title, "原文页阅读人数", $orig_count);
			fputcsv($fp, $temp_data);
			$temp_data = array($title, "分享转发人数", $share_count);
			fputcsv($fp, $temp_data);
			$temp_data = array($title, "微信收藏人数", $favour_count);
			fputcsv($fp, $temp_data);
			$temp_data = array($title, "送达人数", $target_user);
			fputcsv($fp, $temp_data);
		}	
		fclose($fp);
		echo __FUNCTION__.": done parsing\n";
		$this->upload_file_to_google_drive($file_title, $filename);
	}

	function on_request_articlesummary_done($data) {
		if (count($data) <= 0) return;
		echo __FUNCTION__.": start parsing\n"; 
		$title = 'output_getariclesummary-'.$data[0]['ref_date'].'.csv';
		$filename = 'csv/'.$title;
		$fp = fopen($filename, 'w');
		foreach ($data as $item) {
			$temp_data = array($item['title'], "图文页阅读人数", $item['int_page_read_user']);
			fputcsv($fp, $temp_data);
			$temp_data = array($item['title'], "原文页阅读人数", $item['ori_page_read_user']);
			fputcsv($fp, $temp_data);
			$temp_data = array($item['title'], "分享转发人数", $item['share_user']);
			fputcsv($fp, $temp_data);
			$temp_data = array($item['title'], "微信收藏人数", $item['add_to_fav_user']);
			fputcsv($fp, $temp_data);
		}
		fclose($fp);
		echo __FUNCTION__.": done parsing\n";
		$this->upload_file_to_google_drive($title, $filename);
	}

	function get_user_info_new($openid) {
		if ($result->num_rows == 0) {
			$this->parallel_curl->startRequest(
				self::API_URL_PREFIX.self::GET_USER_INFO.'access_token='.$this->get_token().'&openid='.$openid.'&lang=zh_CN', 
				'Wechat::on_request_user_info_done'
			);
		} else {
		}
	}

	function get_user_info($openid, &$user_info_list) {
		$context = $this->post_data(self::API_URL_PREFIX.self::GET_USER_INFO.'access_token='.$this->get_token().'&openid='.$openid.'&lang=zh_CN');
		$json = json_decode($context);

		if (isset($json->subscribe)) {
			if ($json->subscribe == 1) {
				if ($json->sex == 1) {
					$sex = "男";
				} else if ($json->sex == 2) {
					$sex = "女";
				} else if ($json->sex == 0) {
					$sex = "未知";
				}
	
				$date = date("j, n, Y H:i:s", $json->subscribe_time);
				$user_info = array($json->nickname, $sex, $json->language, $json->city, $json->province, $json->country, $json->headimgurl, $date);
				$user_info_list[] = $user_info;
			}
		}
	}

	function get_datacube_data($type, $subtype, $begin_date, $end_date = NULL) {
		echo __FUNCTION__.": start parsing: ".$type." with subtype: ".$subtype." start_date: ".$begin_date."\n";
		$data = array(
			'begin_date'=>$begin_date,
			'end_date'=>$end_date?$end_date:$begin_date
		);
		$result = $this->post_data(self::API_BASE_URL_PREFIX.self::$DATACUBE_URL_ARR[$type][$subtype]['link'].'access_token='.$this->get_token(), $data);
		$callback = self::$DATACUBE_URL_ARR[$type][$subtype]['callback'];

		if (isset($result)) {
			if(is_callable([$this, $callback])) {
				if (!empty((array) $result)) {
					array_walk(json_decode($result, true), array($this, $callback));
				}
			}
		}
		echo __FUNCTION__.": done parsing: ".$type." with subtype: ".$subtype." start_date: ".$begin_date."\n";
	}

	function get_token_from_wechat() {
		$wechat_get_token_url = self::API_URL_PREFIX.'/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsecret;
		$context = $this->post_data($wechat_get_token_url);
		$json_data = json_decode($context);
		if (isset($json_data->access_token)) {
			$this->cache->store('access_token', $json_data->access_token, $json_data->expires_in);
		}
	}

	function get_auto_reply_list () {
		echo __FUNCTION__.": start parsing\n";
		$context = $this->post_data(self::API_URL_PREFIX.self::GET_AUTOREPLY_INFO.'access_token='.$this->get_token());

		$jfo = json_decode($context);

		$autoreply_info = $jfo->keyword_autoreply_info;
		$keyword_list = $autoreply_info->list;

		$fp = fopen("csv/keyword.csv", 'w');
		foreach ($keyword_list as $keyword_info) { 
			$rule_name = $keyword_info->rule_name;
			$keyword_array = array($rule_name, "關鍵詞");

			$list = $keyword_info->keyword_list_info;
			foreach ($list as $item) {
				array_push($keyword_array, $item->content);
			}
			fputcsv($fp, $keyword_array);

			$reply_list = $keyword_info->reply_list_info;
			foreach ($reply_list as $reply) {
				$temp_list = $reply->news_info->list;
				foreach ($temp_list as $temp_item) {
					$reply_item = array($rule_name, "文章", $temp_item->title, $temp_item->content_url);
					fputcsv($fp, $reply_item);
				}
			}
		}
		fclose($fp);
		echo __FUNCTION__.": done parsing\n";
	}

	function get_token() {
		$this->cache->eraseExpired();
		if (!$this->cache->isCached('access_token')) {
			$this->get_token_from_wechat();
		}
		return $this->cache->retrieve('access_token');
	}
}

	function print_help() {
		echo "List of commands:\n";
		echo "\t01. articlesummary: get all articles statistics with the given date or yesterday\n";
		echo "\t02. articletotal: get the de-duplicated statistics in the past seven days from the given date or yesterday\n";
		echo "\t03. userreadhour: get the hourly trend data with the given date or yesterday\n";
		echo "\t04. usersummary: get the user add drop summary with the given date or yesterday\n";
		echo "\t05. usercumulate: get the user cummulated value with the given date or yesterday\n";
		echo "\t06. getuserlist: get the current user list in openid\n";
		echo "\t07. getuserinfo: get all the user info currently and save to local db\n";
		echo "\t08. autoreply: get the auto reply currently\n";
		echo "\t09. help: print this menu \n";
		echo "\t10. exit: quit this program \n";
	}

	$wechat = new Wechat($appid, $appsecret, $upload_mime_type, $upload_folder_id);
	$user_list = array();
	$user_info_list = array();

	$query = "";

	$dtz = new DateTimeZone("Asia/Hong_Kong"); //Your timezone
	if (isset($argv[1])) {
		$now = new DateTime($argv[1]);
	} else {
		$now = new DateTime('NOW', $dtz);
		$now->sub(new DateInterval('P01D'));
	}

	$start_date = $now->format("Y-m-d");
	$temp = $now;
	$a_date = $temp->format("Y-m-d");

	for ($i = 0; $i < 7; $i++) {
		$wechat->get_datacube_data('article', 'total', $a_date);
		$temp->sub(new DateInterval('P01D'));
		$a_date = $temp->format("Y-m-d");
	}
	$now->add(new DateInterval('P07D'));
	
	$wechat->get_datacube_data('article', 'summary', $start_date, NULL);

	$wechat->get_datacube_data('article', 'readhour', $start_date);

	$wechat->get_datacube_data('user', 'summary', $start_date);

	$wechat->get_datacube_data('user', 'cumulate', $start_date);
?>
