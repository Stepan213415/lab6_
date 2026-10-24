<?php
class CapacitorRepository
{
    /** @var array<int,  */
    private array $types = [];
    /** @var array<int,  */
    private array $capacitors = [];
    private int $nextTypeId = 1;
    private int $nextCapacitorId = 1;

    public function __construct(
        private string $typesFile,
        private string $capacitorsFile
    ) {
        $this->loadTypes();
        $this->loadCapacitors();
    }

    private function loadTypes(): void
    {
        $handle = fopen($this->typesFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("Не вдалося відкрити {$this->typesFile}");
        }
        fgetcsv($handle, 0, ',', '"', '');   
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($row === [null]) continue;  
            [$id, $name] = $row;
            $this->types[(int) $id] = new CapacitorType((int) $id, $name);
            $this->nextTypeId = max($this->nextTypeId, (int) $id + 1);
        }
        fclose($handle);
    }

    private function loadCapacitors(): void
    {
        $handle = fopen($this->capacitorsFile, 'r');
        if ($handle === false) {
            throw new RuntimeException("Не вдалося відкрити {$this->capacitorsFile}");
        }
        fgetcsv($handle, 0, ',', '"', ''); 
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($row === [null]) continue;
            [$id, $designation, $typeId, $capacitance, $voltage, $paramsRaw, $photo] = array_pad($row, 7, '');
            $capacitor = new Capacitor(
                (int) $id, $designation, $this->types[(int) $typeId],
                (float) $capacitance, (float) $voltage
            );
            if ($paramsRaw !== '') {
                foreach (explode('|', $paramsRaw) as $pair) {
                    [$pName, $pValue] = explode(':', $pair, 2);
                    $capacitor->addParameter(new Parameter($pName, $pValue));
                }
            }
            if ($photo !== '') {
                $capacitor->setPhoto($photo);
            }
            $this->capacitors[(int) $id] = $capacitor;
            $this->nextCapacitorId = max($this->nextCapacitorId, (int) $id + 1);
        }
        fclose($handle);
    }

    public function getTypes(): array { return $this->types; }
    public function findTypeById(int $id): ?CapacitorType { return $this->types[$id] ?? null; }
    public function findAll(): array { return $this->capacitors; }
    public function findById(int $id): ?Capacitor { return $this->capacitors[$id] ?? null; }

    public function typeNameExists(string $name): bool
    {
        foreach ($this->types as $type) {
            if (mb_strtolower($type->getName()) === mb_strtolower($name)) return true;
        }
        return false;
    }

    public function createType(string $name): CapacitorType
    {
        $type = new CapacitorType($this->nextTypeId++, $name);
        $this->types[$type->getId()] = $type;
        $this->saveTypes();
        return $type;
    }

    public function create(
        string $designation, CapacitorType $type, float $capacitance, float $voltage
    ): Capacitor {
        $capacitor = new Capacitor($this->nextCapacitorId++, $designation, $type, $capacitance, $voltage);
        $this->capacitors[$capacitor->getId()] = $capacitor;
        $this->save();
        return $capacitor;
    }

    public function attachPhoto(int $capacitorId, string $photoFilename): bool
    {
        $capacitor = $this->findById($capacitorId);
        if ($capacitor === null) {
            return false;
        }
        $capacitor->setPhoto($photoFilename);
        $this->save();
        return true;
    }

    public function addParameterToCapacitor(int $capacitorId, string $name, string $value): bool
    {
        $capacitor = $this->findById($capacitorId);
        if ($capacitor === null) {
            return false;
        }
        $capacitor->addParameter(new Parameter($name, $value));
        $this->save();
        return true;
    }

    public function update(int $id, array $data): bool
    {
        $capacitor = $this->findById($id);
        if ($capacitor === null) return false;
        if (isset($data['designation'])) $capacitor->setDesignation($data['designation']);
        if (isset($data['capacitance'])) $capacitor->setCapacitance((float) $data['capacitance']);
        if (isset($data['voltage']))     $capacitor->setVoltage((float) $data['voltage']);
        if (isset($data['type']))        $capacitor->setType($data['type']);
        $this->save();
        return true;
    }

    public function delete(int $id): bool
    {
        if (!isset($this->capacitors[$id])) return false;
        unset($this->capacitors[$id]);
        $this->save();
        return true;
    }

    private function saveTypes(): void
    {
        $handle = fopen($this->typesFile, 'w');
        fputcsv($handle, ['id', 'name'], ',', '"', '');
        foreach ($this->types as $type) {
            fputcsv($handle, [$type->getId(), $type->getName()], ',', '"', '');
        }
        fclose($handle);
    }

    public function save(): void
    {
        $handle = fopen($this->capacitorsFile, 'w');
        fputcsv($handle, ['id', 'designation', 'type_id', 'capacitance_uf', 'voltage_v', 'parameters', 'photo'], ',', '"', '');
        foreach ($this->capacitors as $c) {
            $params = implode('|', array_map(
                fn(Parameter $p) => $p->getName() . ':' . $p->getValue(),
                $c->getParameters()
            ));
            fputcsv($handle, [
                $c->getId(), $c->getDesignation(), $c->getType()->getId(),
                $c->getCapacitance(), $c->getVoltage(), $params, $c->getPhoto() ?? '',
            ], ',', '"', '');
        }
        fclose($handle);
    }
}
