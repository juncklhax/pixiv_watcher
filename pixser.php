#!/usr/bin/php
<?
require_once'html_parser/simple_html_dom.php';

class Crawler {
	protected $conn;
	protected $response;
	protected $html;
	protected $pre_url;

	// コンストラクタ
	function __construct() {
		$this->conn = curl_init();
		// クッキーをファイルに保存する
	  curl_setopt($this->conn, CURLOPT_COOKIEJAR, "cookie"); 
		curl_setopt($this->conn, CURLOPT_COOKIEFILE, "tmp_cookie"); 
		// 正規のSSLを使用しない
		curl_setopt($this->conn, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($this->conn, CURLOPT_SSL_VERIFYHOST, false);
		// タイムアウトまで永遠に待つ
		curl_setopt($this->conn, CURLOPT_CONNECTTIMEOUT, 0);
		// ロケーションを再木探索
		curl_setopt($this->conn, CURLOPT_FOLLOWLOCATION, 1);
		// 結果を標準出力に返さない
		curl_setopt($this->conn, CURLOPT_RETURNTRANSFER, true);
		// ヘッダを書き出すか
		curl_setopt($this->conn, CURLOPT_HEADER, false);//true);
	}

	// デストラクタ
	function __destruct() {
		curl_close($this->conn);
	}

	// プロキシの設定
	public function SetProxy($url, $port, $id_pass) {
		//プロキシ経由フラグ
//		curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
		//プロキシアドレス設定（プロキシのアドレス:ポート名）
		curl_setopt($ch, CURLOPT_PROXY, $url . ":" . $port);
		//念のためプロキシのポートを指定
		curl_setopt($ch, CURLOPT_PROXYPORT, $port);
		//プロキシのID,PASSの設定（ID:PASS）
		curl_setopt($ch, CURLOPT_PROXYUSERPWD, $id_pass);
	}

	// 接続を試行する
	public function Get($url) {
		// リファラを設定
		$this->SetReferer($this->pre_url);
		$this->response = "";
		curl_setopt($this->conn, CURLOPT_POST, false);
		curl_setopt($this->conn, CURLOPT_URL, $url);
		$this->response = curl_exec($this->conn);
		$this->html = str_get_html($this->response);
		$this->pre_url = $url;
		return $this->html;
	}
	// POSTデータを投げる
	public function Post($url, $postdata) {
		// リファラを設定
		$this->SetReferer($this->pre_url);
		$this->response = "";
		curl_setopt($this->conn, CURLOPT_POST, true);
		curl_setopt($this->conn, CURLOPT_URL, $url);
		curl_setopt($this->conn, CURLOPT_POSTFIELDS, $postdata);
		$this->response = curl_exec($this->conn);
		$this->html = str_get_html($this->response);
		$this->pre_url = $url;
		return $this->html;
	}

	// 接続結果をテキストで返す
	public function GetResponse() {
		return $this->response;
	}
	// 解析済みhtmlを返す
	public function GetHtml() {
		return $this->html;
	}
	// リファラを設定
	public function SetReferer($ref) {
		curl_setopt($this->conn, CURLOPT_REFERER, $ref);
	}
	public function GetReferer() {
		return $this->pre_url;
	}
	
	public function IsBinaryDownLoaded() {
		$info = curl_getinfo($this->conn);
		$res = strpos($info['content_type'], "text/html");
		if($res === false) {
			return true;
		}
		else {
			return false;
		}
	}
}

/*====================================================================
main処理
====================================================================*/
$c = new Crawler();

// 初期設定
$numof_saved_files = 0;
$search_words = "艦これ";
$is_r18_mode = "0";
$dirname = "files/";
$start_search_page = 1;
$end_search_page = 1000;
$bookmark_threshold = 1000;
$pixiv_id = "";
$password = "";

// コマンドライン引数で設定を変更
foreach($argv as $args) {
	if(strstr($args, "threshold-")) {
		$bookmark_threshold = intval(ltrim($args, "threshold-"));
		echo "bookmark threshold is " . $bookmark_threshold . "\n";
	}
	if(strstr($args, "search-")) {
		$search_words = ltrim($args, "search-");
		echo "search word is " . $search_words . "\n";
	}
	if(strstr($args, "id-")) {
		$pixiv_id = ltrim($args, "id-");
		echo "account id is " . $pixiv_id . "\n";
	}
	if(strstr($args, "pass-")) {
		$password = ltrim($args, "pass-");
		echo "password is " . $password . "\n";
	}
	if(strstr($args, "r-18")) {
		$is_r18_mode = "1";
		echo "set r-18 search.\n";
	}
}

if($pixiv_id == "" || $password == "") {
	echo "input id and password.\n";
	exit(0);
}

// 検索語をエンコード
$search_words = urlencode($search_words);

// ディレクトリがなければ作成
if( ! file_exists($dirname)) {
	mkdir($dirname);
}

// pixiv接続
$c->Post("https://www.secure.pixiv.net/login.php", 
	"mode=login&skip=0&pixiv_id=" . $pixiv_id . "&pass=" . $password
);

// 解析開始：解析ページ数分だけループ
for($i = $start_search_page; $i <= $end_search_page; $i++) {
	echo "page:" . $i . "\n";
	// 検索語固定でページ送り
	$res = $c->Get("http://www.pixiv.net/search.php?s_mode=s_tag&r18=". $is_r18_mode . "&word=" . $search_words . "&p=" . $i)
		->find('li[class=image-item]');
	// 検索に引っかからなかった時の終了処理
	$is_item_not_found = $c->GetHtml()->find('div[class=_no-item]');
	if(count($is_item_not_found) > 0) {
		echo "finished(not found)\n";
		exit(0);
	}
	// ブクマ数の解析
	foreach($res as $bookmark) {
		$bookmarks = 0;
		$link="";
		// ブクマ数
		$numof_bookmark_array = $bookmark->find("ul[class=count-list] li a");
		foreach($numof_bookmark_array as $numof_bookmark) {
			if($bookmarks < intval($numof_bookmark->plaintext)) {
				$bookmarks = intval($numof_bookmark->plaintext);
			}
		}
		// リンクURL
		$illust_url_array = $bookmark->find("a[class=work]");
		foreach($illust_url_array as $illust_url) {
			$link = $illust_url->{'href'};
			$link = str_replace("&amp;", "&", $link);
		}
		// ブクマ数の閾値
		if($bookmarks >= $bookmark_threshold) {
			$personal_illust_page = "http://www.pixiv.net" . $link;
			$illust_page = $c->Get($personal_illust_page);
			// うごイラは取得しない
			// works_display直下にdivが続く場合はうごイラとする
			$ugoira_array = $illust_page->find('div[class=works_display] div[class]');
			if(count($ugoira_array) > 0) {
				echo "ugoira(un saved)\n";
			}
			// 漫画かイラストの取得
			else {
				$illust_array = $illust_page->find('div[class=works_display] a');
				foreach($illust_array as $illust) {
					$illust_url = $illust->{'href'};
					$illust_url = str_replace("&amp;", "&", $illust_url);
					$illust_page_info = "http://www.pixiv.net/" . $illust_url;
					$find_pos = strpos($illust_page_info, "manga");
					// イラストの取得
					if($find_pos === false) {
						$url_array = $illust_page->find('div[class=works_display] a img');
						foreach($url_array as $img_src) {
							$img_link_url = $img_src->{'src'};
							$img_link_url = str_replace("&amp;", "&", $img_link_url);
							// オリジナルサイズのリンクを取得
							$img_link_url = str_replace("_m.jpg", ".jpg", $img_link_url);
							$img_link_url = str_replace("_m.png", ".png", $img_link_url);
							$img_link_url = str_replace("_m.gif", ".gif", $img_link_url);
							// 画像書き出し
							$c->Get($img_link_url);
							$sv_name = $numof_saved_files . "." . pathinfo($img_link_url, PATHINFO_EXTENSION);
							// 再投稿画像(?)は.jpg?123456...とファイル拡張子にクエリがついてるので消す
							$sv_name = preg_replace("/\?.+/", "", $sv_name);
							$fp = fopen($dirname . $sv_name, "wb");
							fwrite($fp, $c->GetResponse());
							fclose($fp);
							echo "saved:" . $img_link_url . "\n";
							++$numof_saved_files;
						}
					}
					// 漫画の取得
					else {
						$url_array = $illust_page->find('div[class=works_display] a img');
						foreach($url_array as $img_src) {
							$img_link_url = $img_src->{'src'};
							$img_link_url = str_replace("&amp;", "&", $img_link_url);
							// オリジナルサイズのリンクを取得
							$img_link_url = str_replace("_m.jpg", "_big_p0.jpg", $img_link_url);
							$img_link_url = str_replace("_m.png", "_big_p0.png", $img_link_url);
							$img_link_url = str_replace("_m.gif", "_big_p0.gif", $img_link_url);
							// 全ページ保存する
							for($j = 1; $j < 999; $j++) {
								$c->Get($img_link_url);
								// 漫画ページの終了条件
								if($c->IsBinaryDownLoaded()) {
									// xxx.[jpg|png]
									$sv_name = $numof_saved_files . "." . pathinfo($img_link_url, PATHINFO_EXTENSION);
									// 再投稿画像(?)は.jpg?123456...とファイル拡張子にクエリがついてるので消す
									$sv_name = preg_replace("/\?.+/", "", $sv_name);
									$fp = fopen($dirname . $sv_name, "wb");
									// 画像書き出し
									fwrite($fp, $c->GetResponse());
									fclose($fp);
									// 漫画の次ページURLを取得
									if($j <= 11) {
										$img_link_url = preg_replace("/p[0-9].jpg/", "p" . ($j-1) . ".jpg", $img_link_url); 
										$img_link_url = preg_replace("/p[0-9].png/", "p" . ($j-1) . ".png", $img_link_url); 
										$img_link_url = preg_replace("/p[0-9].gif/", "p" . ($j-1) . ".gif", $img_link_url); 
									}	
									if(11 < $j && $j <= 101) {
										$img_link_url = preg_replace("/p[0-9][0-9].jpg/", "p" . ($j-1) . ".jpg", $img_link_url); 
										$img_link_url = preg_replace("/p[0-9][0-9].png/", "p" . ($j-1) . ".png", $img_link_url); 
										$img_link_url = preg_replace("/p[0-9][0-9].gif/", "p" . ($j-1) . ".gif", $img_link_url); 
									}	
									if(101 < $j) {
										$img_link_url = preg_replace("/p[0-9][0-9][0-9].jpg/", "p" . ($j-1) . ".jpg", $img_link_url); 
										$img_link_url = preg_replace("/p[0-9][0-9][0-9].png/", "p" . ($j-1) . ".png", $img_link_url); 
										$img_link_url = preg_replace("/p[0-9][0-9][0-9].gif/", "p" . ($j-1) . ".gif", $img_link_url); 
									}	
									echo "saved:" . $img_link_url . "\n";
									++$numof_saved_files;
								}
								// NotFoundが見つかった==漫画ページが無いので打ち切り
								else {
									echo "manga not found\n";
									break;
								}
							}
						}
					}
				}
			}
		}
	}
}
?>
