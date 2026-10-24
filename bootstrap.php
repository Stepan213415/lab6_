 <?php
require_once __DIR__ . '/classes/CapacitorType.php';
require_once __DIR__ . '/classes/Parameter.php';
require_once __DIR__ . '/classes/Capacitor.php';
require_once __DIR__ . '/classes/CapacitorRepository.php';

$repository = new CapacitorRepository(
    getenv('CAP_TYPES_CSV') ?: __DIR__ . '/data/capacitor_types.csv',
    getenv('CAP_DATA_CSV')  ?: __DIR__ . '/data/capacitors.csv'
);
