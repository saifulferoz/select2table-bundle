<?php

declare(strict_types=1);

namespace Feroz\Select2TableBundle\DependencyInjection;

use SaifulFeroz\Select2TableBundle\DependencyInjection\SaifulFerozSelect2TableExtension;

/**
 * Backward compatibility extension alias.
 */
class FerozSelect2TableExtension extends SaifulFerozSelect2TableExtension
{
    public function getAlias(): string
    {
        return 'feroz_select2_table';
    }
}
