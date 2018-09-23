<?php
declare(strict_types = 1);

namespace TYPO3\CMS\Core\Routing\Traits;

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

use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

trait SiteLanguageAwareTrait
{
    /**
     * @var SiteLanguage
     */
    protected $siteLanguage;

    public function setSiteLanguage(SiteLanguage $siteLanguage)
    {
        $this->siteLanguage = $siteLanguage;
    }

    public function getSiteLanguage(): SiteLanguage
    {
        return $this->siteLanguage;
    }
}