<?php
declare(strict_types=1);

fwrite(
    STDERR,
    "[DEPRECATED] validate_json_contracts.php fue reclasificado como validacion tecnica interna. Usa validate_scraping_technical_contracts.php.\n"
);

require __DIR__ . '/validate_scraping_technical_contracts.php';
