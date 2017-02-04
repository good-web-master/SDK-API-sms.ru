<?php
class SMS {
	
	private $config; //array
	
	private $lastError; //array

	private $countPhoneNumbers = 0;
	
	
	public function __construct(array $config = array()) {
		if (!$config) {
			$this->config = include(__DIR__ . '/config.php');
		} else {
			$this->config = $config;
		}
	}
	
	private function checkPhone($number) {
		return preg_match('/^\+\d{10,}$/', $number);
	}
	
	public function send(array $message) {
		
		if ($this->config['charset_in'] != $this->config['charset_out']) {
			$message['text'] = iconv($this->config['charset_in'], $this->config['charset_out'], $message['text']);
			if ($message['text'] === false) {
				$this->error(1);
				return false;
			}
		}
		
		
		if (is_array($message['to'])) {
			$true_numbers = array_filter($message['to'], array($this, 'checkPhone'));

			$false_numbers = array_diff($message['to'], $true_numbers);
			
			if ($false_numbers) {
				$this->lastError = array(
					'error' => 202, 
					'errno' => 'Неправильные форматы номеров: ' . htmlspecialchars(implode(', ', $false_numbers))
				);
				return false;
			} else {
				$this->countPhoneNumbers = count($message['to']);
				$message['to'] = implode(',', $message['to']);
			}	
		} else {
			if (!$this->checkPhone($message['to'])) {
				$this->error(202);
				return false;
			}
			$this->countPhoneNumbers = 1;
		}
		
		
		$ch = curl_init($this->config['url_service'] . 'sms/send');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);

		$data = array(
			'api_id'		=> $this->config['api_id'],
			'to'			=> $message['to'],
			'text'		    => $message['text'],
			//'test' =>'1'
		);
		
		if ($this->config['partner_id']) {
			$data['partner_id'] = $this->config['partner_id'];
		}
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		
		$response = curl_exec($ch);
		curl_close($ch);
		
		/* data_response
			0 - code
			1 - id sms
			2 - balance
		*/
		$data_response = explode("\n", $response);
		
		if ($data_response[0] == '100') {
			return true;
		}
		
		$this->error((int) $data_response[0]);
		
		return false;
	}
	
	private function error($code) {
		$erros = array( 
			0 => 'Не известная ошибка',
			1 => 'Не удалось перекодировать строку',
			2 => 'При отправке SMS произошла ошибка, попробуйте повторить операцию позже',
			200	=> 'Неправильный api_id',
			201	=> 'Не хватает средств на лицевом счету',
			202	=> 'Неправильно указан номер',
			203	=> 'Нет текста сообщения',
			204	=> 'Имя отправителя не согласовано с администрацией',
			205	=> 'Сообщение слишком длинное (превышает 8 СМС)',
			206	=> 'Будет превышен или уже превышен дневной лимит на отправку сообщений',
			207	=> 'На этот номер нельзя отправлять сообщения',
			208	=> 'Параметр time указан неправильно',
			209	=> 'Вы добавили этот номер (или один из номеров) в стоп-лист',
			210	=> 'Используется GET, где необходимо использовать POST',
			211	=> 'Метод не найден',
			212	=> 'Текст сообщения необходимо передать в кодировке UTF-8 (вы передали в другой кодировке)',
			220	=> 'Отправка SMS временно недоступна, попробуйте чуть позже.',
			230	=> 'На один номер в день нельзя отправлять более 60 сообщений.',
			300	=> 'Неправильный token (возможно истек срок действия, либо ваш IP изменился)',
			301	=> 'Неправильный пароль, либо пользователь не найден',
			302	=> 'Пользователь авторизован, но аккаунт не подтвержден (пользователь не ввел код, присланный в регистрационной смс)'
		);
		
		if ($this->countPhoneNumbers > 1) {
			$erros[207] = 'На один из номеров нельзя отправлять сообщения, либо указано более 100 номеров в списке получателей';
		}
		
		if (!isset($erros[$code])) {
			$code = 0;
		}
		
		
		if (!in_array($code, array(202,207,220,230))) {
			
			file_put_contents(__DIR__ . '/log.txt', $code . ' ' . $erros[$code] . "\r\n", FILE_APPEND);

			$code = 2;
		}
		
		$this->lastError = array('error' => $erros[$code], 'errno' => $code);

	}
	
	public function getLastError() {
		if (isset($this->lastError)) {
			return $this->lastError;
		} else {
			return false;
		}
	}
}