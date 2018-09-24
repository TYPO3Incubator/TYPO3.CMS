<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Aspect;

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

use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

interface Buildable
{
    /**
     * @param SiteInterface $site
     * @param SiteLanguage $siteLanguage
     */
    public function __construct(SiteInterface $site, SiteLanguage $siteLanguage);

    /**
     * @return string[]
     */
    public function builds(): array;

    /**
     * @param array $settings
     * @return Applicable
     */
    public function build(array $settings): Applicable;
}
