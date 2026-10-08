<?php

namespace Wexample\SymfonySignatureDs\Traits;

use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonySignatureDs\WexampleSymfonySignatureDsBundle;

trait SymfonySignatureDsBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonySignatureDsBundle::class;
    }
}
