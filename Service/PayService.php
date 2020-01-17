<?php
namespace App\Service;

use App\Entity\PhdPayTransaction;
use Psr\Log\LoggerInterface;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Response;
/**
 Потом будет бандл
*/
class PayService
{

	public function __construct(ContainerInterface $container, LoggerInterface $oLog)
	{
		$this->_oContainer = $container;
		$this->oTranslator = $container->get('translator');
		$this->_oRequest = $container->get('request_stack')->getCurrentRequest();
		$this->_oLog = $oLog;
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
	 * @return int идентификатор записи из таблицы связанной с сущностью 0 - если не удалось создать запись
	*/
	public function createTransaction(int $nUserId, int $nOrderId) : int
	{
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
		$oEm->persist($oPayTransaction);
		$oEm->flush();

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
		return ($oPayTransaction->getId() ?? 0 );
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
	 * @return array ['sum' => float, 'user_id' => int, 'email' => string, 'phone' => string, 'order_id']
	*/
	private function _getEmailData(string $label, string $withdraw_amount, string $yaRequestLogId) : array
	{
		//TODO ad relation OneToOne
		$aResult = ['sum' => 0, 'user_id' => 0, 'email' => '', 'phone' => '', 'order_id' => ''];
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
}
