<?php

declare(strict_types=1);

namespace Feroz\Select2TableBundle;

use SaifulFeroz\Select2TableBundle\Select2TableBundle;

/**
 * Backward compatibility bundle class.
 *
 * Registering this class keeps existing installations working. The extension
 * alias stays "feroz_select2_table" so previously written configuration under
 * that key continues to be honoured.
 *
 * @deprecated Use {@see Select2TableBundle} instead; the configuration key is "select2_table".
 */
class FerozSelect2TableBundle extends Select2TableBundle
{
    protected string $extensionAlias = 'feroz_select2_table';
}
