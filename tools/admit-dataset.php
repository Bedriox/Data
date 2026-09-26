<?php

declare(strict_types=1);

use Bedriox\Data\ReleaseBundleAdmissionValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

$bundle = null;
$apply = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--apply') {
        $apply = true;
    } elseif (str_starts_with($argument, '--bundle=')) {
        $bundle = substr($argument, strlen('--bundle='));
    } else {
        fwrite(STDERR, "Unknown argument.\n");
        exit(64);
    }
}
if (!is_string($bundle) || $bundle === '') {
    fwrite(STDERR, "Usage: php tools/admit-dataset.php --bundle=<approved-bundle-directory> [--apply]\n");
    exit(64);
}

try {
    $approved = (new ReleaseBundleAdmissionValidator())->validate($bundle);
    if ($apply) {
        $approved->applyTo(dirname(__DIR__));
    }
    fwrite(STDOUT, sprintf(
        "Approved Bedrock %s / protocol %d bundle with %d artifacts%s.\n",
        $approved->gameVersion(),
        $approved->protocolVersion(),
        count($approved->artifacts()),
        $apply ? ' was admitted' : ' passed validation',
    ));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(65);
}
