<?php

namespace App\Controller;

use App\Service\PayService;
use App\Service\AppService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Landlib\SimpleMail;

class YamoneyNoticeRecieverController extends AbstractController
{
    /**
	 * Обработка уведомлений от сервиса Yandex Money
     * @Route("/yamoney/notice/reciever", name="yamoney_notice_reciever")
    */
    public function index(PayService $oService, AppService $oAppService)
    {
    	$this->_oAppService = $oAppService;
    	$oService->setHttpNoticeEntityClassName('App\Entity\YaHttpNotice');
    	$oService->setPayTransactionEntityClassName('App\Entity\PhdPayTransaction');
    	$oService->setUserEntityClassName('App\Entity\PhdUsers');
    	$oService->setYandexNotificationEntityClassName('App\Entity\YaNotificationType');
    	$oService->setOperationEntityClassName('App\Entity\PhdOperations');
        return $oService->processYandexNotice($this, 'setWorkAsPayed');
    }
	/**
	 * @param array $aInfo  {user_id, sum, email, phone, order_id, operation_id} - можно использовать например  для отправки письма
	*/
    public function setWorkAsPayed(array $aInfo)
	{
		//Помечаем товар как оплаченный
		//TODO later make relation
		$oRepository = $this->getDoctrine()->getRepository('App\Entity\PhdMessages');
		$oMessage = $oRepository->find($aInfo['order_id']);
		if ($oMessage) {
			$oMessage->setIsPayed(true);
			$oMessage->setOperationId($aInfo['operation_id']);
			//TODO только если загружен уже архив!
			if ( strlen($oMessage->getResultLink() ) ) {
				$oMessage->setState(8);
			}

			$oEm = $this->getDoctrine()->getManager();
			$oEm->persist($oMessage);
			$oEm->flush();

			//отправить письмо клиенту с ссылкой
			$oRepository = $this->getDoctrine()->getRepository('App\Entity\PhdUsers');
			$oUser = $oRepository->find($aInfo['user_id']);
			$sEmail = '';
			if ($oUser) {
				$sEmail = $oUser->getEmail();
			}
			$oService = $this->_oAppService;
			//TODO тут переделать на уведомление, что оплата получена
			/*if ($oService->isValidEmail($sEmail) && $this->getParameter('app.sendemailoff') == 1) {
				$subject = 'Ваша верстка ждёт вас';
				$siteName = $this->getParameter('app.siteName');
				$siteUrlBegin = $this->getParameter('app.siteUrlBegin');
				$sResultLink = $siteUrlBegin . $oMessage->getResultLink();
				$sFrom = $this->getParameter('app.siteAdminEmail');
				$sPhdManagerEmail = $this->getParameter('app.phdAdminEmail');
				$sBody = '<p>Здравствуйте!</p><p>Вы получили это письмо, потому что оставляли заявку на конвертацию PSD файла в верстку на сайте '
					. $siteName . '.</p> Вы можете скачать архив с версткой по 
				<a href="'. $sResultLink .'" target="_blank">ссылке</a> 
				';
				$oMailer = new SimpleMail();
				$oMailer->setSubject($subject);
				$oMailer->setFrom($sFrom, $siteName);
				$oMailer->setAddressTo([$sEmail => $sEmail, $sPhdManagerEmail => 'Odminko']);
				$oMailer->setHtmlText($sBody);
				$oMailer->send();

			}*/

		}
		/*var_dump($aInfo);
		die();/**/
	}
}
