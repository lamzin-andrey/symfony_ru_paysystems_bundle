<?php
namespace App\Service;

use App\Entity\PhdPayTransaction;
use Psr\Log\LoggerInterface;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Response;
use App\Service\HttpRequest;
use \StdClass;

/**
 Потом будет бандл
*/
class PayService
{
	/** @property  App\Service\HttpRequest $oHttpRequest */
	private $_oHttpRequest = null;

	public function __construct(ContainerInterface $container, LoggerInterface $oLog, HttpRequest $oHttpRequest)
	{
		$this->_oContainer = $container;
		$this->_oTranslator = $container->get('translator');
		$this->_oRequest = $container->get('request_stack')->getCurrentRequest();
		$this->_oLog = $oLog;
		$this->_oHttpRequest = $oHttpRequest;
		//TODO set default entity classes from config
		// 'App\Entity\YaHttpNotice'
	}
	/**
	 * Обработка уведомлений от сервиса Yandex Money
	*/
	public function processYandexNotice($oContext = null, string $sMethod = '')
	{
		$aData = [];
		/** @var \Symfony\Component\HttpFoundation\Response  $oResponse */
		$oResponse = $this->_json($aData);
		$oResponse->setStatusCode(201);

		$oLog = $this->_oLog;
		$oLogCtx = ['context' => 'payments'];
		$oLog->info("\n===========" . date('Y-m-d H:i:s') . "===========\n" . print_r($_POST, 1) . "\n", $oLogCtx);
		$operation_id      = $this->_oRequest->get('operation_id');
		$operation_label   = $this->_oRequest->get('operation_label');
		$notification_type = $this->_oRequest->get('notification_type');
		$datetime          = $this->_oRequest->get('datetime');
		$unaccepted        = $this->_oRequest->get('unaccepted');
		$sha1_hash         = $this->_oRequest->get('sha1_hash');
		$sender            = $this->_oRequest->get('sender');
		$codepro           = $this->_oRequest->get('codepro');
		$codepro = $codepro && $codepro != 'false' ? 'true' : 'false';
		$unaccepted = $unaccepted && $unaccepted != 'false' ? 'true' : 'false';
		$currency = $this->_oRequest->get('currency');
		$amount   = $this->_oRequest->get('amount');
		$withdraw_amount = $this->_oRequest->get('withdraw_amount');
		$label    = $this->_oRequest->get('label');

		$secret = $this->_oContainer->getParameter('app.yasecretkey');
		$str = "{$notification_type}&{$operation_id}&{$amount}&{$currency}&{$datetime}&{$sender}&{$codepro}&{$secret}&{$label}";
		$hash = sha1($str);

		$oLog->info("\nhash = {$hash}\n\n" , $oLogCtx);
		$oLog->info("\nstr = '{$str}'\n\n" , $oLogCtx);

		if ($hash == $sha1_hash) {
			$nTid = intval($label);
			if ($nTid) {
				$label = intval($nTid);
				$yaRequestLogId = $this->_insertYandexNotificationData($operation_id, $notification_type, $datetime, $sender, $codepro, $amount, $withdraw_amount, 	$label, $operation_label, $unaccepted);
				$oRepository = $this->_oContainer->get('doctrine')->getRepository($this->_sPayTransactionClass);
				$this->_oEm = $oEm = $this->_oContainer->get('doctrine')->getManager();
				$oPayTransaction = $oRepository->find($nTid);
				$nId = 0;
				if ($oPayTransaction) {
					$nId = $oPayTransaction->getId();
				}
				if ($nId == 0) {
					$s = $this->_sPayTransactionClass;
					$oPayTransaction = new $s();
				}
				$oLog->info("Will update pay_transaction!\n" , $oLogCtx);
				/** @var  PhdPayTransaction $oPayTransaction*/
				$oPayTransaction->setIsConfirmed(true);
				$oPayTransaction->setYaHttpNoticeId($yaRequestLogId);
				$oPayTransaction->setRealSum($withdraw_amount);
				$oPayTransaction->setNotifyDatetime(new \DateTime());
				$oEm->persist($oPayTransaction);
				//Записываем данные в operations
				$aData = $this->_getEmailData($label, $withdraw_amount, $yaRequestLogId);
				$oContext->$sMethod($aData);
			}
			$oEm->flush();
			//NOTE возможно на самом деле тут действительно нужен 200
		}
		return $oResponse;
	}
	/**
	 * Добавить запись в таблицу транзакций и таблицу операций
	 * @param int $nUserId - идентификатор пользователя (клиента, покупателя)
	 * @param int $nOrderId - идентификатор товара или услуги (или заказа с группой товаров или услуг)
	 * @return StdClass {nPaytansactionId}
	 *   nPayTransactionId int идентификатор записи из таблицы связанной с сущностью payTransaction 0 - если не удалось создать запись
	 *   sPayUrl - в случае использования qiwi кошелька содержит url на который надо отправить пользователя
	 *   nBillId - в случае использования qiwi кошелька содержит номер счёта в системе qiwi полученный при создании счета
	 *   sError string - сообщение об ошибке, если не удалось создать транзакцию
	*/
	public function createTransaction(int $nUserId, int $nOrderId) : StdClass
	{
		$oResult = new \StdClass();
		$oResult->nPayTransactionId = 0;
		$oResult->nBillId = 0;
		$oResult->sPayUrl = '';
		$oResult->sError = '';

		$sClass = $this->_sPayTransactionClass;
		$oPayTransaction = new $sClass();
		$oPayTransaction->setUserId($nUserId);
		//NOTE для rk было безразлично, возможно qiwi заставит пересмотреть
		//Пока всегда пишем номер я-кошелька
		$oPayTransaction->setCache($this->_oContainer->getParameter('app.yacache'));
		$oPayTransaction->setSum( strval(floatval($this->_oRequest->get('sum', 0))) );
		$sMethod = '';
		$sRawMethod = $this->_oRequest->get('method', '');
		switch ($sRawMethod) {
			case 'MC':
				$sMethod = 'ms';
				break;
			case 'AC':
				$sMethod = 'bs';
				break;
			case 'PC':
				$sMethod = 'ps';
		}
		if (!$sMethod) {
			return 0;
		}

		$oPayTransaction->setMethod($sMethod);
		$oPayTransaction->setCreated(new \DateTime());
		$oEm = $this->_oContainer->get('doctrine')->getManager();

		//Да, это плохой код, но у меня нет времени на хороший
		if ($sMethod == 'ms') {
			$sPhone =  $this->_normalizePhone( $this->_oRequest->get('phone', '') );
			$nSz = strlen($sPhone);
			if ($nSz != 11) {
				$oResult->sError = $this->_oTranslator->trans('Телефон должен содержать 11 цифр');
				return $oResult;
			}
			$sNums = '0123456789';
			for ($i = 0; $i < $nSz; $i++) {
				$ch = $sPhone[$i];
				if (strpos($sNums, $ch) === false) {
					$oResult->sError = $this->_oTranslator->trans('Недопустимый символ "' . $ch . '" в номере телефона');
					return $oResult;
				}
			}
			$sThree = intval(substr($sPhone, 1, 3));
			//$aBeelineNumbers = [900, 902, 903, 904, 905, 906, 908, 909, 950, 951, 953, 960, 961, 962, 963, 964, 965, 966, 967, 968, 969, 980, 983, 986];
			$aMtsNumbers = [901, 902, 904, 908, 910, 911, 912, 913, 914, 915, 916, 917, 918, 919, 950, 978, 980, 981, 982, 983, 984, 985, 986, 987, 988, 989];

			if (!in_array($sThree, $aMtsNumbers)) {
				$oResult->sError = $this->_oTranslator->trans('Извините, в настоящее время платежи принимаются только с номеров абонентов МТС. Мы считаем, что вы ввели номер не абонента МТС, потому что первые три цифры номера после +7 или 8 "' . $sThree . '". Вы можете оплатить со счёта Яндекс-кошелька или со счёта банковской карты.');
				return $oResult;
			}
			$oPayTransaction->setPhone($sPhone);
		}

		$oEm->persist($oPayTransaction);
		$oEm->flush();

		//Если оплата через QIWI - Пока не ясно, что тут делать)
		/*if ($sMethod == 'ms') {
			//если дойдём до PayPal, пусть меняется на что надо
			$sCurrency = 'RUB';
			$sComment = 'test'; //'Payment for converting PSD to HTML + CSS (qiwi)';
			//TODO тут узкое место,  если будет не работать, начни с этого слэша
			$sExpirationDateTime = date('Y-m-d') . 'T' . date('H:i:s+03:00');
			$sExpirationDateTime = urlencode($sExpirationDateTime);
			$oQiwiResult = $this->_getQiwiPayFormUrl($oPayTransaction->getId(), floatval($this->_oRequest->get('sum', 0)), $sCurrency, $nUserId, $sComment, $sExpirationDateTime);
			$oResult->sPayUrl = $oQiwiResult->payUrl;
			$oResult->nBillId = $oQiwiResult->billId;

			if ($oResult->nBillId) {
				$oPayTransaction->setQiwiBillId(intval($oResult->nBillId));
				$oEm->persist($oPayTransaction);
				$oEm->flush();
			}
		}*/


		//create operation
		$sClass = $this->_sOperationsClass;
		/** @var \App\Entity\PhdOperations $operation */
		$operation = new $sClass();
		$operation->setUserId($nUserId);
		$operation->setOpCodeId($this->_oContainer->getParameter('rupayservices.operation_code_id'));
		$operation->setMainId($nOrderId);
		$operation->setSum($oPayTransaction->getSum());
		$operation->setPayTransactionId( intval($oPayTransaction->getId()) );
		$operation->setCreated( new \DateTime());
		$oEm->persist($operation);
		$oEm->flush();

		$oResult->nPayTransactionId = ($oPayTransaction->getId() ?? 0 );
		return $oResult;
	}

	public function setPayTransactionEntityClassName(string $s)
	{
		$this->_sPayTransactionClass = $s;
	}

	public function setHttpNoticeEntityClassName(string $s)
	{
		$this->_sHttpNoticeClass = $s;
	}

	public function setUserEntityClassName(string $s)
	{
		$this->_sPayUserClass = $s;
	}

	public function setOperationEntityClassName(string $s)
	{
		$this->_sOperationsClass = $s;
	}

	public function setYandexNotificationEntityClassName(string $s)
	{
		$this->_sYandexNotificationClass = $s;
	}

	private function _json($aData)
	{
		if (!isset($aData['status'])) {
			$aData['status'] = 'ok';
		}
		$oResponse = new Response( json_encode($aData) );
		$oResponse->headers->set("Content-Type", "application/json");
		return $oResponse;
	}

	/**
	 * Логирование данных HTTP уаведомления от Яндекса в Базе данных
	*/
	private function _insertYandexNotificationData($operation_id, $notification_type, $datetime, $sender, $codepro, $amount, $withdraw_amount, 	$label, $operation_label, $unaccepted)
	{
		$oNotificationTypeRepository = $this->_oContainer->get('doctrine')->getRepository($this->_sYandexNotificationClass);
		$oYaNotificationType = $oNotificationTypeRepository->findOneBy(['name' => $notification_type]);
		//$oYaNotificationType = ($oYaNotificationType[0] ?? null);
		$notificationId = 0;
		if ($oYaNotificationType) {
			$notificationId = strval($oYaNotificationType->getId() );
		}
		$oEm = $this->_oContainer->get('doctrine')->getManager();
		$s = $this->_sHttpNoticeClass;
		$oYaHttpNotice = new $s();
		$oYaHttpNotice->setNotificationTypeId($notificationId);
		$oYaHttpNotice->setAmount($amount);
		$oYaHttpNotice->setCodepro($codepro);
		$oYaHttpNotice->setUnaccepted($unaccepted);
		$oYaHttpNotice->setWithdrawAmount($withdraw_amount);
		$oYaHttpNotice->setSender($sender);
		$oYaHttpNotice->setOperationLabel($operation_label);
		$oYaHttpNotice->setOperationId($operation_id);
		$oYaHttpNotice->setLabel($label);
		$oYaHttpNotice->setDateTime( new \DateTime() );
		$oEm->persist($oYaHttpNotice);
		$oEm->flush();
		$insertId = $oYaHttpNotice->getId();
		return $insertId;
	}
	/**
	 * Результат может использоваться например для отправки письма
	 * @return array ['sum' => float, 'user_id' => int, 'email' => string, 'phone' => string, 'order_id', 'operation_id']
	*/
	private function _getEmailData(string $label, string $withdraw_amount, string $yaRequestLogId) : array
	{
		//TODO ad relation OneToOne
		$aResult = ['sum' => 0, 'user_id' => 0, 'email' => '', 'phone' => '', 'order_id' => '', 'operation_id' => ''];
		$nSum = intval($withdraw_amount);
		$oLog = $this->_oLog;
		$aCtx = ['context' => 'payment'];
		$oLog->info("PayService::_getEmailData got payTransactionId = {$label}\n", $aCtx);

		$oPayTransactionRepository = $this->_oContainer->get('doctrine')->getRepository($this->_sPayTransactionClass);
		$oPayTransaction = $oPayTransactionRepository->find($label);

		if ($oPayTransaction) {
			$operationsRepository = $this->_oContainer->get('doctrine')->getRepository($this->_sOperationsClass);
			/** @var \App\Entity\PhdOperations $operation */
			$operation = $operationsRepository->findOneBy(['payTransactionId' => $oPayTransaction->getId()]);
			if (!$operation) {
				$oLog->info('!operation' . ", 
					payTransactionId = {$label}\n", $aCtx);
				return $aResult;
			}
			//записываем в истории операций
			$aResult['user_id'] = $userId = intval( $oPayTransaction->getUserId() );
			$aResult['sum'] = $userId = floatval( $oPayTransaction->getSum() );
			$aResult['order_id'] = $operation->getMainId();
			$aResult['operation_id'] = $operation->getId();
			$oUserRepository = $this->_oContainer->get('doctrine')->getRepository($this->_sPayUserClass);
			$oUser = $oUserRepository->find($userId);
			if ($oUser) {
				if (method_exists($oUser, 'getEmail')) {
					$aResult['email'] = $oUser->getEmail();
				}
				if (method_exists($oUser, 'getPhone')) {
					$aResult['phone'] = $oUser->getPhone();
				}
			}
		}
		return $aResult;
	}
	/**
	 * @param int $nBuild - на данный момнет использую payTransaction.id
	 * @param float $nSum
	 * @param string $sCurrency
	 * @param int $nUserId
	 * @param string $sComment
	 * @param int $nExpirationDateTime
	 * @return \StdClass {billId, payUrl}
	*/
	private function _getQiwiPayFormUrl(int $nBuild, float $nSum, string $sCurrency, int $nUserId, string $sComment, string $sExpirationDateTime) : \StdClass
	{
		/** @var \App\Service\HttpRequest $oHttpRequest */
		$oHttpRequest = $this->_oHttpRequest;
		$sJsonData = json_encode([
			'billId' => $nBuild,
			'amount' => [
				'value' => '1.00',
				'currency' => $sCurrency
			],
			'phone' => $this->_oContainer->getParameter('app.qiwi_phone'),
			'account' => $nUserId,
			'comment' => $sComment,
			'email' => 'example@mail.org',
			'expirationDateTime' => $sExpirationDateTime
		]);
		$sUrl = 'https://api.qiwi.com/partner/bill/v1/bills/create'; ///893794793973
		$proc = null;
		$aHeaders = [
			'Accept: application/json',
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->_oContainer->getParameter('app.qiwi_secret_key')
		];
		//$oResponse = $oHttpRequest->sendRawPut($sUrl, $sJsonData, '', $proc, true, '', $aHeaders);

		//----------- ebd old trying -------
		/** @var \Qiwi\Api\BillPayments $billPayments */
		$billPayments = new \Qiwi\Api\BillPayments($this->_oContainer->getParameter('app.qiwi_secret_key'));


		$billId = $nBuild;
		$fields = [
			'amount' => 1.00,
			'currency' => 'RUB',
			'comment' => 'test',
			'phone' => $this->_oContainer->getParameter('app.qiwi_phone'),
			'expirationDateTime' => date('Y-m-dTH:i:s+03:00'), //  '2018-03-02T08:44:07+03:00'
			'email' => 'example@mail.org',
			'account' => $nUserId
		];
		try {
			$oResponse = $billPayments->createBill($billId, $fields);
		} catch (\Exception $e) {
			die($e->getMessage());
		}

		$oResult = new StdClass();
		$oResult->payUrl = '';
		if ($oResponse) {
			$oResult->payUrl = ($oResponse->payUrl ?? '');
			$oResult->billId = ($oResponse->billId ?? 0);
		}
		$oResult->billId = $this->_oContainer->getParameter('app.qiwi_secret_key');
		//TODO logging
		file_put_contents('/home/andrey/log.log', print_r($oResponse, true));
		return $oResult;
	}

	/**
	 * Так как это будет в отдельном бандле, то ничего страшного что этот метод продублирован
	 * Удаляет из номера телефона всё, кроме цифр. Ведущий +7 меняет на 8.
	 * @param string $sPhone
	 * @return string
	*/
	private function _normalizePhone(string $sPhone) : string
	{
		$phone = trim($sPhone);
		$plus = 0;
		if (isset($phone[0]) && $phone[0] == '+') {
			$plus = 1;
		}
		$s = trim(preg_replace("#[\D]#", "", $phone));
		if ($plus && strlen($s) > 10) {
			$code = substr($s, 0, strlen($s) - 10 );
			$tail = substr($s, strlen($s) - 10 );
			$code++;
			$s = $code . $tail;
		} elseif($plus) {
			$s = '';
		}
		return $s;
	}
}
