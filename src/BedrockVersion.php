<?php

declare(strict_types=1);

namespace Bedriox\Data;

/** Active admitted Bedrock dataset identity. */
final class BedrockVersion
{
    public const int PROTOCOL = 2_193;
    public const string GAME_VERSION = '1.26.52';
    public const string QUALIFIED_CLIENT_VERSION = '1.26.52';
    /** @var list<int> */
    public const array SUPPORTED_PROTOCOLS = [2_193];
    public const string ADMISSION_MANIFEST = 'manifests/bedrock-1.26.52-protocol-2193-schema-2.json';

    private function __construct() {}
}
