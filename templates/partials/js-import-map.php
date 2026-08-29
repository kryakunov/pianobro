<?php

declare(strict_types=1);

use PianoTrainer\AssetVersion;

$importMap = AssetVersion::jsImportMap();
?>
<script type="importmap"><?= json_encode($importMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?></script>
