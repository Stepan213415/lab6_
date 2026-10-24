<?php
class Capacitor
{
    private array $parameters = [];
    private ?string $photo = null;  

    public function __construct(
        private int $id,
        private string $designation,
        private CapacitorType $type,  
        private float $capacitance,    
        private float $voltage       
    ) {}

    public function getId(): int { return $this->id; }
    public function getDesignation(): string { return $this->designation; }
    public function getType(): CapacitorType { return $this->type; }
    public function getCapacitance(): float { return $this->capacitance; }
    public function getVoltage(): float { return $this->voltage; }
    public function getParameters(): array { return $this->parameters; }
    public function getPhoto(): ?string { return $this->photo; }

    public function setDesignation(string $designation): void { $this->designation = $designation; }
    public function setType(CapacitorType $type): void { $this->type = $type; }
    public function setCapacitance(float $capacitance): void { $this->capacitance = $capacitance; }
    public function setVoltage(float $voltage): void { $this->voltage = $voltage; }

    public function setPhoto(?string $photo): void { $this->photo = $photo; }

    public function addParameter(Parameter $parameter): void
    {
        $this->parameters[] = $parameter;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'designation' => $this->designation,
            'type'        => $this->type->toArray(), 
            'capacitance' => $this->capacitance,
            'voltage'     => $this->voltage,
            'photo'       => $this->photo,
            'parameters'  => array_map(
                fn(Parameter $p) => ['name' => $p->getName(), 'value' => $p->getValue()],
                $this->parameters
            ),
        ];
    }

    public function __toString(): string
    {
        $params = implode(', ', array_map(fn(Parameter $p) => (string) $p, $this->parameters));
        return sprintf(
            '#%d %s [%s] — %s мкФ, %.0f В (%s)',
            $this->id,
            $this->designation,
            $this->type,
            rtrim(rtrim(number_format($this->capacitance, 3, '.', ''), '0'), '.'),
            $this->voltage,
            $params
        );
    }
}
