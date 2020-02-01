<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * PhdPayTransaction
 *
 * @ORM\Table(name="phd_pay_transaction")
 * @ORM\Entity
 * ORM\Cache(usage="READ_ONLY", region="global")
 */
class PhdPayTransaction
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var int|null
     *
     * @ORM\Column(name="user_id", type="integer", nullable=true, options={"comment"="Идентификатор пользователя phd_users.id"})
     */
    private $userId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="cache", type="string", length=20, nullable=true, options={"comment"="Номер кошелька"})
     */
    private $cache;

    /**
     * @var string|null
     *
     * @ORM\Column(name="sum", type="decimal", precision=10, scale=2, nullable=true, options={"comment"="Сумма в рублях которую пользователь собирается оплатить"})
     */
    private $sum;

    /**
     * @var string
     *
     * @ORM\Column(name="real_sum", type="decimal", precision=10, scale=2, nullable=false, options={"comment"="Сумма в рублях которую пользователь реально потратил", "default"="0.00"})
     */
    private $realSum = 0;

    /**
     * @var string|null
     *
     * @ORM\Column(name="method", type="string", length=4, nullable=true, options={"comment"="ps - платеж с Якошелька, ms - платеж с мобильного номера (qiwi), bs - платеж с помощью карты"})
     */
    private $method = '';

	/**
	 * @var string|null
	 *
	 * @ORM\Column(name="phone", type="string", length=11, nullable=true, options={"comment"="номер телефона при ms"})
	 */
	private $phone = '';

	/**
	 * @var int|null
	 * @ORM\Column(name="qiwi_bill_id", type="integer", nullable=true, options={"comment"="billId - идентификатор, полученный в ответе сервера qiwi при создании счёта"})
	*/
	private $qiwiBillId = 0;


    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="created", type="datetime", nullable=true, options={"comment"="Время операции", "default"="CURRENT_TIMESTAMP"})
     */
    private $created;

    /**
     * @var bool|null
     *
     * @ORM\Column(name="is_confirmed", type="boolean", nullable=true, options={"comment"="1 когда пришел HTTP запрос из Яндекса по этой записи"})
     */
    private $isConfirmed = '0';

    /**
     * @var int|null
     *
     * @ORM\Column(name="ya_http_notice_id", type="integer", nullable=true, options={"comment"="Если нотайс подтверждён http запросом из Яндекса, ya_http_notice содержит запись о входящих параметрах"})
     */
    private $yaHttpNoticeId = '0';

    /**
     * @var int|null
     *
     * @ORM\Column(name="qiwi_http_notice_id", type="integer", nullable=true, options={"comment"="Если платеж подтвержден нотайсом от qiwi, содержит id записи в qiwi_http_notice"})
     */
    private $qiwiHttpNoticeId = '0';


	/**
	 * @var \DateTime|null
	 *
	 * @ORM\Column(name="notify_datetime", type="datetime", nullable=true, options={"comment"="Время, когда поступил нотайс от платежной системы", "default"="CURRENT_TIMESTAMP"})
	 */
	private $notifyDatetime;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getCache(): ?string
    {
        return $this->cache;
    }

    public function setCache(?string $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    public function getSum(): ?string
    {
        return $this->sum;
    }

    public function setSum(?string $sum): self
    {
        $this->sum = $sum;

        return $this;
    }

    public function getRealSum(): ?string
    {
        return $this->realSum;
    }

    public function setRealSum(string $realSum): self
    {
        $this->realSum = $realSum;

        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;

        return $this;
    }

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setPhone(?string $sPhone): self
	{
		$this->phone = $sPhone;

		return $this;
	}

    public function getCreated(): ?\DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(?\DateTimeInterface $created): self
    {
        $this->created = $created;

        return $this;
    }

	public function getNotifyDatetime(): ?\DateTimeInterface
	{
		return $this->notifyDatetime;
	}

	public function setNotifyDatetime(?\DateTimeInterface $o): self
	{
		$this->notifyDatetime = $o;
		return $this;
	}

    public function getIsConfirmed(): ?bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(?bool $isConfirmed): self
    {
        $this->isConfirmed = $isConfirmed;

        return $this;
    }

    public function getYaHttpNoticeId(): ?int
    {
        return $this->yaHttpNoticeId;
    }

    public function setYaHttpNoticeId(?int $yaHttpNoticeId): self
    {
        $this->yaHttpNoticeId = $yaHttpNoticeId;

        return $this;
    }

    public function getQiwiHttpNoticeId(): ?int
    {
        return $this->qiwiHttpNoticeId;
    }

    public function setQiwiHttpNoticeId(?int $id): self
    {
        $this->qiwiHttpNoticeId = $id;

        return $this;
    }

    public function setQiwiBillId(?int $nBillId): self
	{
		$this->qiwiBillId = $nBillId;

		return $this;
	}

	public function getQiwiBillId(): ?int
	{
		return $this->qiwiBillId;
	}

}
