<?php

declare(strict_types=1);

namespace App\Models;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
#[ORM\HasLifecycleCallbacks]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(type: 'string', length: 200)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $slug;

    #[ORM\Column(name: 'business_type', type: 'string', length: 50)]
    private string $businessType = 'automotive';

    #[ORM\Column(type: 'string', length: 18, nullable: true)]
    private ?string $cnpj = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 200, nullable: true)]
    private ?string $email = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $address = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json', nullable: true)]
    private array $settings = [];

    #[ORM\Column(name: 'is_active', type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'string', length: 50)]
    private string $plan = 'free';

    #[ORM\Column(name: 'created_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetimetz_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->id = $this->generateUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getBusinessType(): string
    {
        return $this->businessType;
    }

    public function setBusinessType(string $businessType): self
    {
        $this->businessType = $businessType;
        return $this;
    }

    public function getCnpj(): ?string
    {
        return $this->cnpj;
    }

    public function setCnpj(?string $cnpj): self
    {
        $this->cnpj = $cnpj;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getAddress(): array
    {
        return $this->address;
    }

    /** @param array<string, mixed> $address */
    public function setAddress(array $address): self
    {
        $this->address = $address;
        return $this;
    }

    /** @return array<string, mixed> */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /** @param array<string, mixed> $settings */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): self
    {
        $this->plan = $plan;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
