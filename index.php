<?php
/**
 * $LeadManager->rename();      Ищет сделки в конктретной воронке, по названию - SEARH, удаляет из названия DESTROY
 * $LeadManager->destroy_tag(); Находит в этой же воронке тег и удаляет его у сделки (обновляет сделку и теги в ней, 
 * т.е. теги по сути новые, но фильтр фильтр по тегам работает корректно
 */
class LeadManager {

	const USER 			= '';
	const KEY 			= '';
	const ACCOUNT 		= '';
	const PIPE_ID		= '';
	const SEARH  		= '/(\w+)*\s\(ТЕСТ\)/i'; 	//присутствует в названии сделки
	const DESTROY 		= '/\s\(ТЕСТ\)/i'; 			//удаляется из названиия сделки
	const TAG_DESTROY	= 'ТЕСТ'; 					//искомый тэг

	private $_leads_link;
	private $_pipe_link;


	public function __construct()
	{
		$this->auth();
		$this->_leads_link = 'https://' . self::ACCOUNT . '.amocrm.ru/api/v2/leads?';
		$this->_pipe_link  = 'https://' . self::ACCOUNT . '.amocrm.ru/api/v2/pipelines?id=' . self::PIPE_ID;
	}

	private function getLeadsByPipline(){

		$pipe = $this->requset($this->_pipe_link);
		$statuses = $pipe['_embedded']['items'][self::PIPE_ID]['statuses'];
		$get='';
		foreach ($statuses as $status){
			if ($status['is_editable'] !== FALSE){
				$get .= 'status%5B%5D=' . $status['id'] . '&'; //urlencode переделывает "=" и АПИ не принимает, хз как быть
			}
		}
		$get = substr($get, 0, -1 );
		$leads_status_link = $this->_leads_link . $get;

		$leads = $this->requset($leads_status_link);
		$leads = $leads['_embedded']['items'];

		return $leads ? $leads : null;
	}

	public function rename(){

		$leads = $this->getLeadsByPipline();
		$date = mktime();
		$send = [];
		foreach ($leads as $lead) {
			if ($lead['pipeline_id'] == self::PIPE_ID && preg_match(self::SEARH ,$lead['name'])) {
				$name = preg_replace(self::DESTROY, '', $lead['name']);
				$send['update'][] = [
					'id' => $lead['id'],
					'updated_at' => $date,
					'name' => $name
				];
			}
		}
		if (!empty($send)){
			$respone = $this->requset($this->_leads_link, $send);
		}
		return $respone ? $respone : 'ни одной сделки не было переименовано.';
	}

	public function destroy_tag(){

		$leads = $this->getLeadsByPipline();
		$date = mktime();
		$send =[];
		foreach ($leads as $lead){
			if ($lead['pipeline_id'] == self::PIPE_ID){
				foreach ($lead['tags'] as $key=>$tag){
					if ($tag['name'] == self::TAG_DESTROY) {
						unset($lead['tags'][$key]);
						$tags = $lead['tags'];
						$names=[];
						foreach ($tags as $t){
							$names[] = $t['name'];
						}
						$names = implode(', ', $names);
						$send['update'][] = [
							'id' 		 => $lead['id'],
							'updated_at' => $date,
							'tags' 		 => $names
						];
					}
				}
			}
		}
		
		if (!empty($send)){
			$respone = $this->requset($this->_leads_link, $send);
		}
		return $respone ? $respone : 'нету тегов для удаления';
	}

	private function auth(){
		$link = 'https://' . self::ACCOUNT . '.amocrm.ru/private/api/auth.php?type=json';
		$user = [
			'USER_LOGIN' => self::USER,
			'USER_HASH'  => self::KEY
		];
		$this->requset($link, $user);

	}

	private function requset($link, $data = null){


		$headers[] = "Accept: application/json";
		$curl = curl_init();
		if ($data){
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
		}
		curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		curl_setopt($curl, CURLOPT_USERAGENT, "TOLYAN777");
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_URL, $link);
		curl_setopt($curl, CURLOPT_HEADER,false);
		curl_setopt($curl,CURLOPT_TIMEOUT,30);
		curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
		curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
		curl_setopt($curl,CURLOPT_COOKIEFILE,dirname(__FILE__)."/cookie.txt");
		curl_setopt($curl,CURLOPT_COOKIEJAR,dirname(__FILE__)."/cookie.txt");
		$out = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$result = json_decode($out,TRUE);
		$code = (int)$code;
		$errors = array(
			301 => 'Moved permanently',
			400 => 'Bad request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not found',
			500 => 'Internal server error',
			502 => 'Bad gateway',
			503 => 'Service unavailable'
		);
		try {
			#Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
			if ($code != 200 && $code != 204)
				throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error', $code);
		} catch (Exception $E) {
			die('Ошибка: ' . $E->getMessage() . PHP_EOL . 'Код ошибки: ' . $E->getCode());
		}

		return $result;
	}
}

$a = new LeadManager();
$result = $a->destroy_tag();
if (is_array($result)) {
	echo '<pre>';
	print_r($result);
	echo '</pre>';
} else {
	echo $result;
}