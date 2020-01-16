<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * YaHttpNotice
 *
 * @ORM\Table(name="ya_http_notice")
 * @ORM\Entity
 * ORM\Cache(usage="READ_ONLY")
 */
class YaHttpNotice
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
     * @var string|null
     *
     * @ORM\Column(name="notification_type_id", type="string", length=24, nullable=true, options={"comment"="1 - card-incoming, 2 - wallet, 3 - mobile если понадобится"})
     */
    private $notificationTypeId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="amount", type="decimal", precision=10, scale=2, nullable=true, options={"comment"="Сумма, поступившая на кошелёк"})
     */
    private $amount;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="_datetime", type="datetime", nullable=true, options={"comment"="Пришедшая с сервера Я-Денег временная метка"})
     */
    private $datetime;

    /**
     * @var string|null
     *
     * @ORM\Column(name="codepro", type="string", length=6, nullable=true, options={"comment"="Был ли защищён кодом протекции"})
     */
    private $codepro;

    /**
     * @var string|null
     *
     * @ORM\Column(name="withdraw_amount", type="decimal", precision=10, scale=2, nullable=true, options={"comment"="Сумма, уплаченная пользователем"})
     */
    private $withdrawAmount;

    /**
     * @var string|null
     *
     * @ORM\Column(name="sender", type="string", length=512, nullable=true, options={"comment"="Данные отпправителя"})
     */
    private $sender;

    /**
     * @var string|null
     *
     * @ORM\Column(name="unaccepted", type="string", length=6, nullable=true, options={"comment"="Флаг означает, что пользователь не получил перевод"})
     */
    private $unaccepted;

    /**
     * @var string|null
     *
     * @ORM\Column(name="operation_label", type="string", length=64, nullable=true, options={"comment"="см. документацию Яндекс денег https://tech.yandex.ru/money/doc/dg/reference/notification-p2p-incoming-docpage/"})
     */
    private $operationLabel;

    /**
     * @var string|null
     *
     * @ORM\Column(name="operation_id", type="string", length=32, nullable=true, options={"comment"="см. документацию Яндекс денег https://tech.yandex.ru/money/doc/dg/reference/notification-p2p-incoming-docpage/"})
     */
    private $operationId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="label", type="string", length=32, nullable=true, options={"comment"="см. документацию Яндекс денег https://tech.yandex.ru/money/doc/dg/reference/notification-p2p-incoming-docpage/"})
     */
    private $label;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNotificationTypeId(): ?string
    {
        return $this->notificationTypeId;
    }

    public function setNotificationTypeId(?string $notificationTypeId): self
    {
        $this->notificationTypeId = $notificationTypeId;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getDatetime(): ?\DateTimeInterface
    {
        return $this->datetime;
    }

    public function setDatetime(?\DateTimeInterface $datetime): self
    {
        $this->datetime = $datetime;

        return $this;
    }

    public function getCodepro(): ?string
    {
        return $this->codepro;
    }

    public function setCodepro(?string $codepro): self
    {
        $this->codepro = $codepro;

        return $this;
    }

    public function getWithdrawAmount(): ?string
    {
        return $this->withdrawAmount;
    }

    public function setWithdrawAmount(?string $withdrawAmount): self
    {
        $this->withdrawAmount = $withdrawAmount;

        return $this;
    }

    public function getSender(): ?string
    {
        return $this->sender;
    }

    public function setSender(?string $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    public function getUnaccepted(): ?string
    {
        return $this->unaccepted;
    }

    public function setUnaccepted(?string $unaccepted): self
    {
        $this->unaccepted = $unaccepted;

        return $this;
    }

    public function getOperationLabel(): ?string
    {
        return $this->operationLabel;
    }

    public function setOperationLabel(?string $operationLabel): self
    {
        $this->operationLabel = $operationLabel;

        return $this;
    }

    public function getOperationId(): ?string
    {
        return $this->operationId;
    }

    public function setOperationId(?string $operationId): self
    {
        $this->operationId = $operationId;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }


}
