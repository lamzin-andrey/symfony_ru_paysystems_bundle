<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * YaNotificationType
 *
 * @ORM\Table(name="ya_notification_type")
 * @ORM\Entity
 * ORM\Cache(usage="READ_ONLY")
 */
class YaNotificationType
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
     * @ORM\Column(name="name", type="string", length=24, nullable=true, options={"comment"="Тип платежа, карта  или я-кошелек"})
     */
    private $name;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }


}
