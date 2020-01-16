<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * OpCodes
 *
 * @ORM\Table(name="op_codes")
 * @ORM\Entity
 * ORM\Cache(usage="READ_ONLY", region="global")
 */
class OpCodes
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
     * @ORM\Column(name="name", type="string", length=512, nullable=true)
     */
    private $name;

    public function getId(): ?int
    {
        return $this->id;
    }

	public function setId(int $id): ?self
	{
		$this->id = $id;

		return $this;
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
