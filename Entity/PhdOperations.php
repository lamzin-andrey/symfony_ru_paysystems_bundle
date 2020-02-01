<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Operations
 *
 * @ORM\Table(name="phd_operations")
 * @ORM\Entity
 * ORM\Cache(usage="READ_ONLY", region="global")
 */
class PhdOperations
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
     * @ORM\Column(name="user_id", type="integer", nullable=true, options={"comment"="Идентификатор пользователя phd_users"})
     */
    private $userId;

    /**
     * @var int|null
     *
     * @ORM\Column(name="op_code_id", type="integer", nullable=true, options={"comment"="Код операции из op_codes"})
     */
    private $opCodeId;

    /**
     * @var int|null
     *
     * @ORM\Column(name="main_id", type="integer", nullable=true, options={"comment"="Идентификатор phd_messages.id", "default"="0"})
     */
    private $mainId = '0';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="created", type="datetime", nullable=true, options={"comment"="Время операции", "default"="CURRENT_TIMESTAMP"})
     */
    private $created;

    /**
     * @var string|null
     *
     * @ORM\Column(name="sum", type="decimal", precision=10, scale=2, nullable=true, options={"comment"="В случае покупки - сумма в рублях потраченная пользователем"})
     */
    private $sum;

    /**
     * @var int|null
     *
     * @ORM\Column(name="pay_transaction_id", type="integer", nullable=true, options={"comment"="Идентификатор из pay_transaction", "default"="0"})
     */
    private $payTransactionId = '0';

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

    public function getOpCodeId(): ?int
    {
        return $this->opCodeId;
    }

    public function setOpCodeId(?int $opCodeId): self
    {
        $this->opCodeId = $opCodeId;

        return $this;
    }

    public function getUpcount(): ?int
    {
        return $this->upcount;
    }

    public function setUpcount(?int $upcount): self
    {
        $this->upcount = $upcount;

        return $this;
    }

    public function getMainId(): ?int
    {
        return $this->mainId;
    }

    public function setMainId(?int $mainId): self
    {
        $this->mainId = $mainId;

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

    public function getSum(): ?string
    {
        return $this->sum;
    }

    public function setSum(?string $sum): self
    {
        $this->sum = $sum;

        return $this;
    }

    public function getPayTransactionId(): ?int
    {
        return $this->payTransactionId;
    }

    public function setPayTransactionId(?int $payTransactionId): self
    {
        $this->payTransactionId = $payTransactionId;

        return $this;
    }


}
