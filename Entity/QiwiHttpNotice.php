<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * TODO тут ещё пилить и пилить
 * QiwiHttpNotice
 *
 * @ORM\Table(name="qiwi_http_notice")
 * @ORM\Entity
 * ORM\Cache(usage="READ_ONLY", region="global")
 */
class QiwiHttpNotice
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
     * @ORM\Column(name="out_summ", type="decimal", precision=10, scale=2, nullable=true, options={"comment"="Сумма, поступившая на счет"})
     */
    private $outSumm;

    /**
     * @var int|null
     *
     * @ORM\Column(name="inv_id", type="integer", nullable=true, options={"comment"="Пришедший с сервера Робокассы идентификатор нашего заказа"})
     */
    private $invId;

    /**
     * @var string|null
     *
     * @ORM\Column(name="inc_sum", type="decimal", precision=10, scale=2, nullable=true, options={"comment"="Сумма, уплаченная пользователем"})
     */
    private $incSum;

    /**
     * @var string|null
     *
     * @ORM\Column(name="payment_method", type="string", length=24, nullable=true, options={"comment"="Каким именно образом была совершена оплата"})
     */
    private $paymentMethod;

    /**
     * @var string|null
     *
     * @ORM\Column(name="inc_curr_label", type="string", length=24, nullable=true, options={"comment"="Каким именно образом была совершена оплата - ещё признак"})
     */
    private $incCurrLabel;

    /**
     * @var int|null
     *
     * @ORM\Column(name="shp_item", type="integer", nullable=true, options={"comment"="На случай, если у нас какие-то ещё варианты появятся"})
     */
    private $shpItem;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOutSumm(): ?string
    {
        return $this->outSumm;
    }

    public function setOutSumm(?string $outSumm): self
    {
        $this->outSumm = $outSumm;

        return $this;
    }

    public function getInvId(): ?int
    {
        return $this->invId;
    }

    public function setInvId(?int $invId): self
    {
        $this->invId = $invId;

        return $this;
    }

    public function getIncSum(): ?string
    {
        return $this->incSum;
    }

    public function setIncSum(?string $incSum): self
    {
        $this->incSum = $incSum;

        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getIncCurrLabel(): ?string
    {
        return $this->incCurrLabel;
    }

    public function setIncCurrLabel(?string $incCurrLabel): self
    {
        $this->incCurrLabel = $incCurrLabel;

        return $this;
    }

    public function getShpItem(): ?int
    {
        return $this->shpItem;
    }

    public function setShpItem(?int $shpItem): self
    {
        $this->shpItem = $shpItem;

        return $this;
    }


}
