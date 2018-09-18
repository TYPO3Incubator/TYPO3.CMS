<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Mapper;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Routing\SiteContext;

abstract class AbstractMapperFactory
{
    /**
     * @return string[] Names or class names this factory can build
     */
    abstract public function builds(): array;

    /**
     * @param SiteContext $context
     * @param string $name
     * @param array $settings
     * @return Mappable
     */
    abstract public function build(SiteContext $context, string $name, array $settings): Mappable;
}
