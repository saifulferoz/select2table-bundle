<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SaifulFerozSelect2TableBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
